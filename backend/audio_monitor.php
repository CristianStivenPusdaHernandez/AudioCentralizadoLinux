<?php
// Script para simular duración de audio y actualizar estado
error_reporting(0);
ini_set('display_errors', 0);

if ($argc < 3) {
    exit;
}

$title = $argv[1];
$duration = intval($argv[2]); // duración estimada en segundos

$status_file = sys_get_temp_dir() . '/player_status.json';

// Mantener estado activo durante la duración del audio
for ($i = 0; $i <= $duration; $i++) {
    // Verificar si se debe detener cada 0.1 segundos para mayor responsividad
    for ($j = 0; $j < 10; $j++) {
        if (file_exists(sys_get_temp_dir() . '/stop_monitor.txt')) {
            break 2; // Salir de ambos bucles
        }
        usleep(100000); // 0.1 segundos
    }
    
    // Leer estado actual para mantener el repeat
    $current_status = [];
    if (file_exists($status_file)) {
        $current_status = json_decode(file_get_contents($status_file), true) ?: [];
    }
    
    $status = [
        'playing' => true,
        'title' => $title,
        'duration' => $duration,
        'position' => $i,
        'repeat' => $current_status['repeat'] ?? false,
        'timestamp' => time()
    ];
    
    file_put_contents($status_file, json_encode($status));
}

// Audio terminado - mantener estado de repetición
$current_status = [];
if (file_exists($status_file)) {
    $current_status = json_decode(file_get_contents($status_file), true) ?: [];
}

$status = [
    'playing' => false,
    'title' => '',
    'duration' => 0,
    'position' => 0,
    'repeat' => $current_status['repeat'] ?? false,
    'timestamp' => time()
];
file_put_contents($status_file, json_encode($status));
?>