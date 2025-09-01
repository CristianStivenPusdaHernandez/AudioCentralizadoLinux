<?php
// Test de permisos y capacidades de audio
header('Content-Type: text/plain');

echo "=== TEST DE AUDIO EN SERVIDOR ===\n\n";

// 1. Verificar si PowerShell está disponible
echo "1. Verificando PowerShell...\n";
$ps_test = shell_exec('powershell -Command "Get-Host | Select-Object Version"');
if ($ps_test) {
    echo "✓ PowerShell disponible\n";
    echo "Versión: " . trim($ps_test) . "\n\n";
} else {
    echo "✗ PowerShell NO disponible\n\n";
}

// 2. Verificar permisos de ejecución
echo "2. Verificando permisos de ejecución...\n";
$exec_test = shell_exec('powershell -Command "echo \'Test ejecutado correctamente\'"');
if (trim($exec_test) === 'Test ejecutado correctamente') {
    echo "✓ PHP puede ejecutar PowerShell\n\n";
} else {
    echo "✗ PHP NO puede ejecutar PowerShell\n";
    echo "Resultado: " . ($exec_test ?: 'Sin respuesta') . "\n\n";
}

// 3. Verificar MediaPlayer
echo "3. Verificando Windows MediaPlayer...\n";
$media_test = shell_exec('powershell -Command "try { Add-Type -AssemblyName presentationCore; \'MediaPlayer disponible\' } catch { \'Error: \' + $_.Exception.Message }"');
if (strpos($media_test, 'MediaPlayer disponible') !== false) {
    echo "✓ Windows MediaPlayer disponible\n\n";
} else {
    echo "✗ Windows MediaPlayer NO disponible\n";
    echo "Error: " . trim($media_test) . "\n\n";
}

// 4. Crear y probar archivo de audio de prueba
echo "4. Creando archivo de prueba...\n";
$test_file = sys_get_temp_dir() . '/test_beep.wav';

// Crear un archivo WAV simple (beep de 1 segundo)
$wav_header = pack('V', 0x46464952) . pack('V', 36) . pack('V', 0x45564157) . 
              pack('V', 0x20746d66) . pack('V', 16) . pack('v', 1) . pack('v', 1) . 
              pack('V', 8000) . pack('V', 8000) . pack('v', 1) . pack('v', 8) . 
              pack('V', 0x61746164) . pack('V', 8000);

$wav_data = str_repeat(chr(128), 8000); // 1 segundo de silencio
file_put_contents($test_file, $wav_header . $wav_data);

if (file_exists($test_file)) {
    echo "✓ Archivo de prueba creado: $test_file\n\n";
    
    // 5. Probar reproducción
    echo "5. Probando reproducción...\n";
    $play_command = 'powershell -Command "Add-Type -AssemblyName presentationCore; $mp = New-Object system.windows.media.mediaplayer; $mp.open([uri]\'file:///' . str_replace('\\', '/', $test_file) . '\'); $mp.Play(); Start-Sleep 2; $mp.Close()"';
    
    $play_result = shell_exec($play_command . ' 2>&1');
    
    if ($play_result === null || trim($play_result) === '') {
        echo "✓ Comando de reproducción ejecutado sin errores\n";
    } else {
        echo "⚠ Posibles errores en reproducción:\n";
        echo trim($play_result) . "\n";
    }
    
    // Limpiar archivo de prueba
    unlink($test_file);
} else {
    echo "✗ No se pudo crear archivo de prueba\n";
}

echo "\n=== CONFIGURACIÓN RECOMENDADA ===\n";
echo "Si hay errores:\n";
echo "1. Ejecutar XAMPP como Administrador\n";
echo "2. Verificar que Windows Media Player esté instalado\n";
echo "3. Instalar codecs de audio (K-Lite Codec Pack)\n";
echo "4. Verificar política de ejecución de PowerShell\n";
?>