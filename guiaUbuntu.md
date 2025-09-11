# Guía Completa: App Estación en Ubuntu Server

## Compatibilidad
- ✅ **Ubuntu Server/Desktop**: Objetivo principal - funciona perfectamente
- ✅ **Debian**: Mismos comandos que Ubuntu
- ⚠️ **Fedora/CentOS/RHEL**: Cambiar `httpd`, `apache`, comandos `dnf`
- ⚠️ **Arch/openSUSE**: Adaptar comandos de instalación

## 1. Instalación en Ubuntu Server

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar dependencias completas
sudo apt install -y apache2 php libapache2-mod-php php-mysql php-json php-mbstring php-xml mysql-server ffmpeg pulseaudio pulseaudio-utils alsa-utils

# Git (opcional - solo si vas a clonar desde repositorio)
sudo apt install -y git

# Habilitar mod_rewrite de Apache
sudo a2enmod rewrite

# Habilitar e iniciar servicios
sudo systemctl enable apache2 mysql
sudo systemctl start apache2 mysql
```

## 2. Configuración de MySQL

```bash
# Configurar seguridad de MySQL
sudo mysql_secure_installation
# Responder: Y, nueva_contraseña, Y, Y, Y, Y

# Crear base de datos y usuario
sudo mysql -u root -p
```

```sql
CREATE DATABASE app_estacion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'app_user'@'localhost' IDENTIFIED BY 'tu_contraseña_segura';
GRANT ALL PRIVILEGES ON app_estacion.* TO 'app_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 3. Configuración de Apache

```bash
# Habilitar .htaccess
sudo nano /etc/apache2/sites-available/000-default.conf
```

Agregar dentro de `<VirtualHost>`:
```apache
<Directory /var/www/html>
    AllowOverride All
    Require all granted
</Directory>
```

```bash
# Configurar límites PHP (usar la versión de PHP instalada)
sudo nano /etc/php/8.*/apache2/php.ini

# O especificar versión exacta:
# sudo nano /etc/php/8.4/apache2/php.ini  # Para Ubuntu 24.04
# sudo nano /etc/php/8.2/apache2/php.ini  # Para Ubuntu 22.04
```

Cambiar estas líneas:
```ini
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
```

```bash
# Configurar MySQL
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Agregar en la sección `[mysqld]`:
```ini
max_allowed_packet = 128M
```

```bash
# Reiniciar servicios
sudo systemctl restart apache2 mysql
```

## 4. Instalar la Aplicación

### Opción A: Clonar desde repositorio (requiere git)
```bash
cd /var/www/html
sudo git clone https://github.com/CristianStivenPusdaHernandez/AudioCentralizadoLinux.git
```

### Opción B: Copiar archivos manualmente
```bash
cd /var/www/html
# Copiar desde USB, descargar ZIP, etc.
sudo cp -r /ruta/origen/App_Estacion .
# O descomprimir: sudo unzip App_Estacion.zip
```

### Configurar permisos (ambas opciones):
```bash
# Configurar permisos (Ubuntu usa www-data)
sudo chown -R www-data:www-data /var/www/html/App_Estacion
sudo chmod -R 755 /var/www/html/App_Estacion
```

## 5. Configuración de Audio

```bash
# Agregar www-data al grupo audio
sudo usermod -a -G audio www-data

# Verificar PulseAudio
pactl info

# Probar ffplay
ffplay --help | head -5

# Reiniciar Apache para aplicar cambios de grupo
sudo systemctl restart apache2
```

**Nota importante:** El audio usa `PULSE_SERVER=127.0.0.1` en el código PHP.

## 6. Configurar Base de Datos

### Opción A: Importar desde archivo SQL (recomendado)

Si tienes un archivo de backup (.sql):

```bash
# Importar desde cualquier ubicación
mysql -u app_user -p app_estacion < /ruta/completa/al/archivo.sql

# Ejemplos comunes:
mysql -u app_user -p app_estacion < ~/backup_app_estacion.sql
mysql -u app_user -p app_estacion < /home/usuario/Descargas/app_estacion.sql
mysql -u app_user -p app_estacion < /media/usb/backup.sql
```

### Opción B: Crear tablas manualmente (si no tienes backup)

Solo ejecutar si no tienes archivo SQL:

```bash
mysql -u app_user -p app_estacion
```

```sql
-- Tabla de roles
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT
);

-- Tabla de permisos
CREATE TABLE permisos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT
);

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol_id INT,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id)
);

-- Tabla de audios
CREATE TABLE audios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    archivo LONGBLOB NOT NULL,
    extension VARCHAR(10) NOT NULL,
    categoria VARCHAR(100) DEFAULT 'GENERAL',
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de relación rol-permiso
CREATE TABLE rol_permiso (
    rol_id INT,
    permiso_id INT,
    PRIMARY KEY (rol_id, permiso_id),
    FOREIGN KEY (rol_id) REFERENCES roles(id),
    FOREIGN KEY (permiso_id) REFERENCES permisos(id)
);

