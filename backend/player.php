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

try {
    $conn = new mysqli('localhost', 'root', '', 'appestacion');
    if ($conn->connect_error) {
        throw new Exception('Database connection failed');
    }

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
        shell_exec('taskkill /f /im powershell.exe >nul 2>&1');
        shell_exec('taskkill /f /im php.exe /fi "WINDOWTITLE eq audio_monitor*" >nul 2>&1');
        
        // Crear archivo de señal para detener monitor
        file_put_contents(sys_get_temp_dir() . '/stop_monitor.txt', '1');
        
        // Actualizar estado global
        $status = [
            'playing' => false,
            'title' => '',
            'duration' => 0,
            'position' => 0,
            'timestamp' => time()
        ];
        file_put_contents(sys_get_temp_dir() . '/player_status.json', json_encode($status));
        
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

    // Detener audio anterior y monitor
    shell_exec('taskkill /f /im powershell.exe >nul 2>&1');
    shell_exec('taskkill /f /im php.exe /fi "WINDOWTITLE eq audio_monitor*" >nul 2>&1');
    
    // Limpiar archivos de señal
    @unlink(sys_get_temp_dir() . '/stop_monitor.txt');
    
    $temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'audio_' . $audio_id . '.' . $row['extension'];
    file_put_contents($temp_file, $row['archivo']);
    
    $safe_file = str_replace('\\', '/', $temp_file);
    
    // Reproducir audio simple
    $command = 'powershell -WindowStyle Hidden -ExecutionPolicy Bypass -Command "Add-Type -AssemblyName presentationCore; $mp = New-Object system.windows.media.mediaplayer; $mp.open([uri]\'file:///' . $safe_file . '\'); $mp.Play(); Start-Sleep 1; while(-not $mp.NaturalDuration.HasTimeSpan) { Start-Sleep 0.1 }; Start-Sleep $mp.NaturalDuration.TimeSpan.TotalSeconds; $mp.Close()"';
    
    pclose(popen('start /B ' . $command, 'r'));
    
    // Iniciar monitor de progreso (estimado 30 segundos)
    $monitor_command = 'php "' . __DIR__ . '/audio_monitor.php" "' . addslashes($row['nombre']) . '" 30';
    pclose(popen('start /B ' . $monitor_command, 'r'));
    
    log_accion($conn, 'Reprodujo audio en servidor: ' . $row['nombre']);
    
    // Estado inicial simple
    $status = [
        'playing' => true,
        'title' => $row['nombre'],
        'duration' => 0,
        'position' => 0,
        'timestamp' => time()
    ];
    file_put_contents(sys_get_temp_dir() . '/player_status.json', json_encode($status));
    
    echo json_encode([
        'success' => true, 
        'message' => 'Audio reproduciéndose en servidor',
        'title' => $row['nombre']
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