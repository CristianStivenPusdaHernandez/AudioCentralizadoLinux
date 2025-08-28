<?php
header('Content-Type: application/json');

$host = 'localhost';
$db = 'appestacion';
$user = 'root';
$pass = '';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }
    
    // Crear tabla audios si no existe
    $sql = "CREATE TABLE IF NOT EXISTS audios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        archivo LONGBLOB NOT NULL,
        extension VARCHAR(10) NOT NULL,
        categoria VARCHAR(50) NOT NULL,
        fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'message' => 'Tabla audios creada o ya existe']);
    } else {
        throw new Exception('Error creating table: ' . $conn->error);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>