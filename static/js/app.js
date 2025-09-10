// Sistema de Audio Centralizado - JavaScript Frontend

let currentSong = null;
let isPlaying = false;
let isPaused = false;

// Show status message
function showStatus(message, type = 'success') {
    const statusEl = document.getElementById('status-message');
    statusEl.textContent = message;
    statusEl.className = type;
    
    setTimeout(() => {
        statusEl.style.opacity = '0';
    }, 3000);
}

// Play specific song
function playSong(filename) {
    fetch(`/api/play/${encodeURIComponent(filename)}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                currentSong = filename;
                isPlaying = true;
                isPaused = false;
                updateUI();
                showStatus(data.message, 'success');
            } else {
                showStatus(data.message, 'error');
            }
        })
        .catch(error => {
            showStatus('Error de conexión', 'error');
            console.error('Error:', error);
        });
}

// Toggle play/pause
function togglePlayPause() {
    if (!currentSong) {
        showStatus('Selecciona una canción primero', 'error');
        return;
    }
    
    fetch('/api/pause')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                isPaused = !isPaused;
                updateUI();
                showStatus(data.message, 'success');
            } else {
                showStatus(data.message, 'error');
            }
        })
        .catch(error => {
            showStatus('Error de conexión', 'error');
            console.error('Error:', error);
        });
}

// Stop audio
function stopAudio() {
    fetch('/api/stop')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                currentSong = null;
                isPlaying = false;
                isPaused = false;
                updateUI();
                showStatus(data.message, 'success');
            } else {
                showStatus(data.message, 'error');
            }
        })
        .catch(error => {
            showStatus('Error de conexión', 'error');
            console.error('Error:', error);
        });
}

// Next song
function nextSong() {
    fetch('/api/next')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                updateStatus();
                showStatus('Siguiente canción', 'success');
            } else {
                showStatus(data.message, 'error');
            }
        })
        .catch(error => {
            showStatus('Error de conexión', 'error');
            console.error('Error:', error);
        });
}

// Previous song
function previousSong() {
    fetch('/api/previous')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                updateStatus();
                showStatus('Canción anterior', 'success');
            } else {
                showStatus(data.message, 'error');
            }
        })
        .catch(error => {
            showStatus('Error de conexión', 'error');
            console.error('Error:', error);
        });
}

// Set volume
function setVolume(volume) {
    fetch(`/api/volume/${volume}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('volume-display').textContent = `${volume}%`;
                showStatus(data.message, 'success');
            } else {
                showStatus(data.message, 'error');
            }
        })
        .catch(error => {
            showStatus('Error de conexión', 'error');
            console.error('Error:', error);
        });
}

// Refresh playlist
function refreshPlaylist() {
    location.reload();
}

// Update UI elements
function updateUI() {
    const currentSongEl = document.getElementById('current-song');
    const playPauseBtn = document.getElementById('play-pause-btn');
    
    if (currentSong) {
        if (isPlaying && !isPaused) {
            currentSongEl.innerHTML = `<i class="fas fa-play-circle"></i> ${currentSong}`;
            playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
        } else if (isPaused) {
            currentSongEl.innerHTML = `<i class="fas fa-pause-circle"></i> ${currentSong} (Pausado)`;
            playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
        } else {
            currentSongEl.innerHTML = `<i class="fas fa-stop-circle"></i> ${currentSong}`;
            playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
        }
    } else {
        currentSongEl.innerHTML = '<i class="fas fa-stop-circle"></i> Sin reproducción';
        playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
    }
    
    // Update playlist highlighting
    document.querySelectorAll('.playlist-item').forEach(item => {
        const songName = item.querySelector('.song-name').textContent;
        const indicator = item.querySelector('.playing-indicator');
        
        if (songName === currentSong && isPlaying) {
            if (!indicator) {
                const newIndicator = document.createElement('i');
                newIndicator.className = 'fas fa-volume-up playing-indicator';
                item.appendChild(newIndicator);
            }
            item.style.borderColor = '#28a745';
            item.style.background = '#f8fff8';
        } else {
            if (indicator) {
                indicator.remove();
            }
            item.style.borderColor = 'transparent';
            item.style.background = '';
        }
    });
}

// Update status from server
function updateStatus() {
    fetch('/api/status')
        .then(response => response.json())
        .then(data => {
            currentSong = data.current_song;
            isPlaying = data.is_playing;
            isPaused = data.is_paused;
            
            // Update volume display
            const volumeSlider = document.getElementById('volume');
            const volumeDisplay = document.getElementById('volume-display');
            if (volumeSlider && volumeDisplay) {
                volumeSlider.value = data.volume;
                volumeDisplay.textContent = `${data.volume}%`;
            }
            
            updateUI();
        })
        .catch(error => {
            console.error('Error updating status:', error);
        });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
    switch(event.code) {
        case 'Space':
            event.preventDefault();
            togglePlayPause();
            break;
        case 'ArrowRight':
            event.preventDefault();
            nextSong();
            break;
        case 'ArrowLeft':
            event.preventDefault();
            previousSong();
            break;
        case 'KeyS':
            event.preventDefault();
            stopAudio();
            break;
    }
});

// Update status periodically
setInterval(updateStatus, 5000);

// Initial status update
document.addEventListener('DOMContentLoaded', function() {
    updateStatus();
    
    // Add click handlers for playlist items
    document.querySelectorAll('.playlist-item').forEach(item => {
        item.addEventListener('click', function() {
            const songName = this.querySelector('.song-name').textContent;
            playSong(songName);
        });
    });
});

// File upload preview
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('file');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const fileName = this.files[0]?.name;
            if (fileName) {
                const label = this.nextElementSibling;
                label.querySelector('span').textContent = fileName;
                showStatus(`Archivo seleccionado: ${fileName}`, 'success');
            }
        });
    }
});