-- Insertar datos iniciales
INSERT INTO roles (nombre, descripcion) VALUES 
('administrador', 'Acceso completo al sistema'),
('operador', 'Puede reproducir y subir audios'),
('reproductor', 'Solo puede reproducir audios');

INSERT INTO permisos (nombre, descripcion) VALUES 
('reproducir_audio', 'Reproducir archivos de audio'),
('subir_audio', 'Subir nuevos archivos de audio'),
('eliminar_audio', 'Eliminar archivos de audio'),
('gestionar_usuarios', 'Crear y gestionar usuarios');

INSERT INTO rol_permiso (rol_id, permiso_id) VALUES 
(1, 1), (1, 2), (1, 3), (1, 4),  -- admin: todos los permisos
(2, 1), (2, 2),                   -- operador: reproducir y subir
(3, 1);                           -- reproductor: solo reproducir

-- Crear usuario administrador (password: admin123)
INSERT INTO usuarios (usuario, password, rol_id) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);
```

## 7. Configurar Variables de Entorno

Tu proyecto ya tiene un archivo `.env`. Solo actualízalo:

```bash
sudo nano /var/www/html/App_Estacion/.env
```

Cambiar estos valores:
```env
DB_HOST=localhost
DB_USER=app_user
DB_PASSWORD=tu_contraseña_segura
DB_NAME=app_estacion
BASE_URL=/App_Estacion
DEBUG_MODE=true
```

## 8. Configurar Firewall (Ubuntu)

```bash
# Habilitar UFW
sudo ufw enable

# Permitir SSH, HTTP y HTTPS
sudo ufw allow ssh
sudo ufw allow 80
sudo ufw allow 443

# Verificar estado
sudo ufw status
```

## 9. Verificación Final

```bash
# Verificar que los servicios estén activos
sudo systemctl status apache2 mysql

# Probar acceso local a la aplicación
curl -I http://localhost/App_Estacion/

# Si hay problemas, revisar logs:
sudo tail -f /var/log/apache2/error.log

# Verificar permisos de archivos
ls -la /var/www/html/App_Estacion/
```

## 10. Acceso desde Red Local

```bash
# Obtener IP del servidor
ip addr show | grep "inet " | grep -v 127.0.0.1
```

**Acceder desde otros dispositivos:**
- **URL**: `http://IP_DEL_SERVIDOR/App_Estacion/`
- **Usuario**: `admin`
- **Contraseña**: `admin123`

**⚠️ Importante**: Cambiar la contraseña por defecto después del primer acceso por seguridad.

## Ventajas de Ubuntu Server:

✅ **Audio funciona mejor** - PulseAudio más accesible
✅ **Menos restricciones** - No SELinux como Fedora  
✅ **Más documentación** - Comunidad más grande
✅ **LTS disponible** - Soporte a largo plazo
✅ **Instalación simple** - Menos configuración manual

## Arranque Automático:

✅ **Los servicios se inician automáticamente** después de reiniciar el servidor gracias a:
```bash
sudo systemctl enable apache2 mysql
```

**Verificar que están habilitados:**
```bash
# Ver servicios habilitados
sudo systemctl is-enabled apache2 mysql

# Ver estado después de reinicio
sudo systemctl status apache2 mysql
```

**Si algún servicio no arranca automáticamente:**
```bash
# Habilitar manualmente
sudo systemctl enable apache2
sudo systemctl enable mysql
```

## Solución de Problemas Comunes:

### No se puede acceder a la aplicación:
```bash
# Verificar servicios
sudo systemctl status apache2 mysql

# Reiniciar si es necesario
sudo systemctl restart apache2 mysql

# Verificar firewall
sudo ufw status
```

### Audio no se reproduce:
```bash
# Verificar que www-data esté en grupo audio
groups www-data

# Verificar PulseAudio
pactl info

# Reiniciar Apache
sudo systemctl restart apache2
```

### Error de base de datos:
```bash
# Verificar conexión
mysql -u app_user -p app_estacion -e "SHOW TABLES;"

# Ver logs de MySQL
sudo tail -f /var/log/mysql/error.log
```

## Comandos de Mantenimiento:

```bash
# Reiniciar servicios
sudo systemctl restart apache2 mysql

# Ver logs en tiempo real
sudo tail -f /var/log/apache2/error.log

# Actualizar sistema
sudo apt update && sudo apt upgrade

# Verificar espacio en disco
df -h

# Probar acceso después de reinicio
curl -I http://localhost/App_Estacion/
```