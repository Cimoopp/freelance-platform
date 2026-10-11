<?php declare(strict_types=1);

/**
 * Глобальные функции-хелперы.
 * Загружается до автозагрузчика, поэтому здесь нет пространства имён.
 * PHP 7.4.
 */

if (!function_exists('config')) {
    /**
     * Доступ к конфигурации по точечному ключу: config('db.host').
     */
    function config($key = null, $default = null)
    {
        static $config = null;

        if ($config === null) {
            $path = defined('APP_PATH') ? APP_PATH . '/Config/config.php' : __DIR__ . '/../Config/config.php';
            $config = require $path;
        }

        if ($key === null) {
            return $config;
        }

        $value = $config;
        $parts = explode('.', (string)$key);

        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }

        return $value;
    }
}

if (!function_exists('e')) {
    /** Безопасный вывод текста в HTML. */
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** Абсолютный путь от корня сайта: url('/services'). */
    function url($path = '')
    {
        $base = '';
        if (isset($_SERVER['SCRIPT_NAME'])) {
            $dir = str_replace('\\', '/', dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'])));
            if ($dir !== '/' && $dir !== '.') {
                $base = rtrim($dir, '/');
            }
        }
        $path = '/' . ltrim((string)$path, '/');
        return $base . ($path === '/' ? '' : $path);
    }
}

if (!function_exists('asset')) {
    /** Ссылка на статику с версией для сброса кэша. */
    function asset($path)
    {
        $path = ltrim((string)$path, '/');
        $full = PUBLIC_PATH . '/' . $path;
        $version = is_file($full) ? '?v=' . filemtime($full) : '';
        return url('/' . $path) . $version;
    }
}

if (!function_exists('upload_url')) {
    /** Ссылка на загруженный файл. */
    function upload_url($path)
    {
        if ($path === null || $path === '') {
            return '';
        }
        if (strpos((string)$path, 'http') === 0) {
            return $path;
        }
        return url('/uploads/' . ltrim((string)$path, '/'));
    }
}

if (!function_exists('avatar_url')) {
    /** Аватар с заглушкой по первой букве имени. */
    function avatar_url($avatar)
    {
        if (!empty($avatar)) {
            return upload_url('avatars/' . $avatar);
        }
        return url('/assets/img/avatar-default.svg');
    }
}

if (!function_exists('money')) {
    /** Форматирование суммы: 1 250,00 ₽. */
    function money($amount, $withSymbol = true)
    {
        $formatted = number_format((float)$amount, 2, ',', ' ');
        if (!$withSymbol) {
            return $formatted;
        }
        $symbol = config('finance.currency') === 'RUB' ? '₽' : (string)config('finance.currency');
        return $formatted . ' ' . $symbol;
    }
}

if (!function_exists('redirect')) {
    /** Редирект и завершение скрипта. */
    function redirect($path, $code = 302)
    {
        if (strpos((string)$path, 'http') !== 0) {
            $path = url($path);
        }
        if (!headers_sent()) {
            header('Location: ' . $path, true, (int)$code);
        }
        exit;
    }
}

if (!function_exists('old')) {
    /** Старое значение поля после неудачной отправки формы. */
    function old($key, $default = '')
    {
        $data = \App\Core\Session::get('_old', []);
        return isset($data[$key]) ? $data[$key] : $default;
    }
}

if (!function_exists('errors')) {
    /** Список ошибок валидации. */
    function errors()
    {
        return \App\Core\Session::get('_errors', []);
    }
}

if (!function_exists('error')) {
    /** Ошибка по конкретному полю. */
    function error($field)
    {
        $errors = errors();
        return isset($errors[$field]) ? (string)$errors[$field] : '';
    }
}

if (!function_exists('has_error')) {
    /** Есть ли ошибка по полю. */
    function has_error($field)
    {
        $errors = errors();
        return isset($errors[$field]);
    }
}

if (!function_exists('csrf_field')) {
    /** Скрытое поле с CSRF-токеном. */
    function csrf_field()
    {
        return \App\Core\CSRF::field();
    }
}

if (!function_exists('csrf_token')) {
    /** Текущий CSRF-токен. */
    function csrf_token()
    {
        return \App\Core\CSRF::token();
    }
}

if (!function_exists('auth')) {
    /** Текущий пользователь или null. */
    function auth()
    {
        return \App\Core\Auth::user();
    }
}

if (!function_exists('is_logged_in')) {
    /** Авторизован ли пользователь. */
    function is_logged_in()
    {
        return \App\Core\Auth::check();
    }
}

if (!function_exists('is_admin')) {
    /** Администратор ли текущий пользователь. */
    function is_admin()
    {
        $user = auth();
        return $user !== null && $user['role'] === 'admin';
    }
}

if (!function_exists('is_client')) {
    /** Клиент ли текущий пользователь. */
    function is_client()
    {
        $user = auth();
        return $user !== null && $user['role'] === 'client';
    }
}

if (!function_exists('is_freelancer')) {
    /** Фрилансер ли текущий пользователь. */
    function is_freelancer()
    {
        $user = auth();
        return $user !== null && $user['role'] === 'freelancer';
    }
}

if (!function_exists('truncate')) {
    /** Обрезка текста до длины с многоточием. */
    function truncate($text, $length = 120)
    {
        $text = trim(strip_tags((string)$text));
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . '…';
    }
}

if (!function_exists('time_ago')) {
    /** Человеческое «5 минут назад». */
    function time_ago($datetime)
    {
        if (empty($datetime)) {
            return '';
        }
        $timestamp = is_numeric($datetime) ? (int)$datetime : strtotime((string)$datetime);
        $diff = time() - $timestamp;

        if ($diff < 0) {
            return date('d.m.Y H:i', $timestamp);
        }
        if ($diff < 60) {
            return 'только что';
        }
        if ($diff < 3600) {
            $m = (int)floor($diff / 60);
            return $m . ' ' . plural($m, 'минуту', 'минуты', 'минут') . ' назад';
        }
        if ($diff < 86400) {
            $h = (int)floor($diff / 3600);
            return $h . ' ' . plural($h, 'час', 'часа', 'часов') . ' назад';
        }
        if ($diff < 2592000) {
            $d = (int)floor($diff / 86400);
            return $d . ' ' . plural($d, 'день', 'дня', 'дней') . ' назад';
        }
        return date('d.m.Y', $timestamp);
    }
}

if (!function_exists('plural')) {
    /** Русский плюрал: 1 товар / 2 товара / 5 товаров. */
    function plural($number, $one, $few, $many)
    {
        $number = abs((int)$number) % 100;
        $last = $number % 10;

        if ($number > 10 && $number < 20) {
            return $many;
        }
        if ($last > 1 && $last < 5) {
            return $few;
        }
        if ($last === 1) {
            return $one;
        }
        return $many;
    }
}

if (!function_exists('status_label')) {
    /** Человеческая подпись статуса. */
    function status_label($status)
    {
        $map = [
            'active'      => 'Активен',
            'blocked'     => 'Заблокирован',
            'pending'     => 'Ожидает',
            'inactive'    => 'Неактивна',
            'open'        => 'Открыт',
            'in_progress' => 'В работе',
            'completed'   => 'Завершён',
            'cancelled'   => 'Отменён',
            'disputed'    => 'Спор',
            'paused'      => 'На паузе',
            'deleted'     => 'Удалена',
            'accepted'    => 'Принята',
            'rejected'    => 'Отклонена',
            'resolved'    => 'Решён',
            'failed'      => 'Ошибка',
        ];
        return isset($map[$status]) ? $map[$status] : (string)$status;
    }
}

if (!function_exists('status_class')) {
    /** CSS-класс бейджа по статусу. */
    function status_class($status)
    {
        $success = ['active', 'completed', 'accepted', 'resolved'];
        $danger = ['blocked', 'cancelled', 'rejected', 'failed'];
        $warning = ['pending', 'paused', 'disputed', 'open'];
        $info = ['in_progress', 'inactive'];

        if (in_array($status, $success, true)) {
            return 'badge badge-success';
        }
        if (in_array($status, $danger, true)) {
            return 'badge badge-danger';
        }
        if (in_array($status, $warning, true)) {
            return 'badge badge-warning';
        }
        if (in_array($status, $info, true)) {
            return 'badge badge-info';
        }
        return 'badge';
    }
}

if (!function_exists('tx_type_label')) {
    /** Подпись типа транзакции. */
    function tx_type_label($type)
    {
        $map = [
            'deposit'    => 'Пополнение',
            'withdrawal' => 'Вывод',
            'payment'    => 'Оплата',
            'commission' => 'Комиссия',
            'refund'     => 'Возврат',
        ];
        return isset($map[$type]) ? $map[$type] : (string)$type;
    }
}

if (!function_exists('role_label')) {
    /** Подпись роли. */
    function role_label($role)
    {
        $map = [
            'admin'      => 'Администратор',
            'client'     => 'Заказчик',
            'freelancer' => 'Фрилансер',
        ];
        return isset($map[$role]) ? $map[$role] : (string)$role;
    }
}

if (!function_exists('json_decode_safe')) {
    /** Безопасное декодирование JSON (в БД храним TEXT). */
    function json_decode_safe($json, $default = [])
    {
        if (empty($json)) {
            return $default;
        }
        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : $default;
    }
}
