<?php
// Script mejorado para obtener duración y reproducir audio
error_reporting(0);
ini_set('display_errors', 0);

if ($argc < 4) {
    exit;
}

$audio_file = $argv[1];
$title = $argv[2];
$audio_id = $argv[3];

$status_file = sys_get_temp_dir() . '/player_status.json';

// Comando PowerShell para obtener duración y reproducir
$safe_file = str_replace('\\', '/', $audio_file);
$ps_script = "
Add-Type -AssemblyName presentationCore
\$mp = New-Object system.windows.media.mediaplayer
\$mp.open([uri]'file:///$safe_file')

# Esperar metadatos
\$timeout = 0
while(-not \$mp.NaturalDuration.HasTimeSpan -and \$timeout -lt 100) {
    Start-Sleep -Milliseconds 50
    \$timeout++
}

if(\$mp.NaturalDuration.HasTimeSpan) {
    \$duration = [math]::Round(\$mp.NaturalDuration.TimeSpan.TotalSeconds)
    \$mp.Play()
    
    # Actualizar estado cada segundo
    for(\$i = 0; \$i -le \$duration; \$i++) {
        if(Test-Path '" . sys_get_temp_dir() . "/stop_monitor.txt') { break }
        
        \$status = @{
            playing = \$true
            title = '$title'
            duration = \$duration
            position = \$i
            timestamp = [int][double]::Parse((Get-Date -UFormat %s))
        } | ConvertTo-Json
        
        \$status | Out-File -FilePath '" . str_replace('\\', '/', $status_file) . "' -Encoding UTF8
        Start-Sleep 1
    }
    
    \$mp.Close()
}

# Estado final
\$final_status = @{
    playing = \$false
    title = ''
    duration = 0
    position = 0
    timestamp = [int][double]::Parse((Get-Date -UFormat %s))
} | ConvertTo-Json

\$final_status | Out-File -FilePath '" . str_replace('\\', '/', $status_file) . "' -Encoding UTF8
";

// Ejecutar PowerShell
$command = 'powershell -WindowStyle Hidden -ExecutionPolicy Bypass -Command "' . str_replace('"', '`"', $ps_script) . '"';
shell_exec($command);
?>