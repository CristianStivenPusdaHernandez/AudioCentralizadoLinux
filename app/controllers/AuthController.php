<?php
class AuthController extends Controller {
    
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['usuario']) || !isset($data['password'])) {
                $this->jsonResponse(['error' => 'Datos incompletos'], 400);
            }
            
            $usuario = trim($data['usuario']);
            $password = $data['password'];
            
            if (empty($usuario) || empty($password)) {
                $this->jsonResponse(['error' => 'Usuario y contraseña requeridos'], 400);
            }
            
            $userModel = $this->model('User');
            $userData = $userModel->authenticate($usuario, $password);
            
            if ($userData) {
                $_SESSION['usuario_id'] = $userData['id'];
                $_SESSION['usuario'] = $userData['usuario'];
                $_SESSION['rol'] = $userData['rol'];
                $_SESSION['permisos'] = $userData['permisos'];
                
                $this->jsonResponse([
                    'success' => true,
                    'usuario' => $userData['usuario'],
                    'rol' => $userData['rol'],
                    'permisos' => $userData['permisos']
                ]);
            } else {
                $this->jsonResponse(['error' => 'Credenciales incorrectas'], 401);
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (isset($_SESSION['usuario_id'])) {
                $this->jsonResponse([
                    'usuario' => $_SESSION['usuario'],
                    'rol' => $_SESSION['rol'],
                    'permisos' => $_SESSION['permisos']
                ]);
            } else {
                $this->jsonResponse(['error' => 'No autenticado'], 401);
            }
        }
    }
    
    public function logout() {
        session_destroy();
        $this->jsonResponse(['success' => true]);
    }
}