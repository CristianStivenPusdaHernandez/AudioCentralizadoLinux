# Guía Completa: App Estación en Ubuntu Server

## Compatibilidad
- ✅ **Ubuntu Server/Desktop**: Objetivo principal - funciona perfectamente
- ✅ **Debian**: Mismos comandos que Ubuntu
- ⚠️ **Fedora/CentOS/RHEL**: Cambiar `httpd`, `apache`, comandos `dnf`
- ⚠️ **Arch/openSUSE**: Adaptar comandos de instalación

## 1. Instalación de Dependencias Base

### Fedora/CentOS/RHEL:
```bash
# Actualizar sistema
sudo dnf update -y

# Instalar Apache, PHP, MySQL y herramientas de audio
sudo dnf install -y httpd php php-mysqlnd php-json php-mbstring php-xml mariadb-server mariadb pulseaudio pulseaudio-utils ffmpeg

# Git (opcional - solo si vas a clonar desde repositorio)
sudo dnf install -y git

# Habilitar e iniciar servicios
sudo systemctl enable httpd mariadb
sudo systemctl start httpd mariadb
```

### Ubuntu/Debian:
```bash
# Actualizar sistema
sudo apt update

# Instalar dependencias básicas
sudo apt install -y apache2 php php-mysql php-json php-mbstring php-xml mysql-server ffmpeg

# Git (opcional - solo si vas a clonar desde repositorio)
sudo apt install -y git

# Habilitar e iniciar servicios
sudo systemctl enable apache2 mysql
sudo systemctl start apache2 mysql
```

## 2. Configuración de MySQL/MariaDB

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
# Configurar DocumentRoot
sudo nano /etc/httpd/conf/httpd.conf
```

Cambiar la línea:
```apache
DocumentRoot "/var/www/html"
```

```bash
# Habilitar mod_rewrite
sudo nano /etc/httpd/conf/httpd.conf
```

Buscar y cambiar:
```apache
<Directory "/var/www/html">
    AllowOverride All
    Require all granted
</Directory>
```

```bash
# Configurar firewall
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload

# Reiniciar Apache
sudo systemctl restart httpd
```

## 4. Instalar la Aplicación

### Opción A: Clonar desde repositorio (requiere git)
```bash
cd /var/www/html
sudo git clone https://github.com/tu_usuario/App_Estacion.git
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
# Cambiar propietario
sudo chown -R apache:apache /var/www/html/App_Estacion
sudo chmod -R 755 /var/www/html/App_Estacion
```

## 5. Configuración de la Base de Datos

### Opción A: Importar desde archivo SQL (recomendado)

Si tienes un archivo de backup (.sql), úsalo:

```bash
# Importar desde cualquier ubicación
mysql -u app_user -p app_estacion < /ruta/completa/al/archivo.sql

# Ejemplos comunes:
mysql -u app_user -p app_estacion < ~/backup_app_estacion.sql
mysql -u app_user -p app_estacion < /home/usuario/Descargas/app_estacion.sql
mysql -u app_user -p app_estacion < /media/usb/backup.sql
```

### Opción B: Crear tablas manualmente (si no tienes backup)

Solo si no tienes archivo SQL:

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

## 6. Configurar Variables de Entorno

Tu proyecto usa archivo `.env`. Actualízalo:

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

## 7. Configuración de Audio en Fedora

```bash
# Instalar PulseAudio (si no está instalado)
sudo dnf install -y pulseaudio pulseaudio-utils alsa-utils

# Iniciar PulseAudio
pulseaudio --start

# Verificar que funciona
pactl info

# Probar reproducción de audio
speaker-test -t sine -f 1000 -l 1

# Instalar ffmpeg para reproducción
sudo dnf install -y ffmpeg

# Probar ffplay
echo "Probando ffplay..."
# ffplay archivo_de_prueba.mp3
```

## 8. Configuración de Permisos SELinux y Audio (Fedora específico)

```bash
# Permitir que Apache ejecute comandos del sistema
sudo setsebool -P httpd_execmem 1
sudo setsebool -P httpd_can_network_connect 1

