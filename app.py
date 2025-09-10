#!/usr/bin/env python3
"""
Sistema de Audio Centralizado para Linux
Servidor web para control remoto de audio en red local
"""

import os
import json
import glob
from flask import Flask, render_template, request, jsonify, redirect, url_for
import pygame
import threading
import time

app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = 'audio_files'
app.config['MAX_CONTENT_LENGTH'] = 100 * 1024 * 1024  # 100MB max file size

# Initialize pygame mixer for audio playback
try:
    pygame.mixer.init()
    audio_available = True
    print("Audio system initialized successfully")
except pygame.error as e:
    audio_available = False
    print(f"Warning: Audio system not available: {e}")
    print("The web interface will work, but audio playback will be simulated")

# Global variables for audio control
current_song = None
is_playing = False
is_paused = False
volume = 0.7
playlist = []
current_index = 0

# Ensure audio folder exists
os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)

def get_audio_files():
    """Get list of available audio files"""
    audio_extensions = ['*.mp3', '*.wav', '*.ogg', '*.m4a']
    files = []
    for ext in audio_extensions:
        files.extend(glob.glob(os.path.join(app.config['UPLOAD_FOLDER'], ext)))
    return [os.path.basename(f) for f in files]

def update_playlist():
    """Update the current playlist with available files"""
    global playlist
    playlist = get_audio_files()

@app.route('/')
def index():
    """Main page with audio control interface"""
    update_playlist()
    return render_template('index.html', 
                         playlist=playlist, 
                         current_song=current_song,
                         is_playing=is_playing,
                         is_paused=is_paused,
                         volume=int(volume * 100))

@app.route('/api/play/<filename>')
def play_audio(filename):
    """Play specific audio file"""
    global current_song, is_playing, is_paused, current_index
    
    try:
        file_path = os.path.join(app.config['UPLOAD_FOLDER'], filename)
        if os.path.exists(file_path):
            if audio_available:
                pygame.mixer.music.load(file_path)
                pygame.mixer.music.play()
            else:
                # Simulate playback when no audio device is available
                print(f"Simulating playback of: {filename}")
                
            current_song = filename
            is_playing = True
            is_paused = False
            
            # Update current index in playlist
            if filename in playlist:
                current_index = playlist.index(filename)
            
            return jsonify({'status': 'success', 'message': f'Playing {filename}'})
        else:
            return jsonify({'status': 'error', 'message': 'File not found'})
    except Exception as e:
        return jsonify({'status': 'error', 'message': str(e)})

@app.route('/api/pause')
def pause_audio():
    """Pause/unpause audio playback"""
    global is_paused
    
    try:
        if is_playing:
            if is_paused:
                if audio_available:
                    pygame.mixer.music.unpause()
                else:
                    print("Simulating resume")
                is_paused = False
                return jsonify({'status': 'success', 'message': 'Resumed'})
            else:
                if audio_available:
                    pygame.mixer.music.pause()
                else:
                    print("Simulating pause")
                is_paused = True
                return jsonify({'status': 'success', 'message': 'Paused'})
        else:
            return jsonify({'status': 'error', 'message': 'No audio playing'})
    except Exception as e:
        return jsonify({'status': 'error', 'message': str(e)})

@app.route('/api/stop')
def stop_audio():
    """Stop audio playback"""
    global current_song, is_playing, is_paused
    
    try:
        if audio_available:
            pygame.mixer.music.stop()
        else:
            print("Simulating stop")
        current_song = None
        is_playing = False
        is_paused = False
        return jsonify({'status': 'success', 'message': 'Stopped'})
    except Exception as e:
        return jsonify({'status': 'error', 'message': str(e)})

@app.route('/api/volume/<int:vol>')
def set_volume(vol):
    """Set audio volume (0-100)"""
    global volume
    
    try:
        volume = max(0, min(100, vol)) / 100.0
        if audio_available:
            pygame.mixer.music.set_volume(volume)
        else:
            print(f"Simulating volume set to {int(volume * 100)}%")
        return jsonify({'status': 'success', 'message': f'Volume set to {int(volume * 100)}%'})
    except Exception as e:
        return jsonify({'status': 'error', 'message': str(e)})

@app.route('/api/next')
def next_song():
    """Play next song in playlist"""
    global current_index
    
    if playlist:
        current_index = (current_index + 1) % len(playlist)
        return play_audio(playlist[current_index])
    else:
        return jsonify({'status': 'error', 'message': 'No playlist available'})

@app.route('/api/previous')
def previous_song():
    """Play previous song in playlist"""
    global current_index
    
    if playlist:
        current_index = (current_index - 1) % len(playlist)
        return play_audio(playlist[current_index])
    else:
        return jsonify({'status': 'error', 'message': 'No playlist available'})

@app.route('/api/status')
def get_status():
    """Get current playback status"""
    return jsonify({
        'current_song': current_song,
        'is_playing': is_playing,
        'is_paused': is_paused,
        'volume': int(volume * 100),
        'playlist': playlist,
        'current_index': current_index
    })

@app.route('/upload', methods=['GET', 'POST'])
def upload_file():
    """Upload audio files"""
    if request.method == 'POST':
        if 'file' not in request.files:
            return redirect(request.url)
        
        file = request.files['file']
        if file.filename == '':
            return redirect(request.url)
        
        if file and file.filename.lower().endswith(('.mp3', '.wav', '.ogg', '.m4a')):
            filename = file.filename
            file.save(os.path.join(app.config['UPLOAD_FOLDER'], filename))
            update_playlist()
            return redirect(url_for('index'))
    
    return render_template('upload.html')

if __name__ == '__main__':
    print("Iniciando Sistema de Audio Centralizado...")
    print("Acceso desde la red local en: http://<IP_DEL_SERVIDOR>:5000")
    app.run(host='0.0.0.0', port=5000, debug=True)