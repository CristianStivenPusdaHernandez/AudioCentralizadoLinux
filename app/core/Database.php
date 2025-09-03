<?php
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->loadEnv();
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadEnv() {
        $envPath = __DIR__ . '/../../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                list($name, $value) = explode('=', $line, 2);
                $_ENV[trim($name)] = trim($value);
            }
        }
    }
    
    private function connect() {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $user = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $database = $_ENV['DB_NAME'] ?? 'appestacion';
        
        try {
            // Conectar sin especificar base de datos primero
            $this->connection = new mysqli($host, $user, $password);
            
            if ($this->connection->connect_error) {
                throw new Exception('Connection failed: ' . $this->connection->connect_error);
            }
            
            // Crear base de datos si no existe
            $this->connection->query("CREATE DATABASE IF NOT EXISTS `$database`");
            $this->connection->select_db($database);
            
            $this->connection->set_charset('utf8mb4');
        } catch (Exception $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new Exception('Database unavailable: ' . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
}