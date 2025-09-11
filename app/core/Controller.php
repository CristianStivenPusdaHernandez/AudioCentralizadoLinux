<?php
class Controller {
    protected function model($model) {
        require_once '../app/models/' . $model . '.php';
        return new $model();
    }
    
    protected function view($view, $data = []) {
        extract($data);
        require_once '../app/views/' . $view . '.php';
    }
    
    protected function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    protected function requireLogin() {
        if (!isset($_SESSION['usuario_id'])) {
            $this->jsonResponse(['error' => 'No autenticado'], 401);
        }
    }
    
    protected function requirePermission($permission) {
        $this->requireLogin();
        if (!in_array($permission, $_SESSION['permisos'] ?? [])) {
            $this->jsonResponse(['error' => 'Permiso denegado'], 403);
        }
    }
}