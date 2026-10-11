<?php declare(strict_types=1);

namespace App\Core;

/**
 * Обёртка над $_SESSION: значения, flash-сообщения, регенерация.
 * PHP 7.4.
 *
 * Механика flash: записанное через flash() попадает в «_flash_new»
 * и на следующем запросе переезжает в «_flash» (живёт ровно один запрос).
 */
class Session
{
    /** @var bool */
    private static $started = false;

    /**
     * Старт сессии с безопасными параметрами.
     */
    public static function start(array $options = []): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            self::rotateFlash();
            return;
        }

        $name = isset($options['name']) ? (string)$options['name'] : 'honer_sid';
        $lifetime = isset($options['lifetime']) ? (int)$options['lifetime'] : 604800;

        $isSecure = (isset($options['secure']) && $options['secure'])
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        session_name($name);

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool)$isSecure,
            'httponly' => isset($options['httponly']) ? (bool)$options['httponly'] : true,
            'samesite' => isset($options['samesite']) ? (string)$options['samesite'] : 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string)$lifetime);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        self::$started = true;
        self::rotateFlash();
    }

    /**
     * Перенос flash-данных: записанное ранее становится доступным сейчас.
     */
    private static function rotateFlash(): void
    {
        $new = isset($_SESSION['_flash_new']) && is_array($_SESSION['_flash_new'])
            ? $_SESSION['_flash_new']
            : [];

        $_SESSION['_flash'] = $new;
        $_SESSION['_flash_new'] = [];
    }

    /**
     * Значение из сессии.
     */
    public static function get(string $key, $default = null)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    /**
     * Записать значение.
     */
    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Есть ли ключ.
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Удалить ключ.
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Все данные сессии.
     */
    public static function all(): array
    {
        return $_SESSION;
    }

    /**
     * Flash-сообщение на следующий запрос.
     */
    public static function flash(string $key, $value): void
    {
        if (!isset($_SESSION['_flash_new']) || !is_array($_SESSION['_flash_new'])) {
            $_SESSION['_flash_new'] = [];
        }
        $_SESSION['_flash_new'][$key] = $value;
    }

    /**
     * Чтение flash-сообщения текущего запроса.
     */
    public static function getFlash(string $key, $default = null)
    {
        return isset($_SESSION['_flash'][$key]) ? $_SESSION['_flash'][$key] : $default;
    }

    public static function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Сохранить старые значения формы и ошибки валидации (перед редиректом).
     */
    public static function withErrors(array $errors, array $old = []): void
    {
        self::flash('_errors', $errors);
        self::flash('_old', $old);
    }

    /**
     * Регенерация ID (после входа/выхода).
     */
    public static function regenerate(bool $deleteOld = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOld);
        }
    }

    /**
     * Полное уничтожение сессии.
     */
    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$started = false;
    }

    /**
     * Счётчик попыток (rate limiting).
     */
    public static function incrementAttempts(string $key): int
    {
        $data = self::get($key, ['count' => 0, 'time' => time()]);
        $data['count'] = (int)$data['count'] + 1;
        $data['time'] = time();
        self::set($key, $data);
        return $data['count'];
    }

    /**
     * Превышен ли лимит попыток.
     */
    public static function tooManyAttempts(string $key, int $limit, int $window): bool
    {
        $data = self::get($key, null);
        if ($data === null || !is_array($data)) {
            return false;
        }
        if (time() - (int)$data['time'] > $window) {
            self::remove($key);
            return false;
        }
        return (int)$data['count'] >= $limit;
    }

    public static function clearAttempts(string $key): void
    {
        self::remove($key);
    }
}
