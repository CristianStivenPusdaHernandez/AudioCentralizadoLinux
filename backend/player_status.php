<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/auth.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$status_file = sys_get_temp_dir() . '/player_status.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Obtener estado actual
    if (file_exists($status_file)) {
        $content = file_get_contents($status_file);
        $status = json_decode($content, true);
        
        // Validar que el JSON es válido
        if ($status === null) {
            $status = ['playing' => false, 'title' => '', 'duration' => 0, 'position' => 0, 'timestamp' => 0];
        }
        
        // Asegurar que todas las propiedades existen
        $status = array_merge([
            'playing' => false,
            'title' => '',
            'duration' => 0,
            'position' => 0,
            'repeat' => false,
            'timestamp' => 0
        ], $status);
        
        echo json_encode($status);
    } else {
        echo json_encode(['playing' => false, 'title' => '', 'duration' => 0, 'position' => 0, 'repeat' => false, 'timestamp' => 0]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Leer estado actual si existe
    $current_status = [];
    if (file_exists($status_file)) {
        $current_status = json_decode(file_get_contents($status_file), true) ?: [];
    }
    
    // Actualizar solo los campos proporcionados
    $status = array_merge($current_status, [
        'playing' => $data['playing'] ?? $current_status['playing'] ?? false,
        'title' => $data['title'] ?? $current_status['title'] ?? '',
        'repeat' => $data['repeat'] ?? $current_status['repeat'] ?? false,
        'timestamp' => time()
    ]);
    
    file_put_contents($status_file, json_encode($status));
    echo json_encode(['success' => true]);
    exit;
}
?>