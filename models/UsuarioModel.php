<?php

class UsuarioModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // ------------------------------------------------------------------
    // Búsqueda por email (para login)
    // ------------------------------------------------------------------
    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.password, u.rol_id, u.deleted_at,
                   p.nombre, p.apellido, p.id AS persona_id
            FROM   usuarios u
            JOIN   personas p ON p.id = u.persona_id
            WHERE  u.email = :email
            LIMIT  1
        ");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ------------------------------------------------------------------
    // Listar usuarios activos (con datos de persona y rol)
    // ------------------------------------------------------------------
    public function getAll(): array {
        $stmt = $this->db->query("
            SELECT u.id, u.email, u.rol_id, r.nombre AS rol,
                   p.id AS persona_id, p.nombre, p.apellido,
                   u.created_at, u.updated_at, u.deleted_at
            FROM   usuarios u
            JOIN   personas p ON p.id  = u.persona_id
            JOIN   roles    r ON r.id  = u.rol_id
            ORDER  BY u.id DESC
        ");
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // Obtener uno por id
    // ------------------------------------------------------------------
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.rol_id, r.nombre AS rol,
                   p.id AS persona_id, p.nombre, p.apellido,
                   u.created_at, u.updated_at
            FROM   usuarios u
            JOIN   personas p ON p.id = u.persona_id
            JOIN   roles    r ON r.id = u.rol_id
            WHERE  u.id = :id
              AND  u.deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ------------------------------------------------------------------
    // Crear persona + usuario en una transacción
    // ------------------------------------------------------------------
    public function create(array $data): int {
        $this->db->beginTransaction();
        try {
            // 1. Insertar persona
            $stmt = $this->db->prepare("
                INSERT INTO personas (nombre, apellido) VALUES (:nombre, :apellido)
            ");
            $stmt->execute([
                'nombre'   => $data['nombre'],
                'apellido' => $data['apellido'],
            ]);
            $personaId = (int)$this->db->lastInsertId();

            // 2. Insertar usuario
            $stmt = $this->db->prepare("
                INSERT INTO usuarios (persona_id, rol_id, email, password)
                VALUES (:persona_id, :rol_id, :email, :password)
            ");
            $stmt->execute([
                'persona_id' => $personaId,
                'rol_id'     => $data['rol_id'],
                'email'      => $data['email'],
                'password'   => password_hash($data['password'], PASSWORD_BCRYPT),
            ]);
            $usuarioId = (int)$this->db->lastInsertId();

            // 3. Disparar la variable de sesión para el trigger de bitácora
            $this->db->exec("SET @usuario_id_app = $usuarioId");

            $this->db->commit();
            return $usuarioId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Actualizar email / rol
    // ------------------------------------------------------------------
    public function update(int $id, array $data, int $editorId): bool {
        $this->db->exec("SET @usuario_id_app = $editorId");

        $fields = [];
        $params = ['id' => $id];

        if (isset($data['email'])) {
            $fields[] = 'email = :email';
            $params['email'] = $data['email'];
        }
        if (isset($data['rol_id'])) {
            $fields[] = 'rol_id = :rol_id';
            $params['rol_id'] = (int)$data['rol_id'];
        }
        if (isset($data['password'])) {
            $fields[] = 'password = :password';
            $params['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        // Actualizar persona si viene nombre/apellido
        if (isset($data['nombre']) || isset($data['apellido'])) {
            $usuario = $this->findById($id);
            if ($usuario) {
                $pFields = [];
                $pParams = ['persona_id' => $usuario['persona_id']];
                if (isset($data['nombre'])) {
                    $pFields[] = 'nombre = :nombre';
                    $pParams['nombre'] = $data['nombre'];
                }
                if (isset($data['apellido'])) {
                    $pFields[] = 'apellido = :apellido';
                    $pParams['apellido'] = $data['apellido'];
                }
                $pStmt = $this->db->prepare(
                    "UPDATE personas SET " . implode(', ', $pFields) . " WHERE id = :persona_id"
                );
                $pStmt->execute($pParams);
            }
        }

        if (empty($fields)) return false;

        $stmt = $this->db->prepare(
            "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------------
    // Soft delete
    // ------------------------------------------------------------------
    public function softDelete(int $id, int $editorId): bool {
        $this->db->exec("SET @usuario_id_app = $editorId");

        // Obtener el persona_id primero para evitar el error 1442 de MySQL con el trigger
        $stmtGet = $this->db->prepare("SELECT persona_id FROM usuarios WHERE id = :id AND deleted_at IS NULL");
        $stmtGet->execute(['id' => $id]);
        $personaId = $stmtGet->fetchColumn();

        if (!$personaId) return false;

        // Actualizar personas (esto disparará el trigger que actualiza usuarios)
        $stmtUpdate = $this->db->prepare("UPDATE personas SET deleted_at = NOW() WHERE id = :pid");
        $stmtUpdate->execute(['pid' => $personaId]);

        return $stmtUpdate->rowCount() > 0;
    }

    // ------------------------------------------------------------------
    // Restaurar
    // ------------------------------------------------------------------
    public function restore(int $id, int $editorId): bool {
        $this->db->exec("SET @usuario_id_app = $editorId");

        // Obtener el persona_id primero
        $stmtGet = $this->db->prepare("SELECT persona_id FROM usuarios WHERE id = :id AND deleted_at IS NOT NULL");
        $stmtGet->execute(['id' => $id]);
        $personaId = $stmtGet->fetchColumn();

        if (!$personaId) return false;

        // Actualizar personas (esto disparará el trigger)
        $stmtUpdate = $this->db->prepare("UPDATE personas SET deleted_at = NULL WHERE id = :pid");
        $stmtUpdate->execute(['pid' => $personaId]);

        return $stmtUpdate->rowCount() > 0;
    }
}
