<?php
require_once 'config/config.php';
require_once 'app/core/Database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "Iniciando configuración simple...\n";
    
    // Insertar roles básicos
    $roles = ['administrador', 'operador', 'reproductor', 'lector'];
    foreach ($roles as $rol) {
        $stmt = $conn->prepare("INSERT IGNORE INTO roles (nombre) VALUES (?)");
        $stmt->bind_param('s', $rol);
        $stmt->execute();
    }
    echo "✓ Roles insertados\n";
    
    // Insertar permisos básicos
    $permisos = ['reproducir_audio', 'subir_audio', 'editar_audio', 'eliminar_audio', 'gestionar_usuarios', 'editar_categorias'];
    foreach ($permisos as $permiso) {
        $stmt = $conn->prepare("INSERT IGNORE INTO permisos (nombre) VALUES (?)");
        $stmt->bind_param('s', $permiso);
        $stmt->execute();
    }
    echo "✓ Permisos insertados\n";
    
    // Asignar permisos - Administrador: todos
    $stmt = $conn->prepare("
        INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) 
        SELECT r.id, p.id 
        FROM roles r, permisos p 
        WHERE r.nombre = 'administrador'
    ");
    $stmt->execute();
    
    // Operador: todos excepto gestionar usuarios
    $stmt = $conn->prepare("
        INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) 
        SELECT r.id, p.id 
        FROM roles r, permisos p 
        WHERE r.nombre = 'operador' 
        AND p.nombre IN ('reproducir_audio', 'subir_audio', 'editar_audio', 'eliminar_audio', 'editar_categorias')
    ");
    $stmt->execute();
    
    // Reproductor: solo reproducir
    $stmt = $conn->prepare("
        INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) 
        SELECT r.id, p.id 
        FROM roles r, permisos p 
        WHERE r.nombre = 'reproductor' 
        AND p.nombre = 'reproducir_audio'
    ");
    $stmt->execute();
    
    // Lector: solo reproducir
    $stmt = $conn->prepare("
        INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) 
        SELECT r.id, p.id 
        FROM roles r, permisos p 
        WHERE r.nombre = 'lector' 
        AND p.nombre = 'reproducir_audio'
    ");
    $stmt->execute();
    
    echo "✓ Permisos asignados\n";
    
    // Usuario admin
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT IGNORE INTO usuarios (usuario, password, rol_id) 
        SELECT 'admin', ?, r.id 
        FROM roles r 
        WHERE r.nombre = 'administrador'
    ");
    $stmt->bind_param('s', $adminPassword);
    $stmt->execute();
    
    echo "✓ Usuario admin creado (contraseña: admin123)\n";
    echo "✅ Configuración completada!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>