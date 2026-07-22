<?php

class CatalogoController {
    private CatalogoModel $model;

    public function __construct() {
        $this->model = new CatalogoModel();
    }

    // GET /api/catalogos — devuelve todos los catálogos de una vez
    public function index(): void {
        AuthMiddleware::require();
        Response::success([
            'roles'       => $this->model->getRoles(),
            'estados'     => $this->model->getEstados(),
            'prioridades' => $this->model->getPrioridades(),
        ]);
    }
}
