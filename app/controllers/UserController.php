<?php
class UserController extends Controller {
    
    public function index() {
        $this->requireLogin();
        
        // Solo administradores pueden gestionar usuarios
        if ($_SESSION['rol'] !== 'administrador') {
            $this->jsonResponse(['error' => 'Solo los administradores pueden gestionar usuarios'], 403);
        }
        
        $userModel = $this->model('User');
        $users = $userModel->getAll();
        
        $this->jsonResponse([
            'success' => true,
            'users' => $users
        ]);
    }
    
    public function create() {
        $this->requireLogin();
        
        // Solo administradores pueden crear usuarios
        if ($_SESSION['rol'] !== 'administrador') {
            $this->jsonResponse(['error' => 'Solo los administradores pueden crear usuarios'], 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['usuario']) || !isset($data['password']) || !isset($data['rol'])) {
            $this->jsonResponse(['error' => 'Faltan datos requeridos'], 400);
        }
        
        $userModel = $this->model('User');
        $result = $userModel->create($data['usuario'], $data['password'], $data['rol']);
        
        if ($result) {
            $this->jsonResponse(['success' => true]);
        } else {
            $this->jsonResponse(['error' => 'No se pudo crear el usuario'], 500);
        }
    }
    
    public function delete($id) {
        $this->requireLogin();
        
        // Solo administradores pueden eliminar usuarios
        if ($_SESSION['rol'] !== 'administrador') {
            $this->jsonResponse(['error' => 'Solo los administradores pueden eliminar usuarios'], 403);
        }
        
        if (!$id || !is_numeric($id)) {
            $this->jsonResponse(['error' => 'ID de usuario inválido'], 400);
        }
        
        try {
            $userModel = $this->model('User');
            $result = $userModel->delete($id);
            
            if ($result === false) {
                $this->jsonResponse(['error' => 'No se puede eliminar el único administrador del sistema'], 400);
            } elseif ($result === true) {
                $this->jsonResponse(['success' => true]);
            } else {
                $this->jsonResponse(['error' => 'Usuario no encontrado'], 404);
            }
        } catch (Exception $e) {
            error_log('Error eliminando usuario: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Error interno del servidor'], 500);
        }
    }
}