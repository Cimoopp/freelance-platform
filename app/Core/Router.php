<?php declare(strict_types=1);

namespace App\Core;

/**
 * Лёгкий роутер: маршруты, группы, middleware, параметры {id}.
 * PHP 7.4.
 */
class Router
{
    /** @var array */
    private $routes = [];

    /** @var string */
    private $groupPrefix = '';

    /** @var array */
    private $groupMiddleware = [];

    /** @var string */
    private $basePath = '';

    /**
     * Базовый путь (если проект в подпапке).
     */
    public function setBasePath(string $path): void
    {
        $this->basePath = rtrim($path, '/');
    }

    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * Регистрация маршрута.
     *
     * @param string $path  например /services/{id}
     * @param array|callable|string $handler [Controller::class, 'method'] | 'Controller@method' | callable
     */
    public function add(string $method, string $path, $handler, array $middleware = []): void
    {
        $full = $this->groupPrefix . $path;
        if ($full === '') {
            $full = '/';
        }

        // {id} -> именованная группа
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $full);
        $pattern = rtrim($pattern, '/');
        if ($pattern === '') {
            $pattern = '/';
        }

        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => '#^' . $pattern . '$#u',
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
            'path'       => $full,
        ];
    }

    /**
     * Группа маршрутов с общим префиксом и middleware.
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = $previousPrefix . $prefix;
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Запуск: находит маршрут и вызывает контроллер.
     */
    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = $path === null ? '/' : rawurldecode($path);

        // Отрезаем базовый путь
        if ($this->basePath !== '' && strpos($path, $this->basePath) === 0) {
            $path = substr($path, strlen($this->basePath));
        }

        $path = '/' . ltrim($path, '/');
        if (mb_strlen($path) > 1) {
            $path = rtrim($path, '/');
        }
        if ($path === '') {
            $path = '/';
        }

        $method = strtoupper($method);
        $matchedPath = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }

            $matchedPath = true;

            if ($route['method'] !== $method) {
                continue;
            }

            // Middleware
            foreach ($route['middleware'] as $middleware) {
                if ($this->runMiddleware($middleware) === false) {
                    return;
                }
            }

            // CSRF для всех POST (если не отключено)
            if ($method === 'POST' && !in_array('no_csrf', $route['middleware'], true)) {
                CSRF::validateOrFail();
            }

            // Именованные параметры
            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[] = $value;
                }
            }

            $this->call($route['handler'], $params);
            return;
        }

        // Путь существует с другим методом -> 405
        if ($matchedPath) {
            http_response_code(405);
            echo 'Метод не поддерживается.';
            return;
        }

        $this->notFound();
    }

    /**
     * Вызов контроллера с параметрами.
     */
    private function call($handler, array $params): void
    {
        if (is_callable($handler) && !is_string($handler)) {
            call_user_func_array($handler, $params);
            return;
        }

        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($class, $method) = explode('@', $handler, 2);
            $controller = new $class();
            call_user_func_array([$controller, $method], $params);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            $controller = new $handler[0]();
            call_user_func_array([$controller, $handler[1]], $params);
            return;
        }

        throw new \RuntimeException('Некорректный обработчик маршрута.');
    }

    /**
     * Проверка middleware.
     *
     * @return bool false — прерывает выполнение
     */
    private function runMiddleware(string $name): bool
    {
        switch ($name) {
            case 'no_csrf':
                return true;

            case 'auth':
                if (!Auth::check()) {
                    Session::flash('error', 'Войдите, чтобы продолжить.');
                    Session::set('_intended', $_SERVER['REQUEST_URI']);
                    redirect('/login');
                    return false;
                }
                return true;

            case 'guest':
                if (Auth::check()) {
                    redirect('/');
                    return false;
                }
                return true;

            case 'admin':
                if (!Auth::check()) {
                    Session::flash('error', 'Войдите как администратор.');
                    redirect('/login');
                    return false;
                }
                $user = Auth::user();
                if ($user['role'] !== 'admin') {
                    http_response_code(403);
                    View::render('errors/403', [], 'layouts/main');
                    return false;
                }
                return true;

            case 'client':
                $user = Auth::user();
                if ($user === null || $user['role'] !== 'client') {
                    Session::flash('error', 'Действие доступно только заказчикам.');
                    redirect('/');
                    return false;
                }
                return true;

            case 'freelancer':
                $user = Auth::user();
                if ($user === null || $user['role'] !== 'freelancer') {
                    Session::flash('error', 'Действие доступно только фрилансерам.');
                    redirect('/');
                    return false;
                }
                return true;

            default:
                return true;
        }
    }

    /**
     * Страница 404.
     */
    private function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', [], 'layouts/main');
    }

    /**
     * Список зарегистрированных маршрутов (для отладки).
     */
    public function routes(): array
    {
        return $this->routes;
    }
}
