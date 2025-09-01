<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/auth.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config.php';

try {
    $conn = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Datos JSON inválidos');
    }

    $action = $data['action'] ?? 'play';

    if ($action === 'stop') {
        // Crear archivo de señal para detener monitor
        file_put_contents(sys_get_temp_dir() . '/stop_monitor.txt', '1');
        
        // Detener procesos
        shell_exec('taskkill /f /im powershell.exe >nul 2>&1');
        shell_exec('taskkill /f /im php.exe /fi "WINDOWTITLE eq audio_monitor*" >nul 2>&1');
        
        // Esperar un momento para que se detengan
        usleep(200000); // 200ms
        
        // Actualizar estado global
        $status = [
            'playing' => false,
            'title' => '',
            'duration' => 0,
            'position' => 0,
            'repeat' => false,
            'timestamp' => time()
        ];
        file_put_contents(sys_get_temp_dir() . '/player_status.json', json_encode($status));
        
        // Limpiar archivo de señal
        @unlink(sys_get_temp_dir() . '/stop_monitor.txt');
        
        echo json_encode(['success' => true, 'message' => 'Audio detenido']);
        exit;
    }
    
    if ($action === 'seek') {
        $position = floatval($data['position'] ?? 0);
        // Crear comando para cambiar posición
        $seek_command = 'powershell -Command "$pos = ' . $position . '; echo $pos > ' . sys_get_temp_dir() . '/seek_position.txt"';
        shell_exec($seek_command);
        echo json_encode(['success' => true, 'message' => 'Posición actualizada']);
        exit;
    }

    $audio_id = intval($data['audio_id'] ?? 0);
    if (!$audio_id) {
        throw new Exception('ID de audio requerido');
    }

    $stmt = $conn->prepare('SELECT nombre, archivo, extension FROM audios WHERE id = ?');
    $stmt->bind_param('i', $audio_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$row = $result->fetch_assoc()) {
        throw new Exception('Audio no encontrado');
    }

    // Detener audio anterior y monitor de forma más efectiva
    shell_exec('taskkill /f /im powershell.exe >nul 2>&1');
    shell_exec('taskkill /f /im php.exe /fi "WINDOWTITLE eq audio_monitor*" >nul 2>&1');
    
    // Crear archivo de señal para detener monitor anterior
    file_put_contents(sys_get_temp_dir() . '/stop_monitor.txt', '1');
    
    // Esperar un momento para que los procesos se detengan
    usleep(300000); // 300ms
    
    // Limpiar archivos de señal
    @unlink(sys_get_temp_dir() . '/stop_monitor.txt');
    
    // Limpiar estado anterior
    $stop_status = [
        'playing' => false,
        'title' => '',
        'duration' => 0,
        'position' => 0,
        'timestamp' => time()
    ];
    file_put_contents(sys_get_temp_dir() . '/player_status.json', json_encode($stop_status));
    
    $temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'audio_' . $audio_id . '.' . $row['extension'];
    file_put_contents($temp_file, $row['archivo']);
    
    $safe_file = str_replace('\\', '/', $temp_file);
    
    // Obtener duración primero
    $duration_cmd = 'powershell -WindowStyle Hidden -Command "Add-Type -AssemblyName presentationCore; $mp = New-Object system.windows.media.mediaplayer; $mp.open([uri]\'file:///' . $safe_file . '\'); Start-Sleep 1; while(-not $mp.NaturalDuration.HasTimeSpan) { Start-Sleep 0.1 }; [math]::Round($mp.NaturalDuration.TimeSpan.TotalSeconds); $mp.Close()"';
    $real_duration = intval(trim(shell_exec($duration_cmd)));
    if ($real_duration <= 0) $real_duration = 30;
    
    // Reproducir audio
    $play_cmd = 'powershell -WindowStyle Hidden -Command "Add-Type -AssemblyName presentationCore; $mp = New-Object system.windows.media.mediaplayer; $mp.open([uri]\'file:///' . $safe_file . '\'); $mp.Play(); Start-Sleep ' . $real_duration . '; $mp.Close()"';
    pclose(popen('start /B ' . $play_cmd, 'r'));
    
    // Iniciar monitor
    $monitor_command = 'php "' . __DIR__ . '/audio_monitor.php" "' . addslashes($row['nombre']) . '" ' . $real_duration;
    pclose(popen('start /B ' . $monitor_command, 'r'));
    
    log_accion($conn, 'Reprodujo audio en servidor: ' . $row['nombre']);
    
    // Estado inicial
    $repeat = isset($data['repeat']) ? $data['repeat'] : false;
    $status = [
        'playing' => true,
        'title' => $row['nombre'],
        'duration' => $real_duration,
        'position' => 0,
        'repeat' => $repeat,
        'timestamp' => time()
    ];
    file_put_contents(sys_get_temp_dir() . '/player_status.json', json_encode($status));
    
    echo json_encode([
        'success' => true, 
        'message' => 'Audio reproduciéndose en servidor',
        'title' => $row['nombre'],
        'duration' => $real_duration
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>