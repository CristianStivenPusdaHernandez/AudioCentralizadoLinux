<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, OPTIONS');
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

switch ($method) {
    case 'GET':
        // Descargar audio real
        if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare('SELECT nombre, archivo, extension FROM audios WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($nombre, $archivo, $extension);
                $stmt->fetch();
                $mime = 'audio/mp4';
                if ($extension === 'm4a') $mime = 'audio/x-m4a';
                header('Content-Type: ' . $mime);
                header('Content-Disposition: inline; filename="' . $nombre . '.' . $extension . '"');
                echo $archivo;
            } else {
                http_response_code(404);
                echo 'Audio no encontrado';
            }
            exit;
        }
        // Listar audios
        $result = $conn->query('SELECT id, nombre, extension, fecha_subida FROM audios ORDER BY fecha_subida DESC');
        $audios = [];
        while ($row = $result->fetch_assoc()) {
            $audios[] = $row;
        }
        echo json_encode($audios);
        break;

    case 'POST':
        // Subir audio
        if (!isset($_FILES['audio']) || !isset($_POST['nombre'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan datos']);
            exit;
        }
        $nombre = $conn->real_escape_string($_POST['nombre']);
        $extension = pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION);
        $archivo = file_get_contents($_FILES['audio']['tmp_name']);
        $stmt = $conn->prepare('INSERT INTO audios (nombre, archivo, extension) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $nombre, $archivo, $extension);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo guardar el audio']);
        }
        break;

    case 'DELETE':
        // Eliminar audio
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta el id']);
            exit;
        }
        $id = intval($_GET['id']);
        $stmt = $conn->prepare('DELETE FROM audios WHERE id = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo eliminar el audio']);
        }
        break;

    case 'PUT':
        // Renombrar audio
        parse_str(file_get_contents('php://input'), $put_vars);
        if (!isset($_GET['id']) || !isset($put_vars['nombre'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan datos para renombrar']);
            exit;
        }
        $id = intval($_GET['id']);
        $nuevo_nombre = $conn->real_escape_string($put_vars['nombre']);
        $stmt = $conn->prepare('UPDATE audios SET nombre = ? WHERE id = ?');
        $stmt->bind_param('si', $nuevo_nombre, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo renombrar el audio']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        break;
}
$conn->close();
