<?php declare(strict_types=1);

namespace App\Core;

/**
 * CSRF-защита: токен в сессии, поле в форме, проверка для POST.
 * PHP 7.4.
 */
class CSRF
{
    /** @var string */
    const TOKEN_KEY = '_csrf_token';

    /** @var string */
    const FIELD_NAME = '_token';

    /**
     * Создание токена, если его нет.
     */
    public static function init(): void
    {
        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Текущий токен.
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::TOKEN_KEY])) {
            self::init();
        }
        return (string)$_SESSION[self::TOKEN_KEY];
    }

    /**
     * Скрытое поле для формы.
     */
    public static function field(string $name = self::FIELD_NAME): string
    {
        return '<input type="hidden" name="' . e($name) . '" value="' . e(self::token()) . '">';
    }

    /**
     * Токен из запроса: POST-поле или заголовок (для AJAX).
     */
    public static function fromRequest(): string
    {
        if (isset($_POST[self::FIELD_NAME]) && is_string($_POST[self::FIELD_NAME])) {
            return (string)$_POST[self::FIELD_NAME];
        }

        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        $raw = @file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded[self::FIELD_NAME]) && is_string($decoded[self::FIELD_NAME])) {
                return (string)$decoded[self::FIELD_NAME];
            }
        }

        return '';
    }

    /**
     * Проверка токена.
     */
    public static function check(?string $token = null): bool
    {
        if ($token === null) {
            $token = self::fromRequest();
        }

        $sessionToken = isset($_SESSION[self::TOKEN_KEY]) ? (string)$_SESSION[self::TOKEN_KEY] : '';

        if ($sessionToken === '' || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Проверка для POST-запроса: при ошибке отдаёт 419 и завершает работу.
     */
    public static function validateOrFail(): void
    {
        if (self::check()) {
            return;
        }

        Logger::warning('Отклонён запрос без валидного CSRF-токена');

        http_response_code(419);

        if (self::isAjax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Сессия истекла. Обновите страницу.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        Session::flash('error', 'Сессия истекла, попробуйте ещё раз.');
        redirect('/');
    }

    /**
     * AJAX-запрос?
     */
    public static function isAjax(): bool
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            return true;
        }
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return true;
        }
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return true;
        }
        return false;
    }

    /**
     * Ротация токена (например, после входа).
     */
    public static function regenerate(): void
    {
        $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
    }
}
