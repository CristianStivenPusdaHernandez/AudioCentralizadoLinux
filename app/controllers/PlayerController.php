<?php
class PlayerController extends Controller {
    
    private function getPlayerState() {
        $playerState = new PlayerState();
        return $playerState;
    }
    
private function getAudioDuration($audioData, $extension = 'mp3') {
        if (empty($audioData) || strlen($audioData) < 100) {
            error_log('Audio vacío o muy pequeño: ' . strlen($audioData) . ' bytes');
            return 30; 
        }
        
        $tempFile = tempnam(sys_get_temp_dir(), 'audio_') . '.' . $extension;
        file_put_contents($tempFile, $audioData);
        
        $command = "ffprobe -v quiet -show_entries format=duration -of csv=p=0 '$tempFile' 2>/dev/null";
        
        $output = shell_exec($command);
        $duration = (float)trim($output ?: '30');
        
        error_log('Duración obtenida para audio .' . $extension . ': ' . $duration . ' segundos');
        
        unlink($tempFile);
        return $duration > 0 ? $duration : 30;
}
    
    public function play() {
        $this->requireLogin();
        
        $data = json_decode(file_get_contents('php://input'), true);
        $audioId = $data['audio_id'] ?? null;
        $repeat = $data['repeat'] ?? false;
        $forcePlay = $data['force_play'] ?? false;
        
        if (!$audioId) {
            $this->jsonResponse(['error' => 'ID de audio requerido'], 400);
        }
        
        // Verificar si hay audio reproduciéndose globalmente
        $playerState = $this->getPlayerState();
        //VERIFICAR SI HAY AUDIO REPRODUCIENDOSE Y FORZAR DETENCION DE ANTIGUI AUDIO Y REPRODUCIR NUEVO AUDIO
      /*   $currentState = $playerState->getState();
          if (!$forcePlay && $currentState['playing']) {
            $this->jsonResponse([
                'audio_playing' => true,
                'message' => '¿Detener el audio actual y reproducir este?'
            ]);
            return;
        }
      */  
        $audioModel = $this->model('Audio');
        $audio = $audioModel->getById($audioId);
        
        if (!$audio) {
            $this->jsonResponse(['error' => 'Audio no encontrado'], 404);
        }
        
        // Log para debug
        error_log('Audio ID: ' . $audioId . ', Nombre: ' . $audio['nombre'] . ', Tamaño archivo: ' . strlen($audio['archivo']) . ' bytes');
        
        $this->stopCurrentAudio();
             
        $duration = $this->getAudioDuration($audio['archivo'], $audio['extension'] ?? 'mp3');
        
// Crear archivo temporal con la extensión correcta
$extension = $audio['extension'] ?? 'mp3';
$tempFile = tempnam(sys_get_temp_dir(), 'audio_') . '.' . $extension;
file_put_contents($tempFile, $audio['archivo']);

// Verificar que el archivo temporal existe
if (!file_exists($tempFile)) {
    error_log('Error: archivo temporal no existe: ' . $tempFile);
    $this->jsonResponse(['error' => 'Error creando archivo temporal'], 500);
    return;
}

// Fedora: usar ffplay con extensión correcta
$command = "PULSE_SERVER=127.0.0.1 ffplay -nodisp -autoexit '$tempFile' > /dev/null 2>&1 &";

error_log('Ejecutando comando de audio: ' . $command . ' (formato: ' . $extension . ')');
shell_exec($command);


        // Guardar estado global
        $playerState->setState([
            'id' => $audioId,
            'title' => $audio['nombre'],
            'playing' => true,
            'repeat' => $repeat,
            'start_time' => time(),
            'duration' => $duration,
            'position' => 0
        ]);
        
        // Guardar archivos temporales en sesión para limpieza
        $_SESSION['temp_files'] = [
            'temp_file' => $tempFile
        ];
        
        $this->jsonResponse([
            'success' => true,
            'message' => 'Audio reproduciéndose',
            'title' => $audio['nombre'],
            'duration' => $duration
        ]);
    }
    
    private function stopCurrentAudio() {
        // Solo Linux: detener todos los reproductores de audio
        shell_exec('pkill -f "ffplay|paplay|aplay" > /dev/null 2>&1');
        
        // Limpiar archivos temporales
        if (isset($_SESSION['temp_files'])) {
            if (isset($_SESSION['temp_files']['temp_file']) && file_exists($_SESSION['temp_files']['temp_file'])) {
                unlink($_SESSION['temp_files']['temp_file']);
            }
            $_SESSION['temp_files'] = null;
        }
        
        // Limpiar estado global
        $playerState = $this->getPlayerState();
        $playerState->clearState();
    }
    
    public function pause() {
        $this->requireLogin();
        
        $playerState = $this->getPlayerState();
        $currentState = $playerState->getState();
        
        if (!$currentState['playing']) {
            $this->jsonResponse(['error' => 'No hay audio reproduciéndose'], 400);
        }
        
        // Calcular posición actual
        $elapsed = time() - $currentState['start_time'];
        
        // Detener procesos de audio (solo Linux)
        shell_exec('pkill -f "ffplay|paplay|aplay" > /dev/null 2>&1');
        
        // Guardar estado pausado con posición actual
        $currentState['playing'] = false;
        $currentState['paused'] = true;
        $currentState['pause_position'] = $elapsed;
        $playerState->setState($currentState);
        
        $this->jsonResponse([
            'success' => true,
            'message' => 'Audio pausado',
            'position' => $elapsed
        ]);
    }
    
    public function stop() {
        $this->requireLogin();
        
        $this->stopCurrentAudio();
        
        $this->jsonResponse([
            'success' => true,
            'message' => 'Audio detenido'
        ]);
    }
    
    public function status() {
        $this->requireLogin();
        
        $playerState = $this->getPlayerState();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['repeat'])) {
                $currentState = $playerState->getState();
                $currentState['repeat'] = $data['repeat'];
                $playerState->setState($currentState);
            }
            $this->jsonResponse(['success' => true]);
            return;
        }
        
        $currentState = $playerState->getState();
        
        if (!$currentState['playing']) {
            $this->jsonResponse([
                'playing' => false,
                'paused' => false,
                'title' => null,
                'position' => 0,
                'duration' => 0,
                'repeat' => $currentState['repeat'] ?? false
            ]);
            return;
        }
        
        $elapsed = time() - $currentState['start_time'];
        $duration = $currentState['duration'];
        
        // Si el audio terminó
        if ($elapsed >= $duration) {
            $this->stopCurrentAudio();
            $this->jsonResponse([
                'playing' => false,
                'title' => null,
                'position' => 0,
                'duration' => 0,
                'repeat' => false
            ]);
            return;
        }
        
        $this->jsonResponse([
            'playing' => true,
            'title' => $currentState['title'],
            'position' => $elapsed,
            'duration' => $duration,
            'repeat' => $currentState['repeat']
        ]);
    }
}