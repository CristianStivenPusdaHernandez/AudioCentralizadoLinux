<?php
class Audio {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAll() {
        $result = $this->db->query('SELECT id, nombre, extension, fecha_subida, categoria FROM audios ORDER BY fecha_subida DESC');
        $audios = [];
        
        while ($row = $result->fetch_assoc()) {
            $archivo_fisico = '../audio/' . $row['nombre'] . '.' . $row['extension'];
            if (file_exists($archivo_fisico)) {
                $row['url'] = 'audio/' . $row['nombre'] . '.' . $row['extension'];
            } else {
                $row['url'] = 'api/audios/download/' . $row['id'];
            }
            $audios[] = $row;
        }
        
        return $audios;
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare('SELECT id, nombre, archivo, extension, categoria, fecha_subida FROM audios WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function create($nombre, $archivo, $extension, $categoria) {
        $stmt = $this->db->prepare('INSERT INTO audios (nombre, archivo, extension, categoria) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $nombre, $archivo, $extension, $categoria);
        return $stmt->execute() ? $this->db->getConnection()->insert_id : false;
    }
    
    public function update($id, $nombre) {
        $stmt = $this->db->prepare('UPDATE audios SET nombre = ? WHERE id = ?');
        $stmt->bind_param('si', $nombre, $id);
        return $stmt->execute();
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare('DELETE FROM audios WHERE id = ?');
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }
    
    public function updateCategory($oldCategory, $newCategory) {
        $stmt = $this->db->prepare('UPDATE audios SET categoria = ? WHERE categoria = ?');
        $stmt->bind_param('ss', $newCategory, $oldCategory);
        return $stmt->execute();
    }
}