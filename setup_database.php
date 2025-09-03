<?php
/**
 * Script para inicializar la base de datos con roles y permisos correctos
 * Ejecutar una sola vez después de la instalación
 */

require_once 'config/config.php';
require_once 'app/core/Database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "Iniciando configuración de base de datos...\n";
    
    // Crear tabla de roles
    $sql = "CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) UNIQUE NOT NULL,
        descripcion VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql);
    echo "✓ Tabla roles creada\n";
    
    // Crear tabla de permisos
    $sql = "CREATE TABLE IF NOT EXISTS permisos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) UNIQUE NOT NULL,
        descripcion VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql);
    echo "✓ Tabla permisos creada\n";
    
    // Crear tabla de relación rol-permiso
    $sql = "CREATE TABLE IF NOT EXISTS rol_permiso (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rol_id INT NOT NULL,
        permiso_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
        FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE,
        UNIQUE KEY unique_rol_permiso (rol_id, permiso_id)
    )";
    $conn->query($sql);
    echo "✓ Tabla rol_permiso creada\n";
    
    // Crear tabla de usuarios si no existe
    $sql = "CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        rol_id INT NOT NULL,
        activo BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (rol_id) REFERENCES roles(id)
    )";
    $conn->query($sql);
    echo "✓ Tabla usuarios creada\n";
    
    // Crear tabla de audios si no existe
    $sql = "CREATE TABLE IF NOT EXISTS audios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        archivo LONGBLOB NOT NULL,
        extension VARCHAR(10) NOT NULL,
        categoria VARCHAR(100) NOT NULL,
        tamaño INT DEFAULT 0,
        duracion INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($sql);
    echo "✓ Tabla audios creada\n";
    
    // Insertar roles básicos
    $roles = ['administrador', 'operador', 'reproductor'];
    
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
    
    // Asignar permisos a roles
    
    // Administrador: todos los permisos
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
    
    echo "✓ Permisos asignados a roles\n";
    
    // Crear usuario administrador por defecto si no existe
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT IGNORE INTO usuarios (usuario, password, rol_id) 
        SELECT 'admin', ?, r.id 
        FROM roles r 
        WHERE r.nombre = 'administrador'
    ");
    $stmt->bind_param('s', $adminPassword);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo "✓ Usuario administrador creado (usuario: admin, contraseña: admin123)\n";
    } else {
        echo "✓ Usuario administrador ya existe\n";
    }
    
    // Mostrar configuración actual
    echo "\n=== CONFIGURACIÓN ACTUAL ===\n";
    $result = $conn->query("
        SELECT 
            u.usuario,
            r.nombre as rol,
            GROUP_CONCAT(p.nombre ORDER BY p.nombre) as permisos
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        LEFT JOIN rol_permiso rp ON r.id = rp.rol_id
        LEFT JOIN permisos p ON rp.permiso_id = p.id
        WHERE u.activo = TRUE
        GROUP BY u.id, u.usuario, r.nombre
        ORDER BY r.nombre, u.usuario
    ");
    
    while ($row = $result->fetch_assoc()) {
        echo "Usuario: {$row['usuario']} | Rol: {$row['rol']} | Permisos: {$row['permisos']}\n";
    }
    
    echo "\n✅ Base de datos configurada correctamente!\n";
    echo "\nPuedes crear usuarios adicionales desde la interfaz web con el usuario administrador.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>