<?php

class MovimientosController {
    private DashboardModel $model;

    public function __construct() {
        $this->model = new DashboardModel();
    }

    // GET /movimientos
    public function index(): void {
        // Solo los administradores pueden ver los movimientos
        AuthMiddleware::requireAdmin();

        // Obtener los últimos 100 movimientos
        $movimientos = $this->model->getMovimientos(100);

        Response::success(['movimientos' => $movimientos]);
    }
}
