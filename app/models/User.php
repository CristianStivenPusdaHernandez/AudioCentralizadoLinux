<?php
class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->createTables();
        $this->syncRolePermissions();
    }
    
    private function syncRolePermissions() {
        // Limpiar permisos existentes para operator
        $this->db->query("DELETE rp FROM rol_permiso rp JOIN roles r ON rp.rol_id = r.id WHERE r.nombre = 'operator'");
        
        // Asignar permisos al rol operator
        $this->db->query("INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) 
                         SELECT r.id, p.id FROM roles r, permisos p WHERE r.nombre = 'operator'");
    }
    
    private function createTables() {
        // Crear tabla roles
        $sql = "CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(50) NOT NULL UNIQUE
        )";
        $this->db->query($sql);
        
        // Crear tabla usuarios
        $sql = "CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            rol_id INT,
            FOREIGN KEY (rol_id) REFERENCES roles(id)
        )";
        $this->db->query($sql);
        
        // Crear tabla permisos
        $sql = "CREATE TABLE IF NOT EXISTS permisos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(50) NOT NULL UNIQUE
        )";
        $this->db->query($sql);
        
        // Crear tabla rol_permiso
        $sql = "CREATE TABLE IF NOT EXISTS rol_permiso (
            rol_id INT,
            permiso_id INT,
            PRIMARY KEY (rol_id, permiso_id),
            FOREIGN KEY (rol_id) REFERENCES roles(id),
            FOREIGN KEY (permiso_id) REFERENCES permisos(id)
        )";
        $this->db->query($sql);
        
        $this->insertDefaultData();
    }
    
    private function insertDefaultData() {
        // Insertar permisos si no existen
        $permisos = ['subir_audio', 'editar_audio', 'eliminar_audio'];
        foreach ($permisos as $permiso) {
            $this->db->query("INSERT IGNORE INTO permisos (nombre) VALUES ('$permiso')");
        }
        
        // Insertar roles si no existen
        $this->db->query("INSERT IGNORE INTO roles (nombre) VALUES ('admin')");
        $this->db->query("INSERT IGNORE INTO roles (nombre) VALUES ('operator')");
        $this->db->query("INSERT IGNORE INTO roles (nombre) VALUES ('viewer')");
        
        // Asignar permisos al rol admin (todos los permisos)
        $this->db->query("INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) 
                         SELECT r.id, p.id FROM roles r, permisos p WHERE r.nombre = 'admin'");
        
        // Asignar permisos al rol operator (todos excepto gestión de usuarios)
        $this->db->query("INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) 
                         SELECT r.id, p.id FROM roles r, permisos p WHERE r.nombre = 'operator'");
        
        // El rol viewer no tiene permisos especiales (solo reproducir)
        
        // Crear usuario admin por defecto si no existe
        $result = $this->db->query("SELECT COUNT(*) as count FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre = 'admin'");
        if ($result->fetch_assoc()['count'] == 0) {
            $password = password_hash('admin', PASSWORD_DEFAULT);
            $this->db->query("INSERT INTO usuarios (usuario, password, rol_id) 
                             SELECT 'admin', '$password', id FROM roles WHERE nombre = 'admin'");
        }
    }
    
    public function authenticate($usuario, $password) {
        $stmt = $this->db->prepare('SELECT u.id, u.usuario, u.password, r.nombre as rol FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.usuario = ?');
        $stmt->bind_param('s', $usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $permisos = $this->getPermissions($row['id']);
                return [
                    'id' => $row['id'],
                    'usuario' => $row['usuario'],
                    'rol' => $row['rol'],
                    'permisos' => $permisos
                ];
            }
        }
        return false;
    }
    
    private function getPermissions($userId) {
        $stmt = $this->db->prepare('SELECT p.nombre FROM permisos p JOIN rol_permiso rp ON p.id = rp.permiso_id WHERE rp.rol_id = (SELECT rol_id FROM usuarios WHERE id = ?)');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $permisos = [];
        while ($row = $result->fetch_assoc()) {
            $permisos[] = $row['nombre'];
        }
        return $permisos;
    }
    
    public function getAll() {
        $result = $this->db->query('SELECT u.id, u.usuario, r.nombre as rol FROM usuarios u JOIN roles r ON u.rol_id = r.id ORDER BY u.usuario');
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        return $users;
    }
    
    public function create($usuario, $password, $rol) {
        // Verificar si el usuario ya existe
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM usuarios WHERE usuario = ?');
        $stmt->bind_param('s', $usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->fetch_assoc()['count'] > 0) {
            return false; // Usuario ya existe
        }
        
        // Obtener ID del rol
        $stmt = $this->db->prepare('SELECT id FROM roles WHERE nombre = ?');
        $stmt->bind_param('s', $rol);
        $stmt->execute();
        $result = $stmt->get_result();
        $rolData = $result->fetch_assoc();
        
        if (!$rolData) {
            // Crear rol si no existe
            $stmt = $this->db->prepare('INSERT INTO roles (nombre) VALUES (?)');
            $stmt->bind_param('s', $rol);
            $stmt->execute();
            $rolId = $this->db->getConnection()->insert_id;
        } else {
            $rolId = $rolData['id'];
        }
        
        // Crear usuario
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare('INSERT INTO usuarios (usuario, password, rol_id) VALUES (?, ?, ?)');
        $stmt->bind_param('ssi', $usuario, $hashedPassword, $rolId);
        return $stmt->execute();
    }
    
    public function delete($id) {
        // No permitir eliminar el único admin
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre = "admin"');
        $stmt->execute();
        $adminCount = $stmt->get_result()->fetch_assoc()['count'];
        
        if ($adminCount <= 1) {
            $stmt = $this->db->prepare('SELECT r.nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $userRole = $stmt->get_result()->fetch_assoc();
            if ($userRole && $userRole['nombre'] === 'admin') {
                return false; // No eliminar el único admin
            }
        }
        
        $stmt = $this->db->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }
}