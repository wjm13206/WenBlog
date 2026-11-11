<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Small regex router with middleware flags per route.
 */
final class Router
{
    /** @var array<string, list<array{pattern: string, params: list<string>, handler: array{0: class-string, 1: string}, options: array<string, mixed>}>> */
    private array $routes = [];

    public function __construct(private readonly App $app)
    {
    }

    /** @param array{0: class-string, 1: string} $handler */
    public function get(string $path, array $handler, array $options = []): void
    {
        $this->add('GET', $path, $handler, $options);
    }

    /** @param array{0: class-string, 1: string} $handler */
    public function post(string $path, array $handler, array $options = []): void
    {
        $this->add('POST', $path, $handler, $options);
    }

    /** @param array{0: class-string, 1: string} $handler */
    private function add(string $method, string $path, array $handler, array $options): void
    {
        $path = '/' . trim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        $params = [];
        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $m) use (&$params): string {
            $params[] = $m[1];

            return '([^/]+)';
        }, $path);
        $pattern = is_string($pattern) ? $pattern : $path;

        $this->routes[$method][] = [
            'pattern' => '#^' . $pattern . '$#u',
            'params'  => $params,
            'handler' => $handler,
            'options' => $options,
        ];
    }

    public function dispatch(): void
    {
        $path = $this->app->request->path();
        $method = $this->app->request->method();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $path, $matches) === 1) {
                array_shift($matches);
                $params = [];
                foreach ($route['params'] as $index => $name) {
                    $params[$name] = rawurldecode((string) ($matches[$index] ?? ''));
                }
                $this->run($route, $params);

                return;
            }
        }

        foreach (['GET', 'POST'] as $otherMethod) {
            if ($otherMethod === $method) {
                continue;
            }
            foreach ($this->routes[$otherMethod] ?? [] as $route) {
                if (preg_match($route['pattern'], $path) === 1) {
                    if (str_starts_with($path, '/api')) {
                        Response::json(['error' => 'method_not_allowed', 'message' => '该地址不支持此请求方法。'], 405);
                    }
                    Response::error(405, '该地址不支持此请求方法。');
                }
            }
        }

        $this->notFound();
    }

    /** @param array{handler: array{0: class-string, 1: string}, options: array<string, mixed>} $route */
    private function run(array $route, array $params): void
    {
        $options = $route['options'];

        if (($options['auth'] ?? false) === true && !$this->app->auth->check()) {
            if ($this->app->request->wantsJson() || ($options['api'] ?? false)) {
                Response::json(['error' => 'unauthorized', 'message' => '需要登录。'], 401);
            }
            $next = $this->app->request->path();
            Response::redirect(admin_url('login?next=' . rawurlencode($next)));
        }

        if (($options['admin'] ?? false) === true && !$this->app->auth->isAdmin()) {
            if ($this->app->request->wantsJson()) {
                Response::json(['error' => 'forbidden', 'message' => '需要管理员权限。'], 403);
            }
            Response::error(403, '该操作需要管理员权限。', true);
        }

        if (($options['api'] ?? false) === true) {
            $this->checkApi();
        }

        if ($this->app->request->isPost() && ($options['csrf'] ?? true) === true) {
            $token = $this->app->request->post('_token');
            if (!$this->app->security->verifyCsrf(is_string($token) ? $token : null)) {
                Response::error(419, '表单已过期，请返回刷新后重试。');
            }
        }

        [$class, $methodName] = $route['handler'];
        if (!class_exists($class) || !method_exists($class, $methodName)) {
            throw new \RuntimeException('Route handler not found: ' . $class . '::' . $methodName);
        }

        try {
            $controller = new $class($this->app);
            $controller->{$methodName}(...array_values($params));
        } catch (Throwable $e) {
            Logger::error('Unhandled request error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'path' => $this->app->request->path(),
            ]);
            if (($options['api'] ?? false) === true || str_starts_with($this->app->request->path(), '/api')) {
                Response::json(['error' => 'server_error', 'message' => '服务器内部错误。'], 500);
            }
            if ((bool) $this->app->config('app.debug', false)) {
                Response::error(500, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            }
            Response::error(500);
        }
    }

    private function checkApi(): void
    {
        if ((string) $this->app->options->get('api_enabled', '0') !== '1') {
            Response::json(['error' => 'not_found', 'message' => 'API 未启用。'], 404);
        }

        $key = (string) $this->app->options->get('api_key', '');
        $provided = $this->app->request->header('X-API-Key');
        if ($provided === '') {
            $queryKey = $this->app->request->query('api_key');
            $provided = is_string($queryKey) ? $queryKey : '';
        }
        if ($key === '' || $provided === '' || !hash_equals($key, $provided)) {
            Response::json(['error' => 'unauthorized', 'message' => 'API 密钥无效。'], 401);
        }
    }

    private function notFound(): never
    {
        http_response_code(404);
        if (str_starts_with($this->app->request->path(), '/admin')) {
            Response::error(404, '你访问的后台页面不存在。', true);
        }
        if (str_starts_with($this->app->request->path(), '/api')) {
            Response::json(['error' => 'not_found', 'message' => '接口不存在。'], 404);
        }
        try {
            echo $this->app->theme->render('404', ['pageTitle' => '页面不存在', 'noIndex' => true]);
        } catch (Throwable) {
            Response::error(404);
        }
        exit;
    }
}