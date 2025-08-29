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
    // Verificar si se debe detener
    if (file_exists(sys_get_temp_dir() . '/stop_monitor.txt')) {
        break;
    }
    
    $status = [
        'playing' => true,
        'title' => $title,
        'duration' => $duration,
        'position' => $i,
        'timestamp' => time()
    ];
    
    file_put_contents($status_file, json_encode($status));
    sleep(1);
}

// Audio terminado
$status = [
    'playing' => false,
    'title' => '',
    'duration' => 0,
    'position' => 0,
    'timestamp' => time()
];
file_put_contents($status_file, json_encode($status));
?>