<?php
declare(strict_types=1);

namespace Prismatch\Core;

use PDO;

class Database {
    private static ?PDO $instance = null;
    private static bool $schemaInitialized = false;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Initialize schema once per request/session if needed
            if (!self::$schemaInitialized) {
                SchemaManager::initSchema(self::$instance);
                self::$schemaInitialized = true;
            }
        }

        return self::$instance;
    }

    public static function generateUuid(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function nowUtc(): string {
        $dt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s.v');
    }

    public static function isoToUtc(string $iso): string {
        try {
            $dt = new \DateTimeImmutable($iso);
        } catch (\Exception $e) {
            return self::nowUtc();
        }
        $dt = $dt->setTimezone(new \DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s.v');
    }
}
