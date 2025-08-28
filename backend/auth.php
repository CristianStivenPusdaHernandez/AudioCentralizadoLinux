<?php
// backend/auth.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

function require_login() {
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
}

function require_permiso($permiso) {
    require_login();
    if (!in_array($permiso, $_SESSION['permisos'] ?? [])) {
        http_response_code(403);
        echo json_encode(['error' => 'Permiso denegado']);
        exit;
    }
}

function log_accion($conn, $accion) {
    $usuario_id = $_SESSION['usuario_id'] ?? null;
    if ($usuario_id) {
        $stmt = $conn->prepare('INSERT INTO logs (usuario_id, accion) VALUES (?, ?)');
        $stmt->bind_param('is', $usuario_id, $accion);
        $stmt->execute();
    }
}
