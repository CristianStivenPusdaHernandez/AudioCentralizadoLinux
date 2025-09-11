<?php
class AudioController extends Controller {
    
    public function index() {
        $this->requireLogin();
        
        $nombre = $_GET['sort'] ?? 'nombre';
        $orden =$_GET['order'] ?? 'asc';

        $todos = ['nombre','fecha_subida'];
        $ordenes = ['asc','desc'];

        if(!in_array($nombre,$todos)){
            $nombre = 'nombre';
        }
        if(!in_array(strtolower($orden),$ordenes)){
            $orden = 'asc';
        }

        $audioModel = $this->model('Audio');
        $audios = $audioModel->getAll($nombre,$orden);
        
        $this->jsonResponse([
            'success' => true,
            'audios' => $audios,
            'count' => count($audios),
            'sort' => $nombre,
            'order' => $orden
        ]);
    }
    
    public function download($id) {
        $this->requireLogin();
        
        $audioModel = $this->model('Audio');
        $audio = $audioModel->getById($id);
        
        if (!$audio) {
            http_response_code(404);
            echo 'Audio no encontrado';
            exit;
        }
        
        if (empty($audio['archivo'])) {
            http_response_code(404);
            echo 'Archivo de audio vacío';
            exit;
        }
        
        ob_clean();
        
        $mime = 'audio/mpeg';
        if ($audio['extension'] === 'mp3') $mime = 'audio/mpeg';
        if ($audio['extension'] === 'm4a') $mime = 'audio/mp4';
        if ($audio['extension'] === 'wav') $mime = 'audio/wav';
        if ($audio['extension'] === 'ogg') $mime = 'audio/ogg';
        if ( $audio['extension'] === 'opus' ) $mime = 'audio/opus';
        if( $audio['extension'] === 'amr' ) $mime = 'audio/amr';
        
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($audio['archivo']));
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=3600');
        
        echo $audio['archivo'];
        exit;
    }
    
    public function create() {
        $this->requirePermission('subir_audio');
        
        // Debug: log del error de archivo
        error_log('Upload attempt - FILES: ' . print_r($_FILES, true));
        error_log('Upload attempt - POST: ' . print_r($_POST, true));
        
        // Debug: verificar tamaño del archivo
        if (isset($_FILES['audio'])) {
            error_log('File size: ' . $_FILES['audio']['size'] . ' bytes');
            error_log('File error code: ' . $_FILES['audio']['error']);
            error_log('Max size allowed: ' . (64 * 1024 * 1024) . ' bytes');
        }
        
        if (!isset($_FILES['audio']) || !isset($_POST['nombre']) || !isset($_POST['categoria'])) {
            $this->jsonResponse(['error' => 'Faltan datos'], 400);
        }
        
        if ($_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = 'Error al subir archivo: ' . $_FILES['audio']['error'];
            error_log($errorMsg);
            $this->jsonResponse(['error' => $errorMsg], 400);
        }
        
        $nombre = trim($_POST['nombre']);
        $categoria = trim($_POST['categoria']);
        
        if (empty($nombre) || empty($categoria)) {
            $this->jsonResponse(['error' => 'Nombre y categoría requeridos'], 400);
        }
        
        $allowedTypes = ['mp3', 'm4a', 'wav', 'ogg', 'opus','amr'];
        $extension = strtolower(pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedTypes)) {
            $this->jsonResponse(['error' => 'Tipo de archivo no permitido'], 400);
        }
        
        $maxSize = 64 * 1024 * 1024; // 64MB
        if ($_FILES['audio']['size'] > $maxSize) {
            $this->jsonResponse(['error' => 'Archivo muy grande (máx 64MB)'], 400);
        }
        
        $archivo = file_get_contents($_FILES['audio']['tmp_name']);
        error_log('File content length: ' . strlen($archivo) . ' bytes');
        
        $audioModel = $this->model('Audio');
        error_log('Attempting to save audio to database...');
        $id = $audioModel->create($nombre, $archivo, $extension, $categoria);
        error_log('Database save result: ' . ($id ? 'SUCCESS (ID: ' . $id . ')' : 'FAILED'));
        
        if ($id) {
            $this->jsonResponse(['success' => true, 'id' => $id]);
        } else {
            $this->jsonResponse(['error' => 'No se pudo guardar el audio'], 500);
        }
    }
    
    public function update($id) {
        $this->requireLogin();
    
        if ($_SESSION['rol'] !== 'administrador' && $_SESSION['rol'] !== 'operador') {
            $this->jsonResponse(['error' => 'No tienes permisos para editar audios'], 403);
        }
        
        parse_str(file_get_contents('php://input'), $data);
        if (!isset($data['nombre'])) {
            $this->jsonResponse(['error' => 'Faltan datos para renombrar'], 400);
        }
        
        $audioModel = $this->model('Audio');
        if ($audioModel->update($id, $data['nombre'])) {
            $this->jsonResponse(['success' => true]);
        } else {
            $this->jsonResponse(['error' => 'No se pudo renombrar el audio'], 500);
        }
    }
    
    public function delete($id) {
        $this->requirePermission('eliminar_audio');
        
        // Verificar si el audio que se va a eliminar está reproduciéndose
        $playerState = new PlayerState();
        $currentState = $playerState->getState();
        
        if ($currentState['playing'] && $currentState['id'] == $id) {
            // Linux: detener reproductores
            shell_exec('pkill -f "ffplay|paplay|aplay" > /dev/null 2>&1');
            
            // Limpiar estado del reproductor
            $playerState->clearState();
        }
        
        $audioModel = $this->model('Audio');
        if ($audioModel->delete($id)) {
            $this->jsonResponse(['success' => true]);
        } else {
            $this->jsonResponse(['error' => 'No se pudo eliminar el audio'], 500);
        }
    }
    
    public function updateCategory() {
        $this->requireLogin();
        
        if ($_SESSION['rol'] !== 'administrador' && $_SESSION['rol'] !== 'operador') {
            $this->jsonResponse(['error' => 'No tienes permisos para editar categorías'], 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['old_category']) || !isset($input['new_category'])) {
            $this->jsonResponse(['error' => 'Faltan datos para editar categoría'], 400);
        }
        
        $audioModel = $this->model('Audio');
        if ($audioModel->updateCategory($input['old_category'], $input['new_category'])) {
            $this->jsonResponse(['success' => true]);
        } else {
            $this->jsonResponse(['error' => 'No se pudo renombrar la categoría'], 500);
        }
    }
}