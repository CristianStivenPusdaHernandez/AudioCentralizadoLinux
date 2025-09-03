<?php
// API simple sin errores
session_start();

// Deshabilitar todos los errores para API
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Archivo de estado compartido
$stateFile = sys_get_temp_dir() . '/audio_player_state.json';

// Funciones para manejar estado compartido
function getPlayerState() {
    global $stateFile;
    if (file_exists($stateFile)) {
        $state = json_decode(file_get_contents($stateFile), true);
        return $state ?: ['playing' => false, 'title' => null, 'start_time' => 0, 'duration' => 0, 'repeat' => false];
    }
    return ['playing' => false, 'title' => null, 'start_time' => 0, 'duration' => 0, 'repeat' => false];
}

function setPlayerState($state) {
    global $stateFile;
    file_put_contents($stateFile, json_encode($state));
}

// Limpiar archivos temporales antiguos
if (isset($_SESSION['cleanup_files'])) {
    foreach ($_SESSION['cleanup_files'] as $key => $cleanup) {
        if (time() > $cleanup['time'] && file_exists($cleanup['file'])) {
            unlink($cleanup['file']);
            unset($_SESSION['cleanup_files'][$key]);
        }
    }
}

// Cargar clases necesarias
try {
    require_once '../config/config.php';
    require_once '../app/core/Database.php';
    require_once '../app/models/User.php';
    require_once '../app/models/Audio.php';
} catch (Exception $e) {
    // Si hay error cargando clases, usar modo simple
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Auth endpoints
if (strpos($requestUri, 'auth') !== false) {
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            if (class_exists('User')) {
                $userModel = new User();
                $userData = $userModel->authenticate($data['usuario'] ?? '', $data['password'] ?? '');
                
                if ($userData) {
                    $_SESSION['usuario_id'] = $userData['id'];
                    $_SESSION['usuario'] = $userData['usuario'];
                    $_SESSION['rol'] = $userData['rol'];
                    $_SESSION['permisos'] = $userData['permisos'];
                    echo json_encode([
                        'success' => true,
                        'usuario' => $userData['usuario'],
                        'rol' => $userData['rol'],
                        'permisos' => $userData['permisos']
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode(['error' => 'Credenciales incorrectas']);
                }
            } else {
                // Fallback simple
                if (($data['usuario'] ?? '') === 'admin' && ($data['password'] ?? '') === 'admin') {
                    $_SESSION['usuario_id'] = 1;
                    $_SESSION['usuario'] = 'admin';
                    $_SESSION['rol'] = 'admin';
                    $_SESSION['permisos'] = ['subir_audio', 'editar_audio', 'eliminar_audio'];
                    echo json_encode([
                        'success' => true,
                        'usuario' => 'admin',
                        'rol' => 'admin',
                        'permisos' => $_SESSION['permisos']
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode(['error' => 'Credenciales incorrectas']);
                }
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error del servidor']);
        }
    } else {
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
    }
    exit;
}

// Logout
if (strpos($requestUri, 'logout') !== false) {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// Usuarios
if (strpos($requestUri, 'users') !== false) {
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    
    // Solo administradores pueden gestionar usuarios
    if ($_SESSION['rol'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'No tienes permisos para gestionar usuarios']);
        exit;
    }
    
    try {
        if (class_exists('User')) {
            $userModel = new User();
            
            if ($method === 'GET') {
                $users = $userModel->getAll();
                echo json_encode(['success' => true, 'users' => $users]);
            } elseif ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $userModel->create($data['usuario'], $data['password'], $data['rol']);
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Usuario creado exitosamente']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al crear usuario']);
                }
            } elseif ($method === 'DELETE') {
                $userId = $_GET['id'] ?? null;
                if ($userId) {
                    $result = $userModel->delete($userId);
                    echo json_encode(['success' => $result, 'message' => $result ? 'Usuario eliminado' : 'Error al eliminar']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                }
            } else {
                echo json_encode(['success' => true]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Modelo de usuario no disponible']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error del servidor']);
    }
    exit;
}

// Audios
if (strpos($requestUri, 'audios') !== false) {
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    
    if ($method === 'GET') {
        try {
            if (class_exists('Audio')) {
                $audioModel = new Audio();
                $audios = $audioModel->getAll();
                echo json_encode(['success' => true, 'audios' => $audios, 'count' => count($audios)]);
            } else {
                // Fallback con datos de ejemplo
                $audios = [
                    ['id' => 1, 'nombre' => 'Bienvenida', 'categoria' => 'ANUNCIOS GENERALES', 'url' => '#'],
                    ['id' => 2, 'nombre' => 'Tren 253', 'categoria' => 'ANUNCIOS DEL TREN', 'url' => '#'],
                    ['id' => 3, 'nombre' => 'Permanecer en asientos', 'categoria' => 'ANUNCIOS GENERALES', 'url' => '#'],
                    ['id' => 4, 'nombre' => 'Tren 254', 'categoria' => 'ANUNCIOS DEL TREN', 'url' => '#']
                ];
                echo json_encode(['success' => true, 'audios' => $audios, 'count' => count($audios)]);
            }
        } catch (Exception $e) {
            // En caso de error, devolver array vacío
            echo json_encode(['success' => true, 'audios' => [], 'count' => 0]);
        }
    } elseif ($method === 'POST') {
        echo json_encode(['success' => true, 'message' => 'Audio subido (simulado)']);
    } elseif ($method === 'PUT') {
        echo json_encode(['success' => true, 'message' => 'Audio editado (simulado)']);
    } elseif ($method === 'DELETE') {
        echo json_encode(['success' => true, 'message' => 'Audio eliminado (simulado)']);
    } elseif ($method === 'PATCH') {
        echo json_encode(['success' => true, 'message' => 'Categoría editada (simulada)']);
    } else {
        echo json_encode(['success' => true]);
    }
    exit;
}

// Player
if (strpos($requestUri, 'player') !== false) {
    if (strpos($requestUri, 'status') !== false || ($_GET['action'] ?? '') === 'status') {
        if ($method === 'POST') {
            // Actualizar configuración del reproductor
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['repeat'])) {
                // Actualizar estado compartido con nuevo valor de repeat
                $currentState = getPlayerState();
                $currentState['repeat'] = $data['repeat'];
                setPlayerState($currentState);
            }
            echo json_encode(['success' => true, 'repeat' => $data['repeat'] ?? false]);
        } else {
            // Obtener estado del reproductor compartido
            $playerState = getPlayerState();
            
            if (!$playerState['playing']) {
                echo json_encode([
                    'playing' => false,
                    'title' => null,
                    'position' => 0,
                    'duration' => 0,
                    'repeat' => $playerState['repeat']
                ]);
            } else {
                $elapsed = time() - $playerState['start_time'];
                $duration = $playerState['duration'];
                
                // Si el audio terminó
                if ($elapsed >= $duration) {
                    if ($playerState['repeat']) {
                        // Reiniciar si está en modo repetición
                        $playerState['start_time'] = time();
                        setPlayerState($playerState);
                        $elapsed = 0;
                        
                        // Reproducir de nuevo el mismo audio
                        if (class_exists('Audio') && isset($playerState['audio_id'])) {
                            $audioModel = new Audio();
                            $audio = $audioModel->getById($playerState['audio_id']);
                            if ($audio && !empty($audio['archivo'])) {
                                $tempDir = sys_get_temp_dir();
                                $tempFile = $tempDir . '\\audio_repeat_' . $playerState['audio_id'] . '_' . time() . '.' . $audio['extension'];
                                file_put_contents($tempFile, $audio['archivo']);
                                
                                $duration = getAudioDuration($tempFile) ?: 30;
                                $psScript = '
                                    Add-Type -AssemblyName presentationCore
                                    $mp = New-Object system.windows.media.mediaplayer
                                    $mp.open([uri]"file:///' . str_replace('\\', '/', $tempFile) . '")
                                    $mp.Play()
                                    Start-Sleep -Seconds ' . ($duration + 1) . '
                                    $mp.Stop()
                                    $mp.Close()
                                ';
                                
                                $tempScript = sys_get_temp_dir() . '\\play_audio_repeat_' . $playerState['audio_id'] . '_' . time() . '.ps1';
                                file_put_contents($tempScript, $psScript);
                                
                                $command = 'powershell -WindowStyle Hidden -ExecutionPolicy Bypass -File "' . $tempScript . '"';
                                pclose(popen('start /B ' . $command, 'r'));
                                
                                // Actualizar duración en el estado
                                $playerState['duration'] = $duration;
                                setPlayerState($playerState);
                                
                                // Agregar archivo para limpieza
                                $_SESSION['cleanup_files'][] = [
                                    'file' => $tempScript,
                                    'time' => time() + $duration + 5
                                ];
                                $_SESSION['cleanup_files'][] = [
                                    'file' => $tempFile,
                                    'time' => time() + $duration + 5
                                ];
                            }
                        }
                    } else {
                        // Limpiar si no está en repetición
                        setPlayerState(['playing' => false, 'title' => null, 'start_time' => 0, 'duration' => 0, 'repeat' => false]);
                        echo json_encode([
                            'playing' => false,
                            'title' => null,
                            'position' => 0,
                            'duration' => 0,
                            'repeat' => false
                        ]);
                        exit;
                    }
                }
                
                echo json_encode([
                    'playing' => true,
                    'title' => $playerState['title'],
                    'position' => $elapsed,
                    'duration' => $duration,
                    'repeat' => $playerState['repeat']
                ]);
            }
        }
    } elseif (strpos($requestUri, 'stop') !== false || ($_GET['action'] ?? '') === 'stop') {
        // Detener todos los reproductores
        exec('taskkill /F /IM wmplayer.exe 2>nul', $output, $return);
        exec('taskkill /F /IM powershell.exe 2>nul', $output, $return);
        
        // Esperar un momento para asegurar que se detengan
        sleep(1);
        
        // Detener PowerShell
        exec('taskkill /F /IM powershell.exe /FI "WINDOWTITLE eq *play_audio*" 2>nul', $output, $return);
        
        // Limpiar scripts de PowerShell
        if (isset($_SESSION['cleanup_files'])) {
            foreach ($_SESSION['cleanup_files'] as $key => $cleanup) {
                if (file_exists($cleanup['file'])) {
                    unlink($cleanup['file']);
                }
                unset($_SESSION['cleanup_files'][$key]);
            }
        }
        
        // Limpiar estado compartido
        setPlayerState(['playing' => false, 'title' => null, 'start_time' => 0, 'duration' => 0, 'repeat' => false]);
        echo json_encode(['success' => true, 'message' => 'Audio detenido']);
    } else {
        // Reproducir audio
        $data = json_decode(file_get_contents('php://input'), true);
        $audioId = $data['audio_id'] ?? null;
        $repeat = $data['repeat'] ?? false;
        $forcePlay = $data['force_play'] ?? false;
        
        if (!$audioId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de audio requerido']);
            exit;
        }
        
        // Verificar si hay un audio reproduciéndose usando estado compartido
        $playerState = getPlayerState();
        if ($playerState['playing'] && !$forcePlay) {
            $elapsed = time() - $playerState['start_time'];
            if ($elapsed < $playerState['duration']) {
                // Hay un audio reproduciéndose
                echo json_encode([
                    'audio_playing' => true,
                    'current_title' => $playerState['title'],
                    'remaining_time' => $playerState['duration'] - $elapsed,
                    'message' => 'Audio en reproducción: ' . $playerState['title'] . '. ¿Desea continuar? El audio actual se pausará.'
                ]);
                exit;
            }
        }
        
        // Si se fuerza la reproducción, detener el audio actual completamente
        if ($forcePlay && $playerState['playing']) {
            // Detener todos los procesos de PowerShell relacionados con audio
            exec('taskkill /F /IM powershell.exe 2>nul', $output, $return);
            
            // Esperar un momento para asegurar que se detengan
            sleep(1);
            
            // Limpiar archivos temporales del audio anterior
            if (isset($_SESSION['cleanup_files'])) {
                foreach ($_SESSION['cleanup_files'] as $key => $cleanup) {
                    if (file_exists($cleanup['file'])) {
                        unlink($cleanup['file']);
                    }
                    unset($_SESSION['cleanup_files'][$key]);
                }
            }
            
            // Limpiar estado anterior
            setPlayerState(['playing' => false, 'title' => null, 'start_time' => 0, 'duration' => 0, 'repeat' => false]);
        }
        
        try {
            if (class_exists('Audio')) {
                $audioModel = new Audio();
                $audio = $audioModel->getById($audioId);
                
                if (!$audio) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Audio no encontrado']);
                    exit;
                }
                
                // Reproducir audio real con Windows Media Player
                $audioPath = '';
                
                // Crear archivo temporal desde la base de datos
                if (!empty($audio['archivo'])) {
                    $tempDir = sys_get_temp_dir();
                    $tempFile = $tempDir . '\\audio_' . $audioId . '.' . $audio['extension'];
                    file_put_contents($tempFile, $audio['archivo']);
                    
                    // Validar que el archivo se creó correctamente
                    if (!file_exists($tempFile) || filesize($tempFile) < 1000) {
                        http_response_code(500);
                        echo json_encode(['error' => 'Archivo de audio corrupto o muy pequeño']);
                        exit;
                    }
                    
                    $audioPath = $tempFile;
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Archivo de audio vacío en la base de datos']);
                    exit;
                }
                
                if ($audioPath && file_exists($audioPath)) {
                    // Detener cualquier reproducción anterior
                    exec('taskkill /F /IM wmplayer.exe 2>nul', $output, $return);
                    
                    // Reproducir con Windows Media Player de forma directa
                    $wmplayerPath = 'C:\\Program Files\\Windows Media Player\\wmplayer.exe';
                    if (!file_exists($wmplayerPath)) {
                        $wmplayerPath = 'C:\\Program Files (x86)\\Windows Media Player\\wmplayer.exe';
                    }
                    
                    // Método 1: PowerShell en background para reproducción completa
                    $duration = getAudioDuration($audioPath) ?: 30;
                    $psScript = '
                        Add-Type -AssemblyName presentationCore
                        $mp = New-Object system.windows.media.mediaplayer
                        $mp.open([uri]"file:///' . str_replace('\\', '/', $audioPath) . '")
                        $mp.Play()
                        Start-Sleep -Seconds ' . ($duration + 1) . '
                        $mp.Stop()
                        $mp.Close()
                    ';
                    
                    $tempScript = sys_get_temp_dir() . '\\play_audio_' . $audioId . '.ps1';
                    file_put_contents($tempScript, $psScript);
                    
                    $command = 'powershell -WindowStyle Hidden -ExecutionPolicy Bypass -File "' . $tempScript . '"';
                    
                    // Ejecutar en background
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        pclose(popen('start /B ' . $command, 'r'));
                    } else {
                        exec($command . ' > /dev/null 2>&1 &');
                    }
                    
                    // Limpiar script después de la duración + 5 segundos
                    $cleanupTime = $duration + 5;
                    $_SESSION['cleanup_files'][] = [
                        'file' => $tempScript,
                        'time' => time() + $cleanupTime
                    ];
                    
                    // Guardar estado compartido
                    setPlayerState([
                        'playing' => true,
                        'title' => $audio['nombre'],
                        'start_time' => time(),
                        'duration' => $duration,
                        'repeat' => $repeat,
                        'audio_id' => $audioId
                    ]);
                    
                    // Obtener duración real del archivo (aproximada)
                    $duration = getAudioDuration($audioPath) ?: 30;
                    
                    // Validar duración máxima (30 minutos para 64MB)
                    if ($duration > 1800) {
                        unlink($audioPath);
                        http_response_code(400);
                        echo json_encode(['error' => 'Audio muy largo (máx 30 minutos)']);
                        exit;
                    }
                    
                    $_SESSION['current_audio'] = [
                        'id' => $audioId,
                        'title' => $audio['nombre'],
                        'playing' => true,
                        'start_time' => time(),
                        'duration' => $duration,
                        'file_path' => $audioPath
                    ];
                    $_SESSION['player_repeat'] = $repeat;
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Audio reproduciéndose en servidor',
                        'title' => $audio['nombre'],
                        'duration' => $duration
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Archivo de audio no encontrado']);
                }
            } else {
                echo json_encode(['success' => true, 'message' => 'Audio simulado']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error del servidor']);
        }
    }
    exit;
}

// Default
echo json_encode(['success' => true]);

// Función para obtener duración de audio
function getAudioDuration($filePath) {
    // Intentar obtener duración con ffprobe si está disponible
    $command = 'ffprobe -v quiet -show_entries format=duration -of csv="p=0" "' . $filePath . '" 2>nul';
    $duration = exec($command);
    
    if (is_numeric($duration) && $duration > 0) {
        return (int)$duration;
    }
    
    // Fallback: estimar por tamaño de archivo (muy aproximado)
    $fileSize = filesize($filePath);
    $estimatedDuration = $fileSize / 32000; // Aproximación para MP3 128kbps
    
    return max(10, min(300, (int)$estimatedDuration)); // Entre 10 y 300 segundos
}
?>