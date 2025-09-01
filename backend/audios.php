
<?php
session_start();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Proteger el endpoint: solo usuarios autenticados pueden acceder
require_login();

// Funciones de autenticación simplificadas
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
    // Simplificado - no hacer nada por ahora
}

require_once __DIR__ . '/config.php';

try {
    $conn = getDBConnection();
    
    // Crear tabla si no existe
    $sql = "CREATE TABLE IF NOT EXISTS audios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        archivo LONGBLOB NOT NULL,
        extension VARCHAR(10) NOT NULL,
        categoria VARCHAR(50) NOT NULL,
        fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql);
    
    // Agregar datos de ejemplo si la tabla está vacía
    $result = $conn->query("SELECT COUNT(*) as count FROM audios");
    $count = $result->fetch_assoc()['count'];
    if ($count == 0) {
        $ejemplos = [
            ['Bienvenida', 'ANUNCIOS GENERALES', 'm4a'],
            ['Tren 253', 'ANUNCIOS DEL TREN', 'm4a'],
            ['Tren 254', 'ANUNCIOS DEL TREN', 'm4a'],
            ['Permanecer en sus asientos', 'ANUNCIOS GENERALES', 'm4a']
        ];
        
        foreach ($ejemplos as $ejemplo) {
            $stmt = $conn->prepare("INSERT INTO audios (nombre, archivo, extension, categoria) VALUES (?, ?, ?, ?)");
            $archivo_vacio = ''; // Archivo vacío por ahora
            $stmt->bind_param('ssss', $ejemplo[0], $archivo_vacio, $ejemplo[2], $ejemplo[1]);
            $stmt->execute();
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            // Descargar audio real
            if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['id'])) {
                $id = intval($_GET['id']);
                $stmt = $conn->prepare('SELECT nombre, archivo, extension FROM audios WHERE id = ?');
                if (!$stmt) {
                    throw new Exception('Prepare failed: ' . $conn->error);
                }
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    if (empty($row['archivo'])) {
                        http_response_code(404);
                        echo 'Archivo de audio vacío';
                        exit;
                    }
                    
                    // Limpiar cualquier salida previa
                    ob_clean();
                    
                    // Configurar headers apropiados
                    $mime = 'audio/mpeg';
                    if ($row['extension'] === 'm4a') $mime = 'audio/mp4';
                    if ($row['extension'] === 'm4p') $mime = 'audio/mp4';
                    if ($row['extension'] === 'wav') $mime = 'audio/wav';
                    if ($row['extension'] === 'ogg') $mime = 'audio/ogg';
                    if ($row['extension'] === 'flac') $mime = 'audio/flac';
                    if ($row['extension'] === 'aac') $mime = 'audio/aac';
                    
                    header('Content-Type: ' . $mime);
                    header('Content-Length: ' . strlen($row['archivo']));
                    header('Accept-Ranges: bytes');
                    header('Cache-Control: public, max-age=3600');
                    
                    echo $row['archivo'];
                } else {
                    http_response_code(404);
                    echo 'Audio no encontrado';
                }
                exit;
            }
            
            // Listar audios
            $result = $conn->query('SELECT id, nombre, extension, fecha_subida, categoria FROM audios ORDER BY fecha_subida DESC');
            if (!$result) {
                throw new Exception('Query failed: ' . $conn->error);
            }
            
            $audios = [];
            while ($row = $result->fetch_assoc()) {
                // Verificar si el archivo físico existe
                $archivo_fisico = '../audio/' . $row['nombre'] . '.' . $row['extension'];
                if (empty($row['archivo']) && file_exists($archivo_fisico)) {
                    $row['url'] = 'audio/' . $row['nombre'] . '.' . $row['extension'];
                } else {
                    $row['url'] = 'backend/audios.php?action=download&id=' . $row['id'];
                }
                $audios[] = $row;
            }
            echo json_encode(['success' => true, 'audios' => $audios, 'count' => count($audios)]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error en GET: ' . $e->getMessage()]);
        }
        break;

    case 'POST':
        require_permiso('subir_audio');
        if (!isset($_FILES['audio']) || !isset($_POST['nombre']) || !isset($_POST['categoria'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan datos']);
            exit;
        }
        $nombre = $conn->real_escape_string($_POST['nombre']);
        $categoria = $conn->real_escape_string($_POST['categoria']);
        $extension = pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION);
        $archivo = file_get_contents($_FILES['audio']['tmp_name']);
        $stmt = $conn->prepare('INSERT INTO audios (nombre, archivo, extension, categoria) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $nombre, $archivo, $extension, $categoria);
        if ($stmt->execute()) {
            log_accion($conn, 'Subió audio: ' . $nombre);
            echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo guardar el audio']);
        }
        break;

    case 'DELETE':
        require_permiso('eliminar_audio');
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta el id']);
            exit;
        }
        $id = intval($_GET['id']);
        $stmt = $conn->prepare('DELETE FROM audios WHERE id = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            log_accion($conn, 'Eliminó audio ID: ' . $id);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo eliminar el audio']);
        }
        break;

    case 'PUT':
        require_permiso('editar_audio');
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
            log_accion($conn, 'Renombró audio ID: ' . $id . ' a ' . $nuevo_nombre);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo renombrar el audio']);
        }
        break;

    case 'PATCH':
        require_permiso('editar_audio');
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['action']) || $input['action'] !== 'edit_category') {
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
            exit;
        }
        if (!isset($input['old_category']) || !isset($input['new_category'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan datos para editar categoría']);
            exit;
        }
        $old_category = $conn->real_escape_string($input['old_category']);
        $new_category = $conn->real_escape_string($input['new_category']);
        $stmt = $conn->prepare('UPDATE audios SET categoria = ? WHERE categoria = ?');
        $stmt->bind_param('ss', $new_category, $old_category);
        if ($stmt->execute()) {
            log_accion($conn, 'Renombró categoría: ' . $old_category . ' a ' . $new_category);
            echo json_encode(['success' => true, 'affected_rows' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo renombrar la categoría']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        break;
}
$conn->close();
