<?php
declare(strict_types=1);

namespace App\Config;

use App\Middlewares\AuthMiddleware;

class Router {
    private array $routes = [];

    public function get(string $path, array $handler, bool $protected = false): void {
        $this->addRoute('GET', $path, $handler, $protected);
    }

    public function post(string $path, array $handler, bool $protected = false): void {
        $this->addRoute('POST', $path, $handler, $protected);
    }

    public function put(string $path, array $handler, bool $protected = false): void {
        $this->addRoute('PUT', $path, $handler, $protected);
    }

    public function delete(string $path, array $handler, bool $protected = false): void {
        $this->addRoute('DELETE', $path, $handler, $protected);
    }

    private function addRoute(string $method, string $path, array $handler, bool $protected): void {
        $regexPath = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $path);
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => "#^" . $regexPath . "$#",
            'handler' => $handler,
            'protected' => $protected
        ];
    }

    public function dispatch(string $requestUri, string $requestMethod): void {
        $uri = parse_url($requestUri, PHP_URL_PATH);
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        
        if ($scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }
        $uri = '/' . ltrim($uri, '/');
        $method = strtoupper($requestMethod);

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $uri, $matches)) {
                
                if ($route['protected']) {
                    AuthMiddleware::authenticate();
                }

                array_shift($matches);
                [$class, $func] = $route['handler'];
                
                $controller = new $class();
                call_user_func_array([$controller, $func], $matches);
                return;
            }
        }

        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Endpoint not found.']);
    }
}