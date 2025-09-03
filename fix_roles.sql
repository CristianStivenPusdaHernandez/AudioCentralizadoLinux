-- Script para corregir los roles y permisos
-- Este script debe ejecutarse en la base de datos para establecer correctamente los permisos

-- Crear tabla de roles si no existe
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE NOT NULL
);

-- Crear tabla de permisos si no existe
CREATE TABLE IF NOT EXISTS permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE NOT NULL,
    descripcion VARCHAR(255)
);

-- Crear tabla de relación rol-permiso si no existe
CREATE TABLE IF NOT EXISTS rol_permiso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    permiso_id INT NOT NULL,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rol_permiso (rol_id, permiso_id)
);

-- Crear tabla de usuarios si no existe
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id)
);

-- Insertar roles básicos
INSERT IGNORE INTO roles (nombre) VALUES 
('administrador'),
('operador'),
('reproductor');

-- Insertar permisos básicos
INSERT IGNORE INTO permisos (nombre, descripcion) VALUES 
('reproducir_audio', 'Puede reproducir audios'),
('subir_audio', 'Puede subir nuevos audios'),
('editar_audio', 'Puede editar nombres de audios'),
('eliminar_audio', 'Puede eliminar audios'),
('gestionar_usuarios', 'Puede crear y eliminar usuarios'),
('editar_categorias', 'Puede editar nombres de categorías');

-- Asignar permisos al rol administrador (todos los permisos)
INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) 
SELECT r.id, p.id 
FROM roles r, permisos p 
WHERE r.nombre = 'administrador';

-- Asignar permisos al rol operador (todos excepto gestionar usuarios)
INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) 
SELECT r.id, p.id 
FROM roles r, permisos p 
WHERE r.nombre = 'operador' 
AND p.nombre IN ('reproducir_audio', 'subir_audio', 'editar_audio', 'eliminar_audio', 'editar_categorias');

-- Asignar permisos al rol reproductor (solo reproducir)
INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) 
SELECT r.id, p.id 
FROM roles r, permisos p 
WHERE r.nombre = 'reproductor' 
AND p.nombre = 'reproducir_audio';

-- Crear usuario administrador por defecto si no existe
INSERT IGNORE INTO usuarios (usuario, password, rol_id) 
SELECT 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', r.id 
FROM roles r 
WHERE r.nombre = 'administrador';
-- Contraseña por defecto: password

-- Mostrar la configuración actual
SELECT 
    u.usuario,
    r.nombre as rol,
    GROUP_CONCAT(p.nombre) as permisos
FROM usuarios u
JOIN roles r ON u.rol_id = r.id
LEFT JOIN rol_permiso rp ON r.id = rp.rol_id
LEFT JOIN permisos p ON rp.permiso_id = p.id
GROUP BY u.id, u.usuario, r.nombre
ORDER BY r.nombre, u.usuario;