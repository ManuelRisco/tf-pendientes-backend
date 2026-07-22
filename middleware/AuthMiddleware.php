<?php

/**
 * Middleware de autenticación JWT.
 * Uso: $payload = AuthMiddleware::require();
 * El payload contiene: id, email, rol_id
 */
class AuthMiddleware {

    /**
     * Verifica el token. Si falla, responde 401 y termina.
     */
    public static function require(): array {
        try {
            $payload = JwtHelper::fromRequest();

            // Verificar en la BD si el usuario sigue activo o si sus credenciales cambiaron
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT deleted_at, updated_at FROM usuarios WHERE id = ?");
            $stmt->execute([(int)$payload['id']]);
            $user = $stmt->fetch();

            if (!$user || $user['deleted_at'] !== null) {
                Response::unauthorized('Tu usuario ha sido desactivado del sistema.');
                exit;
            }

            // Si updated_at es mayor al iat (fecha de emisión del token), significa
            // que al usuario le modificaron datos (correo, password, rol, etc)
            $updatedAt = strtotime($user['updated_at']);
            // Damos un margen de 5 segundos por diferencias de reloj DB/PHP
            if ($updatedAt > ($payload['iat'] + 5)) {
                Response::unauthorized('Tus datos de acceso han sido modificados. Por favor inicia sesión nuevamente.');
                exit;
            }

            return $payload;
        } catch (RuntimeException $e) {
            Response::unauthorized($e->getMessage());
            exit; // Response::unauthorized ya llama exit, pero por si acaso
        }
    }

    /**
     * Verifica el token Y que el usuario tenga rol Administrador (rol_id = 1).
     */
    public static function requireAdmin(): array {
        $payload = self::require();
        if ((int)($payload['rol_id'] ?? 0) !== 1) {
            Response::forbidden('Solo los administradores pueden realizar esta acción.');
        }
        return $payload;
    }
}
