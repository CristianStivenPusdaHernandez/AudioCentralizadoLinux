# AudioCentralizadoLinux

## Sistema de Audio Centralizado para Linux

Sistema web para reproducir audios de forma centralizada en el servidor, permitiendo control remoto desde cualquier dispositivo en la red local. Diseñado específicamente para Ubuntu Server.

### 🎵 Características

- **Control Remoto**: Acceso desde cualquier dispositivo en la red local
- **Interfaz Web**: Control intuitivo a través del navegador
- **Gestión de Archivos**: Subida y administración de archivos de audio
- **Múltiples Formatos**: Soporte para MP3, WAV, OGG, M4A
- **Control de Volumen**: Ajuste remoto del volumen del servidor
- **Lista de Reproducción**: Navegación fácil entre canciones
- **Servicio del Sistema**: Ejecución automática como servicio de Ubuntu

### 🚀 Instalación Rápida

```bash
# Clonar el repositorio
git clone https://github.com/CristianStivenPusdaHernandez/AudioCentralizadoLinux.git
cd AudioCentralizadoLinux

# Ejecutar instalación automatizada (requiere sudo)
sudo ./install.sh
```

### 📋 Requisitos del Sistema

- Ubuntu Server 20.04+ (o distribución compatible)
- Python 3.8+
- Acceso a audio del sistema (ALSA/PulseAudio)
- Red local configurada

### 🔧 Instalación Manual

Si prefieres instalar manualmente:

1. **Instalar dependencias del sistema:**
```bash
sudo apt update
sudo apt install python3 python3-pip alsa-utils pulseaudio
```

2. **Instalar dependencias de Python:**
```bash
pip3 install -r requirements.txt
```

3. **Ejecutar la aplicación:**
```bash
python3 app.py
```

### 🖥️ Uso

1. **Acceder a la interfaz web:**
   - Abre un navegador en cualquier dispositivo de la red
   - Navega a: `http://IP_DEL_SERVIDOR:5000`

2. **Subir archivos de audio:**
   - Haz clic en "Subir Archivos"
   - Selecciona archivos MP3, WAV, OGG o M4A
   - Los archivos estarán disponibles inmediatamente

3. **Controlar la reproducción:**
   - **Reproducir**: Haz clic en cualquier canción de la lista
   - **Pausar/Reanudar**: Botón de pausa en los controles
   - **Detener**: Botón de stop
   - **Siguiente/Anterior**: Navegación por la lista
   - **Volumen**: Deslizador de control de volumen

### ⌨️ Atajos de Teclado

- **Espacio**: Reproducir/Pausar
- **Flecha Derecha**: Siguiente canción
- **Flecha Izquierda**: Canción anterior
- **S**: Detener reproducción

### 🔧 Administración del Servicio

```bash
# Ver estado del servicio
sudo systemctl status audio-centralizado

# Iniciar/Detener/Reiniciar servicio
sudo systemctl start audio-centralizado
sudo systemctl stop audio-centralizado
sudo systemctl restart audio-centralizado

# Ver logs en tiempo real
sudo journalctl -u audio-centralizado -f

# Deshabilitar inicio automático
sudo systemctl disable audio-centralizado
```

### 📁 Estructura del Proyecto

```
AudioCentralizadoLinux/
├── app.py                      # Aplicación Flask principal
├── requirements.txt            # Dependencias de Python
├── install.sh                  # Script de instalación automatizada
├── audio-centralizado.service  # Archivo de servicio systemd
├── templates/
│   ├── index.html             # Interfaz principal
│   └── upload.html            # Página de subida de archivos
├── static/
│   ├── css/
│   │   └── style.css          # Estilos CSS
│   └── js/
│       └── app.js             # Funcionalidad JavaScript
└── audio_files/               # Directorio de archivos de audio
```

### 🌐 API Endpoints

- `GET /` - Interfaz principal
- `GET /upload` - Página de subida de archivos
- `POST /upload` - Subir archivo de audio
- `GET /api/play/<filename>` - Reproducir canción específica
- `GET /api/pause` - Pausar/reanudar reproducción
- `GET /api/stop` - Detener reproducción
- `GET /api/next` - Siguiente canción
- `GET /api/previous` - Canción anterior
- `GET /api/volume/<0-100>` - Establecer volumen
- `GET /api/status` - Estado actual de reproducción

### 🛠️ Configuración

El sistema se configura automáticamente, pero puedes modificar:

- **Puerto**: Cambiar `port=5000` en `app.py`
- **Directorio de audio**: Modificar `UPLOAD_FOLDER` en `app.py`
- **Tamaño máximo de archivo**: Ajustar `MAX_CONTENT_LENGTH` en `app.py`

### 🔒 Seguridad

- El servicio se ejecuta con un usuario dedicado (`audio-server`)
- Acceso limitado solo a la red local
- Validación de tipos de archivo en subidas
- Límite de tamaño de archivo (100MB por defecto)

### 🐛 Solución de Problemas

**No se reproduce audio:**
- Verificar que PulseAudio esté funcionando: `pulseaudio --check`
- Comprobar permisos de audio: `groups audio-server`

**No se puede acceder desde otros dispositivos:**
- Verificar firewall: `sudo ufw status`
- Comprobar que el servicio esté en puerto 5000: `netstat -tlnp | grep 5000`

**Archivos no se suben:**
- Verificar permisos del directorio: `ls -la audio_files/`
- Comprobar espacio en disco: `df -h`

### 🤝 Contribución

Las contribuciones son bienvenidas. Por favor:

1. Fork el repositorio
2. Crea una rama para tu feature
3. Haz commit de tus cambios
4. Envía un Pull Request

### 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver `LICENSE` para más detalles.

### 👨‍💻 Autor

Cristian Stiven Pusda Hernández

---

¿Necesitas ayuda? Abre un issue en GitHub o consulta la documentación completa.
