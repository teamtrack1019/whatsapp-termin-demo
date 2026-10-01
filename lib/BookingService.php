<?php

declare(strict_types=1);

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Database.php';
require_once dirname(__DIR__) . '/stubs/CalendarStub.php';
require_once dirname(__DIR__) . '/stubs/WhatsAppStub.php';

final class BookingService
{
    private const WEEKDAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function salonPublic(): array
    {
        $cfg = Config::load();
        return [
            'name' => $cfg['name'],
            'tagline' => $cfg['tagline'],
            'phone' => $cfg['phone'],
            'botName' => $cfg['botName'],
            'welcomeMessage' => $cfg['welcomeMessage'],
            'reminderHoursBefore' => $cfg['reminderHoursBefore'],
            'currency' => $cfg['currency'],
            'services' => $cfg['services'],
            'bookingHorizonDays' => $cfg['bookingHorizonDays'],
        ];
    }

    public function availableDays(string $serviceId): array
    {
        $service = Config::serviceById($serviceId);
        if ($service === null) {
            throw new InvalidArgumentException('Unbekannter Service.');
        }

        $cfg = Config::load();
        $tz = new DateTimeZone($cfg['timezone'] ?? 'Europe/Berlin');
        $horizon = (int) ($cfg['bookingHorizonDays'] ?? 14);
        $today = new DateTimeImmutable('today', $tz);
        $closedDates = array_flip($cfg['closedDates'] ?? []);
        $days = [];

        for ($i = 0; $i < $horizon; $i++) {
            $date = $today->modify("+{$i} days");
            $ymd = $date->format('Y-m-d');
            if (isset($closedDates[$ymd])) {
                continue;
            }

            $slots = $this->slotsForDate($date, $service, $cfg);
            if ($slots === []) {
                continue;
            }

            $days[] = [
                'date' => $ymd,
                'label' => $this->germanDayLabel($date),
                'weekday' => self::WEEKDAY_KEYS[(int) $date->format('w')],
                'slotCount' => count($slots),
            ];
        }

        return $days;
    }

    public function availableTimes(string $serviceId, string $dateYmd): array
    {
        $service = Config::serviceById($serviceId);
        if ($service === null) {
            throw new InvalidArgumentException('Unbekannter Service.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            throw new InvalidArgumentException('Ungültiges Datum.');
        }

        $cfg = Config::load();
        $tz = new DateTimeZone($cfg['timezone'] ?? 'Europe/Berlin');
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateYmd, $tz);
        if ($date === false) {
            throw new InvalidArgumentException('Ungültiges Datum.');
        }

        $closedDates = $cfg['closedDates'] ?? [];
        if (in_array($dateYmd, $closedDates, true)) {
            return [];
        }

        return array_map(
            static fn(string $time): array => [
                'time' => $time,
                'label' => $time . ' Uhr',
            ],
            $this->slotsForDate($date, $service, $cfg)
        );
    }

