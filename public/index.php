<?php declare(strict_types=1);

/**
 * Единая точка входа приложения (front controller).
 * PHP 7.4 — без синтаксиса PHP 8.
 */

// ---------------------------------------------------------------------
// Базовые константы и пути
// ---------------------------------------------------------------------
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');

// ---------------------------------------------------------------------
// Хелперы (загружаются до автозагрузчика — это обычные функции)
// ---------------------------------------------------------------------
require APP_PATH . '/Core/Helpers.php';

/** @var array $config */
$config = config();

// ---------------------------------------------------------------------
// Настройки ошибок и окружения
// ---------------------------------------------------------------------
error_reporting(E_ALL);

if (!empty($config['app']['debug'])) {
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $config['paths']['logs'] . '/error.log');
}

date_default_timezone_set($config['app']['timezone']);
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'ru_RU.UTF-8');

// ---------------------------------------------------------------------
// Автозагрузчик классов вида App\Core\Router -> app/Core/Router.php
// ---------------------------------------------------------------------
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $length = strlen($prefix);

    if (strncmp($class, $prefix, $length) !== 0) {
        return;
    }

    $relative = substr($class, $length);
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// ---------------------------------------------------------------------
// Инициализация ядра
// ---------------------------------------------------------------------
\App\Core\Logger::init($config['paths']['logs']);
\App\Core\Database::init($config['db']);
\App\Core\Session::start($config['session']);
\App\Core\CSRF::init();
\App\Core\Auth::init($config);

// ---------------------------------------------------------------------
// Маршрутизация
// ---------------------------------------------------------------------
$router = new \App\Core\Router();

// Базовый путь (если проект лежит в подпапке)
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/index.php';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
$basePath = ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');
$router->setBasePath($basePath);

require APP_PATH . '/routes.php';

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

try {
    $router->dispatch($method, $uri);
} catch (\Throwable $e) {
    \App\Core\Logger::error('Необработанное исключение: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    if (!empty($config['app']['debug'])) {
        echo '<pre>' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        http_response_code(500);
        echo 'Внутренняя ошибка сервера. Попробуйте позже.';
    }
}
