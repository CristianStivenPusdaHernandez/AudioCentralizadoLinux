# API Documentation

## Autenticación

### POST /api/auth
Iniciar sesión
```json
{
  "usuario": "string",
  "password": "string"
}
```

### GET /api/auth
Verificar sesión actual

### POST /api/logout
Cerrar sesión

## Audios

### GET /api/audios
Listar todos los audios

### POST /api/audios
Subir nuevo audio (multipart/form-data)
- `nombre`: string
- `categoria`: string
- `audio`: file

### PUT /api/audios/{id}
Editar nombre de audio
```
nombre=nuevo_nombre
```

### DELETE /api/audios/{id}
Eliminar audio

### PATCH /api/audios/category
Renombrar categoría
```json
{
  "old_category": "string",
  "new_category": "string"
}
```

### GET /api/audios/download/{id}
Descargar archivo de audio

## Reproductor

### POST /api/player
Reproducir audio
```json
{
  "audio_id": "number",
  "repeat": "boolean"
}
```

### POST /api/player/stop
Detener reproducción

### GET /api/player/status
Obtener estado del reproductor

### POST /api/player/status
Actualizar configuración del reproductor
```json
{
  "repeat": "boolean"
}
```

## Códigos de Estado HTTP

- 200: OK
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 500: Internal Server Error