<?php

declare(strict_types=1);

/**
 * Placeholder for WhatsApp Business Cloud API / 360dialog / Wati etc.
 * Demo only — no Meta credentials required.
 */
final class WhatsAppStub
{
    public static function sendText(string $to, string $body, string $kind = 'message'): array
    {
        $payload = [
            'provider' => 'stub-whatsapp',
            'action' => 'send_text',
            'kind' => $kind,
            'to' => $to,
            'body' => $body,
            'status' => 'stubbed',
            'note' => 'Replace with Meta Cloud API / 360dialog later.',
        ];

        self::log($payload);
        return $payload;
    }

    public static function scheduleReminder(array $booking, int $hoursBefore): array
    {
        $starts = new DateTimeImmutable($booking['starts_at']);
        $sendAt = $starts->modify(sprintf('-%d hours', $hoursBefore));

        $body = sprintf(
            "Erinnerung: Dein Termin bei Salon Mira am %s um %s (%s). Bis bald!",
            $starts->format('d.m.Y'),
            $starts->format('H:i'),
            $booking['service_name']
        );

        $payload = [
            'provider' => 'stub-whatsapp',
            'action' => 'schedule_reminder',
            'hours_before' => $hoursBefore,
            'send_at' => $sendAt->format(DateTimeInterface::ATOM),
            'to' => $booking['customer_phone'],
            'body' => $body,
            'status' => 'stubbed-simulated',
            'note' => 'In production: queue / cron / provider template message.',
        ];

        self::log($payload);
        return $payload;
    }

    private static function log(array $payload): void
    {
        $dir = dirname(__DIR__) . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $line = date('c') . ' ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($dir . '/whatsapp-stub.log', $line, FILE_APPEND);
    }
}
