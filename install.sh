#!/bin/bash

# Script de instalación para Sistema de Audio Centralizado
# Para Ubuntu Server

echo "=== Instalación del Sistema de Audio Centralizado ==="

# Verificar que se ejecuta como root
if [ "$EUID" -ne 0 ]; then
    echo "Por favor ejecuta este script como root (sudo)"
    exit 1
fi

# Actualizar sistema
echo "Actualizando sistema..."
apt update && apt upgrade -y

# Instalar dependencias del sistema
echo "Instalando dependencias del sistema..."
apt install -y python3 python3-pip python3-venv alsa-utils pulseaudio pulseaudio-utils

# Crear usuario para el servicio
echo "Creando usuario para el servicio..."
useradd -r -s /bin/false -d /opt/audio-centralizado -G audio,pulse-access audio-server

# Crear directorio de instalación
echo "Creando directorios..."
mkdir -p /opt/audio-centralizado
mkdir -p /opt/audio-centralizado/audio_files
mkdir -p /opt/audio-centralizado/static/css
mkdir -p /opt/audio-centralizado/static/js
mkdir -p /opt/audio-centralizado/templates

# Copiar archivos del proyecto
echo "Copiando archivos del proyecto..."
cp app.py /opt/audio-centralizado/
cp requirements.txt /opt/audio-centralizado/
cp -r templates/* /opt/audio-centralizado/templates/
cp -r static/* /opt/audio-centralizado/static/

# Instalar dependencias de Python
echo "Instalando dependencias de Python..."
cd /opt/audio-centralizado
python3 -m pip install -r requirements.txt

# Configurar permisos
echo "Configurando permisos..."
chown -R audio-server:audio /opt/audio-centralizado
chmod -R 755 /opt/audio-centralizado
chmod 644 /opt/audio-centralizado/audio_files

# Instalar servicio systemd
echo "Instalando servicio systemd..."
cp audio-centralizado.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable audio-centralizado
systemctl start audio-centralizado

# Configurar firewall (si ufw está activo)
if systemctl is-active --quiet ufw; then
    echo "Configurando firewall..."
    ufw allow 5000/tcp
fi

# Mostrar estado
echo "=== Instalación completada ==="
echo "El servicio está corriendo en el puerto 5000"
echo "Accede desde cualquier dispositivo en la red local usando:"
echo "http://$(hostname -I | awk '{print $1}'):5000"
echo ""
echo "Comandos útiles:"
echo "  systemctl status audio-centralizado  # Ver estado"
echo "  systemctl stop audio-centralizado    # Detener servicio"
echo "  systemctl start audio-centralizado   # Iniciar servicio"
echo "  systemctl restart audio-centralizado # Reiniciar servicio"
echo "  journalctl -u audio-centralizado -f  # Ver logs en tiempo real"