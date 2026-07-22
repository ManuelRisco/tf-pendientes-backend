<?php

class TareaController {
    private TareaModel $model;

    public function __construct() {
        $this->model = new TareaModel();
    }

    // GET /api/tareas[?estado_id=&prioridad_id=&search=]
    public function index(): void {
        $auth    = AuthMiddleware::require();
        $filters = [
            'estado_id'    => $_GET['estado_id']    ?? null,
            'prioridad_id' => $_GET['prioridad_id'] ?? null,
            'search'       => $_GET['search']       ?? null,
        ];

        // Todos pueden ver todas las tareas
        $usuarioId = null;

        Response::success($this->model->getAll($filters, $usuarioId));
    }

    // GET /api/tareas/:id
    public function show(array $params): void {
        $auth  = AuthMiddleware::require();
        $tarea = $this->model->findById((int)$params['id']);

        if (!$tarea) Response::notFound('Tarea no encontrada.');

        Response::success($tarea);
    }

    // POST /api/tareas
    public function store(): void {
        $auth = AuthMiddleware::require();
        $body = $this->json();

        $errors = $this->validate($body, true);
        if ($errors) Response::error('Datos inválidos.', 422, $errors);

        // Si no se envía usuario_id, asignar al usuario actual
        if (empty($body['usuario_id'])) {
            $body['usuario_id'] = $auth['id'];
        }

        $id = $this->model->create($body, (int)$auth['id']);
        Response::success(['id' => $id], 'Tarea creada.', 201);
    }

    // PUT /api/tareas/:id
    public function update(array $params): void {
        $auth  = AuthMiddleware::require();
        $id    = (int)$params['id'];
        $body  = $this->json();
        $tarea = $this->model->findById($id);

        if (!$tarea) Response::notFound('Tarea no encontrada.');

        $errors = $this->validate($body, false);
        if ($errors) Response::error('Datos inválidos.', 422, $errors);

        $ok = $this->model->update($id, $body, (int)$auth['id']);
        if (!$ok) Response::error('Sin cambios detectados.', 400);

        Response::success(null, 'Tarea actualizada.');
    }

    // DELETE /api/tareas/:id
    public function destroy(array $params): void {
        $auth  = AuthMiddleware::require();
        $id    = (int)$params['id'];
        $tarea = $this->model->findById($id);

        if (!$tarea) Response::notFound('Tarea no encontrada.');

        // Solo los administradores pueden eliminar tareas
        if ((int)$auth['rol_id'] !== 1) {
            Response::forbidden('No tienes permisos para eliminar tareas.');
        }

        $ok = $this->model->softDelete($id, (int)$auth['id']);
        if (!$ok) Response::error('No se pudo eliminar la tarea.', 400);

        Response::success(null, 'Tarea eliminada.');
    }

    // PATCH /api/tareas/:id/restaurar
    public function restore(array $params): void {
        $auth = AuthMiddleware::requireAdmin();
        $id   = (int)$params['id'];

        $ok = $this->model->restore($id, (int)$auth['id']);
        if (!$ok) Response::error('Tarea no encontrada o ya activa.', 400);

        Response::success(null, 'Tarea restaurada.');
    }

    // -----------------------------------------------------------------------
    private function json(): array {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    private function validate(array $data, bool $isCreate): array {
        $errors = [];
        if ($isCreate) {
            if (empty($data['titulo']))       $errors['titulo']       = 'Requerido.';
            if (empty($data['prioridad_id'])) $errors['prioridad_id'] = 'Requerido.';
        }
        if (isset($data['estado_id']) && !in_array((int)$data['estado_id'], [1,2,3,4])) {
            $errors['estado_id'] = 'Estado inválido.';
        }
        return $errors;
    }
}
