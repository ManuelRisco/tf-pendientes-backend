<?php

class DashboardModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Estadísticas generales — para administradores ve todo,
     * para empleados solo sus propias tareas.
     */
    public function getStats(?int $usuarioId = null): array {
        $whereUser = $usuarioId !== null ? 'AND t.usuario_id = :uid' : '';
        $params    = $usuarioId !== null ? ['uid' => $usuarioId] : [];

        // Total de tareas activas
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM   tareas t
            WHERE  t.deleted_at IS NULL $whereUser
        ");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Por estado
        $stmt = $this->db->prepare("
            SELECT  e.nombre, COUNT(t.id) AS cantidad
            FROM    estados e
            LEFT JOIN tareas t ON t.estado_id = e.id
                               AND t.deleted_at IS NULL
                               $whereUser
            GROUP BY e.id, e.nombre
            ORDER BY e.id
        ");
        $stmt->execute($params);
        $porEstado = $stmt->fetchAll();

        // Por prioridad
        $stmt = $this->db->prepare("
            SELECT  p.nombre, COUNT(t.id) AS cantidad
            FROM    prioridades p
            LEFT JOIN tareas t ON t.prioridad_id = p.id
                               AND t.deleted_at IS NULL
                               $whereUser
            GROUP BY p.id, p.nombre
            ORDER BY p.id
        ");
        $stmt->execute($params);
        $porPrioridad = $stmt->fetchAll();

        return [
            'total'        => $total,
            'porEstado'    => $porEstado,
            'porPrioridad' => $porPrioridad,
        ];
    }

    /**
     * Solo para admins: número de usuarios activos
     */
    public function getTotalUsuarios(): int {
        return (int)$this->db->query("
            SELECT COUNT(*) FROM usuarios WHERE deleted_at IS NULL
        ")->fetchColumn();
    }

    /**
     * Solo para admins: obtener los últimos movimientos de la bitácora
     */
    public function getMovimientos(int $limite = 50): array {
        $stmt = $this->db->prepare("
            SELECT 
                b.id,
                b.modulo,
                b.registro_id,
                b.detalles,
                b.created_at,
                ta.nombre AS tipo_accion,
                p.nombre AS persona_nombre,
                p.apellido AS persona_apellido,
                u.email
            FROM bitacora b
            INNER JOIN tipos_acciones ta ON b.tipo_accion_id = ta.id
            LEFT JOIN usuarios u ON b.usuario_id = u.id
            LEFT JOIN personas p ON u.persona_id = p.id
            ORDER BY b.created_at DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodificar JSON de detalles para que React pueda usarlo fácilmente
        foreach ($movimientos as &$mov) {
            if (!empty($mov['detalles'])) {
                $mov['detalles'] = json_decode($mov['detalles'], true);
            }
        }
        
        return $movimientos;
    }
}
