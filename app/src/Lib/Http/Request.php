<?php

namespace App\Lib\Http;

class Request
{
    private readonly string $uri;
    private readonly string $method;
    private readonly array $headers;
    private array $routeParameters = [];

    public function __construct()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $this->uri = rtrim($uri, '/') ?: '/';
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
    }

    public function getUri(): string { return $this->uri; }

    public function getMethod(): string { return $this->method; }

    public function getHeaders(): array { return $this->headers; }

    public function getHeader(string $name): ?string
    {
        $name = strtolower($name);
        foreach ($this->headers as $headerName => $value) {
            if (strtolower((string) $headerName) === $name) {
                return is_array($value) ? implode(', ', $value) : (string) $value;
            }
        }
        return null;
    }

    public function getJsonBody(): array
    {
        $content = file_get_contents('php://input');
        if ($content === false || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON body');
        }

        return $decoded;
    }

    public function setRouteParameters(array $parameters): void
    {
        $this->routeParameters = $parameters;
    }

    public function getRouteParameter(string $name): ?string
    {
        return $this->routeParameters[$name] ?? null;
    }
}
