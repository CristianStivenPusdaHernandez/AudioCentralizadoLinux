<?php
// backend/login.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$host = 'localhost';
$db = 'appestacion';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $usuario = $conn->real_escape_string($data['usuario'] ?? '');
    $password = $data['password'] ?? '';
    $stmt = $conn->prepare('SELECT u.id, u.usuario, u.password, r.nombre as rol FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.usuario = ?');
    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            // Obtener permisos
            $stmt2 = $conn->prepare('SELECT p.nombre FROM permisos p JOIN rol_permiso rp ON p.id = rp.permiso_id WHERE rp.rol_id = (SELECT rol_id FROM usuarios WHERE id = ?)');
            $stmt2->bind_param('i', $row['id']);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            $permisos = [];
            while ($perm = $res2->fetch_assoc()) {
                $permisos[] = $perm['nombre'];
            }
            $_SESSION['usuario_id'] = $row['id'];
            $_SESSION['usuario'] = $row['usuario'];
            $_SESSION['rol'] = $row['rol'];
            $_SESSION['permisos'] = $permisos;
            echo json_encode([
                'success' => true,
                'usuario' => $row['usuario'],
                'rol' => $row['rol'],
                'permisos' => $permisos
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Contraseña incorrecta']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Usuario no encontrado']);
    }
    exit;
}

if ($method === 'GET') {
    // Verificar sesión
    if (isset($_SESSION['usuario_id'])) {
        echo json_encode([
            'usuario' => $_SESSION['usuario'],
            'rol' => $_SESSION['rol'],
            'permisos' => $_SESSION['permisos']
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
    }
    exit;
}

// Cierre de conexión
$conn->close();
?>
