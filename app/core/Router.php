<?php
class Router {
    private $routes = [];
    
    public function get($path, $callback) {
        $this->routes['GET'][$path] = $callback;
    }
    
    public function post($path, $callback) {
        $this->routes['POST'][$path] = $callback;
    }
    
    public function put($path, $callback) {
        $this->routes['PUT'][$path] = $callback;
    }
    
    public function delete($path, $callback) {
        $this->routes['DELETE'][$path] = $callback;
    }
    
    public function patch($path, $callback) {
        $this->routes['PATCH'][$path] = $callback;
    }
    
    public function resolve() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Limpiar la ruta
        $path = str_replace('/public', '', $path);
        $path = str_replace('/App_Estacion', '', $path);
        $path = rtrim($path, '/');
        if (empty($path)) $path = '/';
        
        if (DEBUG_MODE) {
            error_log("Router - Method: $method, Path: $path");
        }
        
        $callback = null;
        $params = [];
        
        // Buscar coincidencia exacta primero
        if (isset($this->routes[$method][$path])) {
            $callback = $this->routes[$method][$path];
        } else {
            // Buscar coincidencias con parámetros
            foreach ($this->routes[$method] ?? [] as $route => $routeCallback) {
                $pattern = str_replace('(\d+)', '(\d+)', $route);
                $pattern = '#^' . $pattern . '$#';
                if (preg_match($pattern, $path, $matches)) {
                    $callback = $routeCallback;
                    $params = array_slice($matches, 1);
                    break;
                }
            }
        }
        
        if ($callback === null) {
            if (DEBUG_MODE) {
                error_log("Route not found: $path");
                error_log("Available routes: " . print_r($this->routes[$method] ?? [], true));
            }
            http_response_code(404);
            echo json_encode(['error' => 'Route not found', 'path' => $path]);
            return;
        }
        
        try {
            if (is_array($callback)) {
                $controller = new $callback[0]();
                $methodName = $callback[1];
                $controller->$methodName(...$params);
            } else {
                call_user_func_array($callback, $params);
            }
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Controller error: " . $e->getMessage());
            }
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
}