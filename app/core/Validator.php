<?php
class Validator {
    
    public static function required($value, $fieldName = 'Campo') {
        if (empty(trim($value))) {
            throw new InvalidArgumentException("$fieldName es requerido");
        }
        return trim($value);
    }
    
    public static function maxLength($value, $max, $fieldName = 'Campo') {
        if (strlen($value) > $max) {
            throw new InvalidArgumentException("$fieldName no puede exceder $max caracteres");
        }
        return $value;
    }
    
    public static function audioFile($file) {
        $allowedTypes = ['mp3', 'm4a', 'wav', 'ogg'];
        $maxSize = 50 * 1024 * 1024; // 50MB
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Error al subir archivo');
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            throw new InvalidArgumentException('Tipo de archivo no permitido');
        }
        
        if ($file['size'] > $maxSize) {
            throw new InvalidArgumentException('Archivo muy grande (máx 50MB)');
        }
        
        return $extension;
    }
    
    public static function sanitizeString($value) {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
}