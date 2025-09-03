<?php
class AudioController extends Controller {
    
    public function index() {
        $this->requireLogin();
        
        $audioModel = $this->model('Audio');
        $audios = $audioModel->getAll();
        
        $this->jsonResponse([
            'success' => true,
            'audios' => $audios,
            'count' => count($audios)
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
        if ($audio['extension'] === 'm4a') $mime = 'audio/mp4';
        if ($audio['extension'] === 'wav') $mime = 'audio/wav';
        if ($audio['extension'] === 'ogg') $mime = 'audio/ogg';
        
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($audio['archivo']));
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=3600');
        
        echo $audio['archivo'];
        exit;
    }
    
    public function create() {
        $this->requirePermission('subir_audio');
        
        if (!isset($_FILES['audio']) || !isset($_POST['nombre']) || !isset($_POST['categoria'])) {
            $this->jsonResponse(['error' => 'Faltan datos'], 400);
        }
        
        if ($_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
            $this->jsonResponse(['error' => 'Error al subir archivo'], 400);
        }
        
        $nombre = trim($_POST['nombre']);
        $categoria = trim($_POST['categoria']);
        
        if (empty($nombre) || empty($categoria)) {
            $this->jsonResponse(['error' => 'Nombre y categoría requeridos'], 400);
        }
        
        $allowedTypes = ['mp3', 'm4a', 'wav', 'ogg'];
        $extension = strtolower(pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedTypes)) {
            $this->jsonResponse(['error' => 'Tipo de archivo no permitido'], 400);
        }
        
        $maxSize = 64 * 1024 * 1024; // 64MB
        if ($_FILES['audio']['size'] > $maxSize) {
            $this->jsonResponse(['error' => 'Archivo muy grande (máx 64MB)'], 400);
        }
        
        $archivo = file_get_contents($_FILES['audio']['tmp_name']);
        
        $audioModel = $this->model('Audio');
        $id = $audioModel->create($nombre, $archivo, $extension, $categoria);
        
        if ($id) {
            $this->jsonResponse(['success' => true, 'id' => $id]);
        } else {
            $this->jsonResponse(['error' => 'No se pudo guardar el audio'], 500);
        }
    }
    
    public function update($id) {
        $this->requireLogin();
        
        // Solo administradores y operadores pueden editar audios
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
            // Detener la reproducción
            $commands = [
                'taskkill /F /IM powershell.exe 2>nul',
                'taskkill /F /IM wmplayer.exe 2>nul'
            ];
            
            foreach ($commands as $cmd) {
                shell_exec($cmd);
            }
            
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
        
        // Solo administradores y operadores pueden editar categorías
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