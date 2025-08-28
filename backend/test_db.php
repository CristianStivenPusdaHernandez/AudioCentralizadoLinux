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
    
    // Verificar si la tabla audios existe
    $result = $conn->query("SHOW TABLES LIKE 'audios'");
    if ($result->num_rows == 0) {
        echo json_encode(['error' => 'La tabla audios no existe']);
        exit;
    }
    
    // Verificar estructura de la tabla
    $result = $conn->query("DESCRIBE audios");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    // Contar registros
    $result = $conn->query("SELECT COUNT(*) as count FROM audios");
    $count = $result->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Conexión exitosa',
        'columns' => $columns,
        'count' => $count
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>