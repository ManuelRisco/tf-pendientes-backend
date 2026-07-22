<?php

class Response {

    public static function json(mixed $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success($data = null, string $message = 'Operación exitosa', int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ]);
        if ($json === false) {
            echo json_encode([
                'success' => false,
                'message' => 'JSON encode error',
                'error' => json_last_error_msg(),
                'data' => null
            ]);
        } else {
            echo $json;
        }
        exit;
    }

    public static function error(string $message = 'Error', int $status = 400, mixed $errors = null): void {
        $payload = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        self::json($payload, $status);
    }

    public static function notFound(string $message = 'Recurso no encontrado'): void {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'No autorizado'): void {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Acceso denegado'): void {
        self::error($message, 403);
    }
}
