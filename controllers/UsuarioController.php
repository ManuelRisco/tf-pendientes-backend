<?php

class UsuarioController {
    private UsuarioModel $model;

    public function __construct() {
        $this->model = new UsuarioModel();
    }

    // GET /api/usuarios
    public function index(): void {
        AuthMiddleware::requireAdmin();
        Response::success($this->model->getAll());
    }

    // GET /api/usuarios/:id
    public function show(array $params): void {
        $auth = AuthMiddleware::require();
        $id   = (int)$params['id'];

        // Un empleado solo puede ver su propio perfil
        if ((int)$auth['rol_id'] !== 1 && $auth['id'] !== $id) {
            Response::forbidden();
        }

        $user = $this->model->findById($id);
        if (!$user) Response::notFound('Usuario no encontrado.');

        Response::success($user);
    }

    // POST /api/usuarios  — solo admin puede crear usuarios
    public function store(): void {
        $auth = AuthMiddleware::requireAdmin();
        $body = $this->json();

        $errors = $this->validate($body, true);
        if ($errors) Response::error('Datos inválidos.', 422, $errors);

        // Validar si el email ya existe
        $existingUser = $this->model->findByEmail($body['email']);
        if ($existingUser) {
            Response::error('El email ya está registrado. Por favor, usa otro.', 409);
        }

        try {
            $id = $this->model->create($body);
            Response::success(['id' => $id], 'Usuario creado correctamente.', 201);
        } catch (PDOException $e) {
            throw $e;
        }
    }

    // PUT /api/usuarios/:id
    public function update(array $params): void {
        $auth = AuthMiddleware::require();
        $id   = (int)$params['id'];
        $body = $this->json();

        // Solo admin puede cambiar el rol
        if (isset($body['rol_id']) && (int)$auth['rol_id'] !== 1) {
            Response::forbidden('No puedes cambiar el rol.');
        }

        // Empleado solo puede editar su propio perfil
        if ((int)$auth['rol_id'] !== 1 && $auth['id'] !== $id) {
            Response::forbidden();
        }

        $errors = $this->validate($body, false);
        if ($errors) Response::error('Datos inválidos.', 422, $errors);

        $ok = $this->model->update($id, $body, (int)$auth['id']);
        if (!$ok) Response::notFound('Usuario no encontrado o sin cambios.');

        Response::success(null, 'Usuario actualizado.');
    }

    // DELETE /api/usuarios/:id
    public function destroy(array $params): void {
        $auth = AuthMiddleware::requireAdmin();
        $id = (int)$params['id'];

        if ($id === (int)$auth['id']) {
            Response::error('No puedes desactivarte a ti mismo.', 400);
        }

        $usuario = $this->model->findById($id);

        if (!$usuario) Response::notFound('Usuario no encontrado.');

        $ok = $this->model->softDelete($id, (int)$auth['id']);
        if (!$ok) Response::error('No se pudo desactivar el usuario.', 400);

        Response::success(null, 'Usuario desactivado exitosamente.');
    }

    // PATCH /api/usuarios/:id/restaurar — solo admin
    public function restore(array $params): void {
        $auth = AuthMiddleware::requireAdmin();
        $id   = (int)$params['id'];

        $ok = $this->model->restore($id, (int)$auth['id']);
        if (!$ok) Response::error('Usuario no encontrado o ya está activo.', 400);

        Response::success(null, 'Usuario restaurado.');
    }

    // -----------------------------------------------------------------------
    private function json(): array {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    private function validate(array $data, bool $isCreate): array {
        $errors = [];
        if ($isCreate) {
            if (empty($data['nombre']))   $errors['nombre']   = 'Requerido.';
            if (empty($data['apellido'])) $errors['apellido'] = 'Requerido.';
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
                $errors['email'] = 'Email inválido.';
            if (empty($data['password']) || strlen($data['password']) < 6)
                $errors['password'] = 'Mínimo 6 caracteres.';
            if (empty($data['rol_id']))   $errors['rol_id']   = 'Requerido.';
        } else {
            if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
                $errors['email'] = 'Email inválido.';
            if (isset($data['password']) && strlen($data['password']) < 6)
                $errors['password'] = 'Mínimo 6 caracteres.';
        }
        return $errors;
    }
}
