<?php
declare(strict_types=1);

// Cargar .env si existe (Para desarrollo local)
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// ==============================================================================
// CORS — permite peticiones desde React (Vite dev server u origen de producción)
// ==============================================================================
$allowedOrigins = [
    'http://localhost:5173',  // Vite dev
    'http://localhost:3000',
];

// Agregamos la URL del frontend en producción (Railway) si existe
$frontendUrl = getenv('FRONTEND_URL');
if ($frontendUrl) {
    $allowedOrigins[] = rtrim($frontendUrl, '/');
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ==============================================================================
// Autoload — carga todas las clases automáticamente por carpeta
// ==============================================================================
spl_autoload_register(function (string $class): void {
    $dirs = [
        __DIR__ . '/config/',
        __DIR__ . '/core/',
        __DIR__ . '/middleware/',
        __DIR__ . '/models/',
        __DIR__ . '/controllers/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ==============================================================================
// Manejo global de excepciones no capturadas
// ==============================================================================
set_exception_handler(function (Throwable $e): void {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor.',
        'error'   => $e->getMessage(), // Quitar en producción
    ]);
});

// ==============================================================================
// Rutas
// ==============================================================================
$router = new Router();

// --- Auth ---
$router->post('/auth/login', fn()      => (new AuthController())->login());
$router->get('/auth/me',     fn()      => (new AuthController())->me());

// --- Dashboard ---
$router->get('/dashboard',   fn()      => (new DashboardController())->index());

// --- Movimientos (Bitácora) ---
$router->get('/movimientos', fn()      => (new MovimientosController())->index());

// --- Catálogos ---
$router->get('/catalogos',   fn()      => (new CatalogoController())->index());

// --- Usuarios ---
$router->get('/usuarios',               fn()      => (new UsuarioController())->index());
$router->get('/usuarios/:id',           fn($p)    => (new UsuarioController())->show($p));
$router->post('/usuarios',              fn()      => (new UsuarioController())->store());
$router->put('/usuarios/:id',           fn($p)    => (new UsuarioController())->update($p));
$router->delete('/usuarios/:id',        fn($p)    => (new UsuarioController())->destroy($p));
$router->patch('/usuarios/:id/restaurar', fn($p)  => (new UsuarioController())->restore($p));

// --- Tareas ---
$router->get('/tareas',                 fn()      => (new TareaController())->index());
$router->get('/tareas/:id',             fn($p)    => (new TareaController())->show($p));
$router->post('/tareas',                fn()      => (new TareaController())->store());
$router->put('/tareas/:id',             fn($p)    => (new TareaController())->update($p));
$router->delete('/tareas/:id',          fn($p)    => (new TareaController())->destroy($p));
$router->patch('/tareas/:id/restaurar', fn($p)    => (new TareaController())->restore($p));

// ==============================================================================
// Despachar
// ==============================================================================
$router->dispatch();
