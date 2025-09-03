<?php
class Constants {
    // HTTP Status Codes
    const HTTP_OK = 200;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_INTERNAL_ERROR = 500;
    
    // File Upload
    const MAX_FILE_SIZE = 67108864; // 64MB
    const ALLOWED_AUDIO_TYPES = ['mp3', 'm4a', 'wav', 'ogg'];
    
    // Audio Categories
    const CATEGORY_GENERAL = 'ANUNCIOS GENERALES';
    const CATEGORY_TRAIN = 'ANUNCIOS DEL TREN';
    
    // Permissions
    const PERM_UPLOAD_AUDIO = 'subir_audio';
    const PERM_EDIT_AUDIO = 'editar_audio';
    const PERM_DELETE_AUDIO = 'eliminar_audio';
    
    // Player Status
    const PLAYER_STOPPED = 'stopped';
    const PLAYER_PLAYING = 'playing';
    const PLAYER_PAUSED = 'paused';
}