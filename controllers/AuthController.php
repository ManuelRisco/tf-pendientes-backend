<?php

class AuthController {
    private UsuarioModel $model;

    public function __construct() {
        $this->model = new UsuarioModel();
    }

    // POST /api/auth/login
    public function login(): void {
        $body = $this->json();

        $email    = trim($body['email']    ?? '');
        $password = trim($body['password'] ?? '');
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!$email || !$password) {
            Response::error('Email y contraseña son requeridos.', 422);
        }

        $db   = Database::getConnection();
        $user = $this->model->findByEmail($email);

        $exitoso = 0;

        if ($user && is_null($user['deleted_at']) && password_verify($password, $user['password'])) {
            $exitoso = 1;

            // Registrar log de acceso exitoso
            $this->registrarLog($db, $user['id'], $email, $ip, 1);

            $token = JwtHelper::generate([
                'id'     => $user['id'],
                'email'  => $user['email'],
                'rol_id' => $user['rol_id'],
            ]);

            Response::success([
                'token' => $token,
                'user'  => [
                    'id'       => $user['id'],
                    'email'    => $user['email'],
                    'nombre'   => $user['nombre'],
                    'apellido' => $user['apellido'],
                    'rol_id'   => $user['rol_id'],
                ],
            ], 'Login exitoso');
        } else {
            // Log de acceso fallido
            $this->registrarLog($db, $user['id'] ?? null, $email, $ip, 0);
            Response::unauthorized('Credenciales incorrectas o cuenta inactiva.');
        }
    }

    // POST /api/auth/me — devuelve datos del usuario autenticado
    public function me(): void {
        $payload = AuthMiddleware::require();
        $user    = $this->model->findById((int)$payload['id']);

        if (!$user) {
            Response::notFound('Usuario no encontrado.');
        }

        Response::success($user);
    }

    // -----------------------------------------------------------------------
    private function json(): array {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }

    private function registrarLog(PDO $db, ?int $userId, string $email, string $ip, int $exitoso): void {
        try {
            $stmt = $db->prepare("
                INSERT INTO logs_acceso (usuario_id, email_ingresado, direccion_ip, exitoso)
                VALUES (:uid, :email, :ip, :exitoso)
            ");
            $stmt->execute([
                'uid'     => $userId,
                'email'   => $email,
                'ip'      => $ip,
                'exitoso' => $exitoso,
            ]);
        } catch (Throwable) {
            // No romper el flujo principal si falla el log
        }
    }
}
