<?php
declare(strict_types=1);

namespace Prismatch\Core;

class Logger {
    private static ?string $logDir = null;

    public static function getLogDir(): string {
        if (self::$logDir === null) {
            self::$logDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir(self::$logDir)) {
                @mkdir(self::$logDir, 0777, true);
            }
        }
        return self::$logDir;
    }

    public static function log(string $level, string $tag, string $message, array $context = []): void {
        try {
            $logDir = self::getLogDir();
            $date = date('Y-m-d');
            $filePath = $logDir . DIRECTORY_SEPARATOR . "room_{$date}.log";
            
            $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
            $contextStr = !empty($context) ? ' | ctx=' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
            $line = sprintf("[%s UTC] [%s] [%s] %s%s\n", $now, strtoupper($level), $tag, $message, $contextStr);

            @file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            @error_log("[Prismatch Logger] Failed to write log: " . $e->getMessage());
        }
    }

    public static function info(string $tag, string $message, array $context = []): void {
        self::log('INFO', $tag, $message, $context);
    }

    public static function error(string $tag, string $message, array $context = [], ?\Throwable $e = null): void {
        if ($e !== null) {
            $context['exception'] = [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }
        self::log('ERROR', $tag, $message, $context);
    }

    public static function warning(string $tag, string $message, array $context = []): void {
        self::log('WARN', $tag, $message, $context);
    }

    public static function debug(string $tag, string $message, array $context = []): void {
        self::log('DEBUG', $tag, $message, $context);
    }

    public static function room(string $action, string $guid, array $context = [], string $level = 'INFO'): void {
        $message = "action={$action} | guid={$guid}";
        self::log($level, 'RoomGame', $message, $context);
    }
}
