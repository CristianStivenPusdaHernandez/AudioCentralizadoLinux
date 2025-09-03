<?php
class Audio {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->createTableIfNotExists();
        $this->addSampleData();
    }
    
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS audios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            archivo LONGBLOB NOT NULL,
            extension VARCHAR(10) NOT NULL,
            categoria VARCHAR(50) NOT NULL,
            fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->query($sql);
    }
    
    private function addSampleData() {
        $result = $this->db->query("SELECT COUNT(*) as count FROM audios");
        $count = $result->fetch_assoc()['count'];
        
        if ($count == 0) {
            $ejemplos = [
                ['Bienvenida', 'ANUNCIOS GENERALES', 'm4a'],
                ['Tren 253', 'ANUNCIOS DEL TREN', 'm4a'],
                ['Tren 254', 'ANUNCIOS DEL TREN', 'm4a'],
                ['Permanecer en sus asientos', 'ANUNCIOS GENERALES', 'm4a']
            ];
            
            foreach ($ejemplos as $ejemplo) {
                $stmt = $this->db->prepare("INSERT INTO audios (nombre, archivo, extension, categoria) VALUES (?, ?, ?, ?)");
                $archivo_vacio = '';
                $stmt->bind_param('ssss', $ejemplo[0], $archivo_vacio, $ejemplo[2], $ejemplo[1]);
                $stmt->execute();
            }
        }
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