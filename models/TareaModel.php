<?php

class TareaModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // ------------------------------------------------------------------
    // Listar tareas con filtros opcionales
    // Admins ven todas; Empleados solo las suyas
    // ------------------------------------------------------------------
    public function getAll(array $filters = [], ?int $usuarioId = null): array {
        $where  = ['t.deleted_at IS NULL'];
        $params = [];

        if ($usuarioId !== null) {
            $where[]          = 't.usuario_id = :uid';
            $params['uid']    = $usuarioId;
        }
        if (!empty($filters['estado_id'])) {
            $where[]               = 't.estado_id = :estado_id';
            $params['estado_id']   = (int)$filters['estado_id'];
        }
        if (!empty($filters['prioridad_id'])) {
            $where[]               = 't.prioridad_id = :prioridad_id';
            $params['prioridad_id'] = (int)$filters['prioridad_id'];
        }
        if (!empty($filters['search'])) {
            $where[]              = '(t.titulo LIKE :search OR t.descripcion LIKE :search)';
            $params['search']     = '%' . $filters['search'] . '%';
        }

        $whereSQL = implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT  t.id, t.titulo, t.descripcion,
                    t.estado_id,    e.nombre AS estado,
                    t.prioridad_id, pr.nombre AS prioridad,
                    t.usuario_id,
                    CONCAT(p.nombre, ' ', p.apellido) AS usuario_nombre,
                    t.created_at, t.updated_at
            FROM    tareas t
            JOIN    estados    e  ON e.id  = t.estado_id
            JOIN    prioridades pr ON pr.id = t.prioridad_id
            JOIN    usuarios   u  ON u.id  = t.usuario_id
            JOIN    personas   p  ON p.id  = u.persona_id
            WHERE   $whereSQL
            ORDER   BY t.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT  t.id, t.titulo, t.descripcion,
                    t.estado_id,    e.nombre AS estado,
                    t.prioridad_id, pr.nombre AS prioridad,
                    t.usuario_id,
                    CONCAT(p.nombre, ' ', p.apellido) AS usuario_nombre,
                    t.created_at, t.updated_at
            FROM    tareas t
            JOIN    estados    e  ON e.id  = t.estado_id
            JOIN    prioridades pr ON pr.id = t.prioridad_id
            JOIN    usuarios   u  ON u.id  = t.usuario_id
            JOIN    personas   p  ON p.id  = u.persona_id
            WHERE   t.id = :id AND t.deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ------------------------------------------------------------------
    public function create(array $data, int $creadorId): int {
        $this->db->exec("SET @usuario_id_app = $creadorId");

        $stmt = $this->db->prepare("
            INSERT INTO tareas (titulo, descripcion, estado_id, prioridad_id, usuario_id)
            VALUES (:titulo, :descripcion, :estado_id, :prioridad_id, :usuario_id)
        ");
        $stmt->execute([
            'titulo'       => $data['titulo'],
            'descripcion'  => $data['descripcion'] ?? null,
            'estado_id'    => $data['estado_id']   ?? 1,
            'prioridad_id' => (int)$data['prioridad_id'],
            'usuario_id'   => (int)$data['usuario_id'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    // ------------------------------------------------------------------
    public function update(int $id, array $data, int $editorId): bool {
        $this->db->exec("SET @usuario_id_app = $editorId");

        $fields = [];
        $params = ['id' => $id];

        $allowed = ['titulo', 'descripcion', 'estado_id', 'prioridad_id', 'usuario_id'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[]       = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $stmt = $this->db->prepare(
            "UPDATE tareas SET " . implode(', ', $fields) . " WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------------
    public function softDelete(int $id, int $editorId): bool {
        $this->db->exec("SET @usuario_id_app = $editorId");
        $stmt = $this->db->prepare(
            "UPDATE tareas SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------------
    public function restore(int $id, int $editorId): bool {
        $this->db->exec("SET @usuario_id_app = $editorId");
        $stmt = $this->db->prepare(
            "UPDATE tareas SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
