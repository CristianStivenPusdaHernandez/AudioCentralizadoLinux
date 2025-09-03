<?php
class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
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
        // No permitir eliminar el único administrador
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre = "administrador"');
        $stmt->execute();
        $adminCount = $stmt->get_result()->fetch_assoc()['count'];
        
        if ($adminCount <= 1) {
            $stmt = $this->db->prepare('SELECT r.nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $userRole = $stmt->get_result()->fetch_assoc();
            if ($userRole && $userRole['nombre'] === 'administrador') {
                return false; // No eliminar el único administrador
            }
        }
        
        $stmt = $this->db->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }
}