# Permitir que Apache escriba archivos temporales
sudo chcon -R -t httpd_exec_t /var/www/html/App_Estacion/
sudo chcon -R -t httpd_rw_content_t /tmp/

# Configurar permisos para archivos de estado
sudo mkdir -p /var/www/html/App_Estacion/config
sudo chown apache:apache /var/www/html/App_Estacion/config
sudo chmod 755 /var/www/html/App_Estacion/config

# Agregar apache al grupo audio
sudo usermod -a -G audio apache

# Si hay problemas con SELinux, temporalmente:
# sudo setenforce 0  # Solo para pruebas
```

## 9. Configuración de Red para Acceso Externo

```bash
# Obtener IP del servidor
ip addr show

# Configurar firewall para acceso desde red local
sudo firewall-cmd --permanent --add-rich-rule='rule family="ipv4" source address="192.168.1.0/24" accept'
sudo firewall-cmd --reload

# Verificar estado del firewall
sudo firewall-cmd --list-all
```

## 10. Verificación y Pruebas

```bash
# Verificar servicios
sudo systemctl status httpd mariadb

# Verificar logs de Apache
sudo tail -f /var/log/httpd/error_log

# Probar acceso local
curl http://localhost/App_Estacion/

# Verificar permisos de archivos
ls -la /var/www/html/App_Estacion/
```

## 11. Acceso desde Otros Dispositivos

1. **Obtener IP del servidor Fedora:**
```bash
ip addr show | grep "inet " | grep -v 127.0.0.1
```

2. **Acceder desde otros dispositivos:**
   - URL: `http://IP_DEL_SERVIDOR/App_Estacion/`
   - Ejemplo: `http://192.168.1.100/App_Estacion/`

3. **Credenciales por defecto:**
   - Usuario: `admin`
   - Contraseña: `password`

## 12. Solución de Problemas Comunes

### Audio no se reproduce:
```bash
# Verificar PulseAudio/PipeWire
pactl info

# Reiniciar servicios de audio
systemctl --user restart pipewire-pulse

# Verificar permisos de usuario apache
sudo usermod -a -G audio apache
sudo systemctl restart httpd

# Probar reproducción manual
ffplay /ruta/a/archivo/audio.m4a
```

### Problemas de permisos:
```bash
# Corregir propietario
sudo chown -R apache:apache /var/www/html/App_Estacion/

# Corregir permisos
sudo chmod -R 755 /var/www/html/App_Estacion/
sudo chmod -R 644 /var/www/html/App_Estacion/config/
```

### Problemas de base de datos:
```bash
# Verificar conexión
mysql -u app_user -p app_estacion -e "SHOW TABLES;"

# Verificar logs de MySQL
sudo tail -f /var/log/mariadb/mariadb.log
```

## 13. Configuración de Producción (Opcional)

```bash
# Deshabilitar debug mode
sudo nano /var/www/html/App_Estacion/config/config.php
# Cambiar: define('DEBUG_MODE', false);

# Configurar SSL (opcional)
sudo dnf install -y certbot python3-certbot-apache
sudo certbot --apache

# Configurar backup automático
sudo crontab -e
# Agregar: 0 2 * * * mysqldump -u app_user -p'contraseña' app_estacion > /backup/app_estacion_$(date +\%Y\%m\%d).sql
```

## Notas Importantes:

1. **Cambiar contraseñas por defecto** antes de usar en producción
2. **El audio se reproduce en el servidor**, no en los dispositivos cliente
3. **Configurar firewall** según tus necesidades de seguridad
4. **Hacer backups regulares** de la base de datos
5. **Monitorear logs** para detectar problemas
6. **Configuración de audio**: En Fedora usar `PULSE_SERVER=127.0.0.1` en lugar del socket directo
7. **Formatos soportados**: MP3, M4A, WAV, OGG - usar extensión correcta en archivos temporales

## Comandos de Mantenimiento:

```bash
# Reiniciar servicios
sudo systemctl restart httpd mariadb

# Ver logs en tiempo real
sudo tail -f /var/log/httpd/error_log

# Limpiar archivos temporales
sudo find /tmp -name "audio_*" -type f -delete

# Verificar espacio en disco
df -h
```