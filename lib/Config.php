<?php

declare(strict_types=1);

final class Config
{
    private static ?array $cache = null;

    public static function path(): string
    {
        return dirname(__DIR__) . '/config/salon.json';
    }

    public static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = self::path();
        if (!is_readable($path)) {
            throw new RuntimeException('Salon-Konfiguration nicht gefunden: ' . $path);
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            throw new RuntimeException('Ungültige salon.json');
        }

        self::$cache = $data;
        return self::$cache;
    }

    public static function serviceById(string $id): ?array
    {
        foreach (self::load()['services'] as $service) {
            if (($service['id'] ?? '') === $id) {
                return $service;
            }
        }
        return null;
    }
}
