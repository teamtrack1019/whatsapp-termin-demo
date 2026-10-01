<?php

declare(strict_types=1);

/**
 * Placeholder for a real calendar provider (Google Calendar, Cal.com, Outlook, …).
 * Currently logs intent only — no external API calls.
 */
final class CalendarStub
{
    public static function createEvent(array $booking): array
    {
        $payload = [
            'provider' => 'stub-calendar',
            'action' => 'create_event',
            'title' => $booking['service_name'] . ' – ' . $booking['customer_name'],
            'starts_at' => $booking['starts_at'],
            'ends_at' => $booking['ends_at'],
            'attendee' => [
                'name' => $booking['customer_name'],
                'phone' => $booking['customer_phone'],
            ],
            'status' => 'stubbed',
            'note' => 'Replace with Google Calendar / Cal.com API later.',
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
        file_put_contents($dir . '/calendar-stub.log', $line, FILE_APPEND);
    }
}
