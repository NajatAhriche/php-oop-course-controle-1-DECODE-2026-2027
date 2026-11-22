<?php

namespace App\Lib\Http;

use App\Lib\Controllers\AbstractController;

class Router
{
    private const ROUTE_CONFIG_PATH = __DIR__ . '/../../../config/routes.json';
    private const CONTROLLER_NAMESPACE = 'App\\Controllers\\';

    public function main(Request $request): Response
    {
        $routes = $this->getRoutes();
        $uriFound = false;

        foreach ($routes as $route) {
            $this->validateConfig($route);
            $parameters = $this->matchUri($request->getUri(), (string) $route['uri']);

            if ($parameters === null) {
                continue;
            }

            $uriFound = true;
            if (strtoupper((string) $route['method']) !== $request->getMethod()) {
                continue;
            }

            $request->setRouteParameters($parameters);

            try {
                $controller = $this->getValidControllerInstance((string) $route['controller']);
                return $controller->process($request);
            } catch (\Throwable $exception) {
                $response = new Response();
                $response->statusCode = 500;
                $response->content = json_encode([
                    'error' => 'Internal server error',
                    'message' => $exception->getMessage(),
                ], JSON_UNESCAPED_SLASHES);
                return $response;
            }
        }

        $response = new Response();
        $response->statusCode = $uriFound ? 405 : 404;
        $response->content = json_encode([
            'error' => $uriFound ? 'Method not allowed' : 'Route not found',
        ], JSON_UNESCAPED_SLASHES);
        return $response;
    }

    private function getRoutes(): array
    {
        if (!file_exists(self::ROUTE_CONFIG_PATH)) {
            throw new \RuntimeException('Routes configuration not found');
        }
        return json_decode(file_get_contents(self::ROUTE_CONFIG_PATH), true, 512, JSON_THROW_ON_ERROR);
    }

    private function validateConfig(array $route): void
    {
        if (!isset($route['uri'], $route['method'], $route['controller'])) {
            throw new \RuntimeException('Invalid route configuration');
        }
    }

    private function matchUri(string $uri, string $routeUri): ?array
    {
        $uri = trim($uri, '/');
        $routeUri = trim($routeUri, '/');

        if ($routeUri === '') {
            return $uri === '' ? [] : null;
        }

        $pattern = '#^/';
        $parts = explode('/', $routeUri);

        foreach ($parts as $index => $part) {
            if ($index > 0) {
                $pattern .= '/';
            }

            if (preg_match('/^<([a-zA-Z_][a-zA-Z0-9_]*)>$/', $part, $placeholder)) {
                $pattern .= '(?<' . $placeholder[1] . '>[^/]+)';
            } else {
                $pattern .= preg_quote($part, '#');
            }
        }

        $pattern .= '/?$#';
        if (!preg_match($pattern, '/' . $uri, $matches)) {
            return null;
        }

        $parameters = [];
        foreach ($matches as $name => $value) {
            if (is_string($name)) {
                $parameters[$name] = rawurldecode($value);
            }
        }
        return $parameters;
    }

    private function getValidControllerInstance(string $routeController): AbstractController
    {
        $controllerName = self::CONTROLLER_NAMESPACE . $routeController;
        if (!class_exists($controllerName)) {
            throw new \RuntimeException('Controller ' . $controllerName . ' not found');
        }

        $controllerInstance = new $controllerName();
        if (!is_subclass_of($controllerInstance, AbstractController::class)) {
            throw new \RuntimeException('Invalid controller');
        }
        return $controllerInstance;
    }
}
