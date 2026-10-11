<?php declare(strict_types=1);

namespace App\Core;

/**
 * Простой файловый логгер. Пишет в logs/error.log.
 * PHP 7.4.
 */
class Logger
{
    /** @var string */
    private static $dir = '';

    /** @var bool */
    private static $initialized = false;

    /**
     * Инициализация каталога логов.
     */
    public static function init(string $dir): void
    {
        self::$dir = rtrim($dir, '/\\');

        if (!is_dir(self::$dir)) {
            @mkdir(self::$dir, 0775, true);
        }
        if (!is_dir(self::$dir . '/cache')) {
            @mkdir(self::$dir . '/cache', 0775, true);
        }

        self::$initialized = true;
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (config('app.debug')) {
            self::write('DEBUG', $message, $context);
        }
    }

    /**
     * Запись строки в лог.
     */
    private static function write(string $level, string $message, array $context = []): void
    {
        if (!self::$initialized) {
            self::$dir = defined('BASE_PATH') ? BASE_PATH . '/logs' : __DIR__ . '/../../logs';
            if (!is_dir(self::$dir)) {
                @mkdir(self::$dir, 0775, true);
            }
            self::$initialized = true;
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $level . ': ' . $message;

        if (!empty($context)) {
            $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                $line .= ' ' . $encoded;
            }
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli';
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '-';

        $line .= ' | ip=' . $ip . ' uri=' . $uri . PHP_EOL;

        @file_put_contents(self::$dir . '/error.log', $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Путь к файлу лога.
     */
    public static function file(): string
    {
        return self::$dir . '/error.log';
    }
}
