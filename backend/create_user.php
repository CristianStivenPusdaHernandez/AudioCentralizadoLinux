<?php
// backend/create_user.php
$host = 'localhost';
$db = 'appestacion';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Datos del usuario que quieres crear
$nuevo_usuario = 'admin';
$password_plano = 'unaContraseñaSegura123.';
$rol_id = 1; // Por ejemplo, el ID del rol 'usuario'

// Hashear la contraseña de forma segura
$hashed_password = password_hash($password_plano, PASSWORD_DEFAULT);

// Inserta el nuevo usuario en la base de datos
$stmt = $conn->prepare('INSERT INTO usuarios (usuario, password, rol_id) VALUES (?, ?, ?)');
$stmt->bind_param('ssi', $nuevo_usuario, $hashed_password, $rol_id);

if ($stmt->execute()) {
    echo "El usuario '{$nuevo_usuario}' fue creado exitosamente.";
} else {
    echo "Error al crear el usuario: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>