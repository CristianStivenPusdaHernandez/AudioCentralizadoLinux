<?php
class PlayerController extends Controller {
    
    private function getPlayerState() {
        $playerState = new PlayerState();
        return $playerState;
    }
    
    private function getAudioDuration($audioData) {
        // Verificar que hay datos de audio
        if (empty($audioData) || strlen($audioData) < 100) {
            error_log('Audio vacío o muy pequeño: ' . strlen($audioData) . ' bytes');
            return 30; // Usar duración por defecto para audios de ejemplo
        }
        
        // Crear archivo temporal
        $tempFile = tempnam(sys_get_temp_dir(), 'audio_') . '.mp3';
        file_put_contents($tempFile, $audioData);
        
        // Usar PowerShell con MediaFoundation para obtener duración más confiable
        $command = 'powershell -Command "' .
            'Add-Type -AssemblyName PresentationCore; ' .
            '$media = New-Object System.Windows.Media.MediaPlayer; ' .
            '$media.Open([System.Uri]::new(\"file:///' . str_replace('\\', '/', $tempFile) . '\")); ' .
            'Start-Sleep -Milliseconds 3000; ' .
            'if ($media.NaturalDuration.HasTimeSpan) { ' .
                '$media.NaturalDuration.TimeSpan.TotalSeconds ' .
            '} else { ' .
                '30 ' .
            '}"';
        
        $output = trim(shell_exec($command));
        $duration = (float)$output;
        
        error_log('Duración obtenida para audio: ' . $duration . ' segundos');
        
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
        $currentState = $playerState->getState();
        
        if (!$forcePlay && $currentState['playing']) {
            $this->jsonResponse([
                'audio_playing' => true,
                'message' => '¿Detener el audio actual y reproducir este?'
            ]);
            return;
        }
        
        $audioModel = $this->model('Audio');
        $audio = $audioModel->getById($audioId);
        
        if (!$audio) {
            $this->jsonResponse(['error' => 'Audio no encontrado'], 404);
        }
        
        // Log para debug
        error_log('Audio ID: ' . $audioId . ', Nombre: ' . $audio['nombre'] . ', Tamaño archivo: ' . strlen($audio['archivo']) . ' bytes');
        
        // Detener audio actual si existe
        $this->stopCurrentAudio();
        
        // Crear archivo temporal para reproducir
        $tempFile = tempnam(sys_get_temp_dir(), 'audio_') . '.mp3';
        file_put_contents($tempFile, $audio['archivo']);
        
        // Obtener duración real
        $duration = $this->getAudioDuration($audio['archivo']);
        
        // Crear script PowerShell para reproducir
        $scriptContent = '
            Add-Type -AssemblyName PresentationCore
            $media = New-Object System.Windows.Media.MediaPlayer
            $media.Open([System.Uri]::new("file:///' . str_replace('\\', '/', $tempFile) . '"))
            $media.Play()
            Start-Sleep -Seconds ' . ceil($duration + 2) . '
            $media.Stop()
            $media.Close()
        ';
        
        $scriptFile = tempnam(sys_get_temp_dir(), 'play_') . '.ps1';
        file_put_contents($scriptFile, $scriptContent);
        
        // Ejecutar script en segundo plano
        $command = 'powershell -WindowStyle Hidden -ExecutionPolicy Bypass -File "' . $scriptFile . '"';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen('start /B ' . $command, 'r'));
        }
        
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
            'temp_file' => $tempFile,
            'script_file' => $scriptFile
        ];
        
        $this->jsonResponse([
            'success' => true,
            'message' => 'Audio reproduciéndose',
            'title' => $audio['nombre'],
            'duration' => $duration
        ]);
    }
    
    private function stopCurrentAudio() {
        // Detener procesos PowerShell relacionados con audio
        $command = 'taskkill /F /IM powershell.exe 2>nul';
        shell_exec($command);
        
        // Limpiar archivos temporales de todas las sesiones
        if (isset($_SESSION['temp_files'])) {
            if (isset($_SESSION['temp_files']['temp_file']) && file_exists($_SESSION['temp_files']['temp_file'])) {
                unlink($_SESSION['temp_files']['temp_file']);
            }
            if (isset($_SESSION['temp_files']['script_file']) && file_exists($_SESSION['temp_files']['script_file'])) {
                unlink($_SESSION['temp_files']['script_file']);
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
        
        // Detener procesos de audio
        $commands = [
            'taskkill /F /IM powershell.exe 2>nul',
            'taskkill /F /IM wmplayer.exe 2>nul'
        ];
        
        foreach ($commands as $cmd) {
            shell_exec($cmd);
        }
        
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
    
    public function resume() {
        $this->requireLogin();
        
        $playerState = $this->getPlayerState();
        $currentState = $playerState->getState();
        
        if (!isset($currentState['paused']) || !$currentState['paused']) {
            $this->jsonResponse(['error' => 'No hay audio pausado'], 400);
        }
        
        // Obtener audio de la base de datos
        $audioModel = $this->model('Audio');
        $audio = $audioModel->getById($currentState['id']);
        
        if (!$audio) {
            $this->jsonResponse(['error' => 'Audio no encontrado'], 404);
        }
        
        // Crear archivo temporal
        $tempFile = tempnam(sys_get_temp_dir(), 'audio_') . '.mp3';
        file_put_contents($tempFile, $audio['archivo']);
        
        // Calcular tiempo restante
        $pausePosition = $currentState['pause_position'] ?? 0;
        $remainingTime = $currentState['duration'] - $pausePosition;
        
        // Crear script PowerShell para reproducir desde la posición pausada
        $scriptContent = '
            Add-Type -AssemblyName PresentationCore
            $media = New-Object System.Windows.Media.MediaPlayer
            $media.Open([System.Uri]::new("file:///' . str_replace('\\', '/', $tempFile) . '"))
            Start-Sleep -Milliseconds 1000
            $media.Position = [TimeSpan]::FromSeconds(' . $pausePosition . ')
            $media.Play()
            Start-Sleep -Seconds ' . ceil($remainingTime + 2) . '
            $media.Stop()
            $media.Close()
        ';
        
        $scriptFile = tempnam(sys_get_temp_dir(), 'play_') . '.ps1';
        file_put_contents($scriptFile, $scriptContent);
        
        // Ejecutar script
        $command = 'powershell -WindowStyle Hidden -ExecutionPolicy Bypass -File "' . $scriptFile . '"';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen('start /B ' . $command, 'r'));
        }
        
        // Actualizar estado
        $currentState['playing'] = true;
        $currentState['paused'] = false;
        $currentState['start_time'] = time() - $pausePosition; // Ajustar tiempo de inicio
        unset($currentState['pause_position']);
        $playerState->setState($currentState);
        
        // Guardar archivos temporales
        $_SESSION['temp_files'] = [
            'temp_file' => $tempFile,
            'script_file' => $scriptFile
        ];
        
        $this->jsonResponse([
            'success' => true,
            'message' => 'Audio reanudado',
            'position' => $pausePosition
        ]);
    }
    
    public function stop() {
        $this->requireLogin();
        
        $this->stopCurrentAudio();
        
        // Detener procesos de audio
        $commands = [
            'taskkill /F /IM powershell.exe 2>nul',
            'taskkill /F /IM wmplayer.exe 2>nul'
        ];
        
        foreach ($commands as $cmd) {
            shell_exec($cmd);
        }
        
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
            // Si está pausado, mantener la información del audio
            if (isset($currentState['paused']) && $currentState['paused']) {
                $this->jsonResponse([
                    'playing' => false,
                    'paused' => true,
                    'title' => $currentState['title'],
                    'position' => $currentState['pause_position'] ?? 0,
                    'duration' => $currentState['duration'],
                    'repeat' => $currentState['repeat']
                ]);
            } else {
                // Si no está pausado, no hay audio
                $this->jsonResponse([
                    'playing' => false,
                    'paused' => false,
                    'title' => null,
                    'position' => 0,
                    'duration' => 0,
                    'repeat' => $currentState['repeat'] ?? false
                ]);
            }
            return;
        }
        
        $elapsed = time() - $currentState['start_time'];
        $duration = $currentState['duration'];
        
        // Si el audio terminó
        if ($elapsed >= $duration) {
            if ($currentState['repeat']) {
                // Reiniciar si está en modo repetición
                $audioModel = $this->model('Audio');
                $audio = $audioModel->getById($currentState['id']);
                if ($audio) {
                    // Reproducir de nuevo
                    $tempFile = tempnam(sys_get_temp_dir(), 'audio_') . '.mp3';
                    file_put_contents($tempFile, $audio['archivo']);
                    
                    $scriptContent = '
                        Add-Type -AssemblyName PresentationCore
                        $media = New-Object System.Windows.Media.MediaPlayer
                        $media.Open([System.Uri]::new("file:///' . str_replace('\\', '/', $tempFile) . '"))
                        $media.Play()
                        Start-Sleep -Seconds ' . ceil($duration + 2) . '
                        $media.Stop()
                        $media.Close()
                    ';
                    
                    $scriptFile = tempnam(sys_get_temp_dir(), 'play_') . '.ps1';
                    file_put_contents($scriptFile, $scriptContent);
                    
                    $command = 'powershell -WindowStyle Hidden -ExecutionPolicy Bypass -File "' . $scriptFile . '"';
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        pclose(popen('start /B ' . $command, 'r'));
                    }
                    
                    // Actualizar estado global
                    $currentState['start_time'] = time();
                    $playerState->setState($currentState);
                    
                    // Guardar archivos temporales
                    $_SESSION['temp_files'] = [
                        'temp_file' => $tempFile,
                        'script_file' => $scriptFile
                    ];
                    $elapsed = 0;
                }
            } else {
                // Limpiar si no está en repetición
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