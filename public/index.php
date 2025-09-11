<?php
// Mostrar errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar configuración
try {
    require_once '../config/config.php';
} catch (Exception $e) {
    die('Error loading config: ' . $e->getMessage());
}

session_start();

// Headers para CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Autoload de clases
spl_autoload_register(function ($class) {
    $paths = [
        '../app/core/' . $class . '.php',
        '../app/controllers/' . $class . '.php',
        '../app/models/' . $class . '.php'
    ];
    
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Inicializar router
$router = new Router();

// Rutas de la API
$router->get('/api/auth', [AuthController::class, 'login']);
$router->post('/api/auth', [AuthController::class, 'login']);
$router->post('/api/logout', [AuthController::class, 'logout']);

$router->get('/api/audios', [AudioController::class, 'index']);
$router->post('/api/audios', [AudioController::class, 'create']);
$router->put('/api/audios/(\d+)', function($id) {
    $controller = new AudioController();
    $controller->update($id);
});
$router->delete('/api/audios/(\d+)', function($id) {
    $controller = new AudioController();
    $controller->delete($id);
});
$router->patch('/api/audios/category', [AudioController::class, 'updateCategory']);
$router->get('/api/audios/download/(\d+)', function($id) {
    $controller = new AudioController();
    $controller->download($id);
});

$router->post('/api/player', [PlayerController::class, 'play']);
$router->post('/api/player/pause', [PlayerController::class, 'pause']);
$router->post('/api/player/resume', [PlayerController::class, 'resume']);
$router->post('/api/player/stop', [PlayerController::class, 'stop']);
$router->get('/api/player/status', [PlayerController::class, 'status']);
$router->post('/api/player/status', [PlayerController::class, 'status']);

$router->get('/api/users', [UserController::class, 'index']);
$router->post('/api/users', [UserController::class, 'create']);
$router->delete('/api/users/(\d+)', function($id) {
    $controller = new UserController();
    $controller->delete($id);
});

// Obtener la ruta actual
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remover /public si está presente
$path = str_replace('/public', '', $path);

// Debug (remover en producción)
if (DEBUG_MODE) {
    error_log('Request URI: ' . $requestUri);
    error_log('Parsed path: ' . $path);
    error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
    error_log('Content type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
}

// Ruta principal - mostrar la aplicación
if ($path === '/' || $path === '/index.php' || $path === '' || $path === '/App_Estacion' || $path === '/App_Estacion/') {
    require_once '../app/views/home.php';
    exit;
}

// Resolver rutas de API
try {
    $router->resolve();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}