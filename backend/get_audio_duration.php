<?php
// Script para obtener duración de audio usando PowerShell
error_reporting(0);
ini_set('display_errors', 0);

if ($argc < 2) {
    echo "0";
    exit;
}

$audio_file = $argv[1];

if (!file_exists($audio_file)) {
    echo "0";
    exit;
}

// Comando PowerShell optimizado para obtener duración
$safe_file = str_replace('\\', '/', $audio_file);
$command = 'powershell -WindowStyle Hidden -ExecutionPolicy Bypass -Command "
try {
    Add-Type -AssemblyName presentationCore
    $mp = New-Object system.windows.media.mediaplayer
    $mp.open([uri]\'file:///' . $safe_file . '\')
    
    # Esperar hasta que los metadatos estén disponibles
    $timeout = 0
    while(-not $mp.NaturalDuration.HasTimeSpan -and $timeout -lt 50) {
        Start-Sleep -Milliseconds 100
        $timeout++
    }
    
    if($mp.NaturalDuration.HasTimeSpan) {
        [math]::Round($mp.NaturalDuration.TimeSpan.TotalSeconds)
    } else {
        0
    }
    
    $mp.Close()
} catch {
    0
}"';

$duration = intval(trim(shell_exec($command)));
echo $duration > 0 ? $duration : 0;
?>