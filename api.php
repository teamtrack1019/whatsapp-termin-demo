<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/lib/BookingService.php';

function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    $service = new BookingService();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? '';

    if ($method === 'GET' && $action === 'salon') {
        json_out(['ok' => true, 'salon' => $service->salonPublic()]);
    }

    if ($method === 'GET' && $action === 'days') {
        $serviceId = (string) ($_GET['service_id'] ?? '');
        json_out(['ok' => true, 'days' => $service->availableDays($serviceId)]);
    }

    if ($method === 'GET' && $action === 'times') {
        $serviceId = (string) ($_GET['service_id'] ?? '');
        $date = (string) ($_GET['date'] ?? '');
        json_out(['ok' => true, 'times' => $service->availableTimes($serviceId, $date)]);
    }

    if ($method === 'POST' && $action === 'book') {
        $result = $service->createBooking(read_json_body());
        json_out(['ok' => true] + $result);
    }

    json_out(['ok' => false, 'error' => 'Unbekannte Aktion.'], 404);
} catch (InvalidArgumentException $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (RuntimeException $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 409);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Serverfehler: ' . $e->getMessage()], 500);
}