    public function createBooking(array $input): array
    {
        $serviceId = trim((string) ($input['service_id'] ?? ''));
        $dateYmd = trim((string) ($input['date'] ?? ''));
        $time = trim((string) ($input['time'] ?? ''));
        $name = trim((string) ($input['customer_name'] ?? ''));
        $phone = trim((string) ($input['customer_phone'] ?? ''));

        $service = Config::serviceById($serviceId);
        if ($service === null) {
            throw new InvalidArgumentException('Unbekannter Service.');
        }
        if ($name === '' || strlen($name) < 2) {
            throw new InvalidArgumentException('Bitte gib deinen Namen an.');
        }
        if ($phone === '' || !preg_match('/^[+\d][\d\s\/\-()]{5,}$/', $phone)) {
            throw new InvalidArgumentException('Bitte gib eine gültige Telefonnummer an.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new InvalidArgumentException('Datum oder Uhrzeit ungültig.');
        }

        $cfg = Config::load();
        $tz = new DateTimeZone($cfg['timezone'] ?? 'Europe/Berlin');
        $starts = DateTimeImmutable::createFromFormat('Y-m-d H:i', $dateYmd . ' ' . $time, $tz);
        if ($starts === false) {
            throw new InvalidArgumentException('Termin ungültig.');
        }

        $available = $this->slotsForDate($starts, $service, $cfg);
        if (!in_array($time, $available, true)) {
            throw new RuntimeException('Dieser Termin ist leider nicht mehr verfügbar.');
        }

        $duration = (int) $service['durationMinutes'];
        $ends = $starts->modify("+{$duration} minutes");
        $now = new DateTimeImmutable('now', $tz);
        $reminderHours = (int) ($cfg['reminderHoursBefore'] ?? 24);

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            // Lock against concurrent double-booking for overlapping windows
            $overlap = $pdo->prepare(
                'SELECT id FROM bookings
                 WHERE status = \'confirmed\'
                   AND starts_at < :ends
                   AND ends_at > :starts
                 LIMIT 1'
            );
            $overlap->execute([
                ':starts' => $starts->format('Y-m-d H:i:s'),
                ':ends' => $ends->format('Y-m-d H:i:s'),
            ]);
            if ($overlap->fetch()) {
                throw new RuntimeException('Doppelbuchung verhindert: Slot bereits belegt.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO bookings
                 (service_id, service_name, starts_at, ends_at, customer_name, customer_phone, status, reminder_simulated_at, created_at)
                 VALUES
                 (:service_id, :service_name, :starts_at, :ends_at, :customer_name, :customer_phone, \'confirmed\', :reminder, :created)'
            );

            $reminderSimulatedAt = $starts->modify(sprintf('-%d hours', $reminderHours))->format(DateTimeInterface::ATOM);

            $stmt->execute([
                ':service_id' => $service['id'],
                ':service_name' => $service['name'],
                ':starts_at' => $starts->format('Y-m-d H:i:s'),
                ':ends_at' => $ends->format('Y-m-d H:i:s'),
                ':customer_name' => $name,
                ':customer_phone' => $phone,
                ':reminder' => $reminderSimulatedAt,
                ':created' => $now->format('Y-m-d H:i:s'),
            ]);

            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                throw new RuntimeException('Doppelbuchung verhindert: Slot bereits belegt.');
            }
            throw $e;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $booking = [
            'id' => $id,
            'service_id' => $service['id'],
            'service_name' => $service['name'],
            'starts_at' => $starts->format(DateTimeInterface::ATOM),
            'ends_at' => $ends->format(DateTimeInterface::ATOM),
            'customer_name' => $name,
            'customer_phone' => $phone,
            'status' => 'confirmed',
            'price' => $service['price'],
            'durationMinutes' => $duration,
            'display' => [
                'date' => $starts->format('d.m.Y'),
                'time' => $starts->format('H:i'),
                'weekday' => $this->germanWeekday($starts),
            ],
        ];

        $calendar = CalendarStub::createEvent($booking);
        $waConfirm = WhatsAppStub::sendText(
            $phone,
            sprintf(
                "Bestätigung: %s am %s um %s Uhr. Dauer ca. %d Min. Preis ab %.0f €. Bis bald bei %s!",
                $service['name'],
                $booking['display']['date'],
                $booking['display']['time'],
                $duration,
                (float) $service['price'],
                $cfg['name']
            ),
            'confirmation'
        );
        $waReminder = WhatsAppStub::scheduleReminder($booking, $reminderHours);

        return [
            'booking' => $booking,
            'stubs' => [
                'calendar' => $calendar,
                'whatsapp_confirmation' => $waConfirm,
                'whatsapp_reminder' => $waReminder,
            ],
            'reminder_note' => sprintf(
                'Simulierte Erinnerung: %d Stunden vor dem Termin (geplant für %s).',
                $reminderHours,
                $waReminder['send_at'] ?? $reminderSimulatedAt
            ),
        ];
    }

    /** @return list<string> HH:MM times still free */
    private function slotsForDate(DateTimeImmutable $date, array $service, array $cfg): array
    {
        $tz = $date->getTimezone();
        $weekday = self::WEEKDAY_KEYS[(int) $date->format('w')];
        $hours = $cfg['openingHours'][$weekday] ?? null;
        if ($hours === null || empty($hours['open']) || empty($hours['close'])) {
            return [];
        }

        $interval = (int) ($cfg['slotIntervalMinutes'] ?? 30);
        $duration = (int) $service['durationMinutes'];
        $open = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $hours['open'], $tz);
        $close = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $hours['close'], $tz);
        if ($open === false || $close === false) {
            return [];
        }

        $now = new DateTimeImmutable('now', $tz);
        $booked = $this->bookedRangesForDay($date->format('Y-m-d'), $tz);

        $slots = [];
        for ($cursor = $open; $cursor < $close; $cursor = $cursor->modify("+{$interval} minutes")) {
            $slotEnd = $cursor->modify("+{$duration} minutes");
            if ($slotEnd > $close) {
                break;
            }
            // Skip past slots for today
            if ($cursor <= $now) {
                continue;
            }
            if ($this->overlapsAny($cursor, $slotEnd, $booked)) {
                continue;
            }
            $slots[] = $cursor->format('H:i');
        }

        return $slots;
    }

    /** @return list<array{0:DateTimeImmutable,1:DateTimeImmutable}> */
    private function bookedRangesForDay(string $ymd, DateTimeZone $tz): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT starts_at, ends_at FROM bookings
             WHERE status = \'confirmed\'
               AND date(starts_at) = :d'
        );
        $stmt->execute([':d' => $ymd]);

        $ranges = [];
        foreach ($stmt->fetchAll() as $row) {
            $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['starts_at'], $tz);
            $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['ends_at'], $tz);
            if ($start === false || $end === false) {
                continue;
            }
            $ranges[] = [$start, $end];
        }
        return $ranges;
    }

    /** @param list<array{0:DateTimeImmutable,1:DateTimeImmutable}> $ranges */
    private function overlapsAny(DateTimeImmutable $start, DateTimeImmutable $end, array $ranges): bool
    {
        foreach ($ranges as [$bStart, $bEnd]) {
            if ($start < $bEnd && $end > $bStart) {
                return true;
            }
        }
        return false;
    }

    private function germanDayLabel(DateTimeImmutable $date): string
    {
        $today = new DateTimeImmutable('today', $date->getTimezone());
        $diff = (int) $today->diff($date)->format('%r%a');
        if ($diff === 0) {
            return 'Heute, ' . $date->format('d.m.');
        }
        if ($diff === 1) {
            return 'Morgen, ' . $date->format('d.m.');
        }
        return $this->germanWeekday($date) . ', ' . $date->format('d.m.');
    }

    private function germanWeekday(DateTimeImmutable $date): string
    {
        $map = [
            'Monday' => 'Montag',
            'Tuesday' => 'Dienstag',
            'Wednesday' => 'Mittwoch',
            'Thursday' => 'Donnerstag',
            'Friday' => 'Freitag',
            'Saturday' => 'Samstag',
            'Sunday' => 'Sonntag',
        ];
        return $map[$date->format('l')] ?? $date->format('l');
    }
}
