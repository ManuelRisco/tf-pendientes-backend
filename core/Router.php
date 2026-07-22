<?php

class Router {
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $this->buildRegex($pattern),
            'handler' => $handler,
        ];
    }

    public function get(string $pattern, callable $handler): void    { $this->add('GET',    $pattern, $handler); }
    public function post(string $pattern, callable $handler): void   { $this->add('POST',   $pattern, $handler); }
    public function put(string $pattern, callable $handler): void    { $this->add('PUT',    $pattern, $handler); }
    public function patch(string $pattern, callable $handler): void  { $this->add('PATCH',  $pattern, $handler); }
    public function delete(string $pattern, callable $handler): void { $this->add('DELETE', $pattern, $handler); }

    private function buildRegex(string $pattern): string {
        // Convierte :param en grupo de captura nombrado
        $regex = preg_replace('/\/:([a-zA-Z_]+)/', '/(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    public function dispatch(): void {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Detecta automáticamente el subdirectorio base desde donde corre index.php
        // Ej: /tf-pendientes/backend/index.php  →  base = /tf-pendientes/backend
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']); // /tf-pendientes/backend
        $scriptDir = rtrim($scriptDir, '/');

        if ($scriptDir !== '' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }
        $uri = $uri ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            if (preg_match($route['pattern'], $uri, $matches)) {
                // Filtra solo grupos nombrados
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                call_user_func($route['handler'], $params);
                return;
            }
        }

        Response::notFound("Ruta no encontrada: $method $uri");
    }
}
