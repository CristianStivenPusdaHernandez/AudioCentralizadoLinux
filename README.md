# App_Estacion
Aplicación para reproducir audios, según un menú de selección.

## Arquitectura MVC

Este proyecto ha sido reestructurado siguiendo el patrón de arquitectura **Modelo-Vista-Controlador (MVC)** para mejorar la organización, mantenibilidad y escalabilidad del código.

### Estructura del Proyecto

```
App_Estacion/
├── app/                    # Lógica de la aplicación
│   ├── controllers/        # Controladores (lógica de negocio)
│   │   ├── AuthController.php
│   │   ├── AudioController.php
│   │   └── PlayerController.php
│   ├── models/            # Modelos (acceso a datos)
│   │   ├── User.php
│   │   └── Audio.php
│   ├── views/             # Vistas (presentación)
│   │   └── home.php
│   └── core/              # Clases base del framework
│       ├── Database.php
│       ├── Controller.php
│       └── Router.php
├── public/                # Punto de entrada público
│   ├── index.php         # Archivo principal
│   ├── css/              # Estilos CSS
│   ├── js/               # JavaScript
│   ├── assets/           # Imágenes y recursos
│   └── .htaccess         # Configuración Apache
├── config/               # Configuraciones
│   └── config.php
├── backend/              # Archivos legacy (compatibilidad)
└── .env                  # Variables de entorno
```

### Componentes MVC

#### Modelos (Models)
- **User.php**: Maneja la autenticación y permisos de usuarios
- **Audio.php**: Gestiona los archivos de audio y categorías

#### Vistas (Views)
- **home.php**: Interfaz principal de la aplicación

#### Controladores (Controllers)
- **AuthController.php**: Maneja login, logout y sesiones
- **AudioController.php**: CRUD de audios y categorías
- **PlayerController.php**: Control del reproductor de audio

#### Core
- **Database.php**: Singleton para conexión a base de datos
- **Controller.php**: Clase base para todos los controladores
- **Router.php**: Sistema de enrutamiento de la aplicación

### API Endpoints

#### Autenticación
- `GET/POST /api/auth` - Login y verificación de sesión
- `POST /api/logout` - Cerrar sesión

#### Audios
- `GET /api/audios` - Listar todos los audios
- `POST /api/audios` - Subir nuevo audio
- `PUT /api/audios/{id}` - Editar nombre de audio
- `DELETE /api/audios/{id}` - Eliminar audio
- `PATCH /api/audios/category` - Renombrar categoría
- `GET /api/audios/download/{id}` - Descargar archivo de audio

#### Reproductor
- `POST /api/player` - Reproducir audio
- `POST /api/player/stop` - Detener reproducción
- `GET/POST /api/player/status` - Estado del reproductor

### Instalación

1. Configurar el servidor web para que apunte a la carpeta `public/`
2. Configurar las variables de entorno en `.env`
3. Asegurar que el archivo `.htaccess` esté habilitado
4. La aplicación creará automáticamente las tablas necesarias

### Beneficios de la Arquitectura MVC

- **Separación de responsabilidades**: Cada componente tiene una función específica
- **Mantenibilidad**: Código más organizado y fácil de mantener
- **Escalabilidad**: Fácil agregar nuevas funcionalidades
- **Reutilización**: Componentes reutilizables
- **Testabilidad**: Cada capa se puede probar independientemente

