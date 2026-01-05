let userSession = null;
const appContainer = document.querySelector('.app-container');
const loginScreen = document.getElementById('login-screen');

// Muestra la pantalla de inicio de sesión
const showLogin = () => {
    loginScreen.style.display = 'flex';
    appContainer.classList.add('hidden');
};

// Muestra la aplicación principal
const showApp = (userData) => {
    loginScreen.style.display = 'none';
    appContainer.classList.remove('hidden');
    if (userData) {
        document.getElementById('user-name').textContent = userData.usuario;
        userSession = userData; // Guardar datos del usuario
        
        // Mostrar/ocultar FAB según permisos
        // Solo roles con permisos pueden subir audios
        const fab = document.querySelector('.fab');
        if (userData.permisos && userData.permisos.includes('subir_audio') && userData.rol !== 'reproductor') {
            fab.classList.remove('hidden');
        } else {
            fab.classList.add('hidden');
        }
        
        // Mostrar botón de usuarios solo para administradores
        const usersBtn = document.getElementById('users-btn');
        if (userData.rol === 'administrador') {
            usersBtn.style.display = 'inline-block';
        } else {
            usersBtn.style.display = 'none';
        }
}
    loadAudios(); // Cargar audios al mostrar la app
    startStatusCheck(); // Iniciar verificación de estado
};

// Función para obtener duración de audio (fallback para archivos locales)
const getAudioDuration = (url) => {
    return new Promise((resolve) => {
        const audio = new Audio(url);
        audio.addEventListener('loadedmetadata', () => {
            resolve(audio.duration || 0);
        });
        audio.addEventListener('error', () => {
            resolve(0);
        });
    });
};

// Verifica la sesión al cargar la página
const checkSession = async () => {
    try {
        const response = await fetch('/App_Estacion/public/api/auth', { 
            method: 'GET',
            credentials: 'include'
        });
        if (response.ok) {
            userSession = await response.json();
            showApp(userSession);
        } else {
            showLogin();
        }
    } catch (e) {
        // En caso de error de red, muestra la pantalla de login
        showLogin();
        console.error('Error de red al verificar la sesión:', e);
    }
};

// Función para cargar audios
const loadAudios = async (sortBy = 'nombre', order = 'asc') => {
    try {
        // Construir URL con parámetros
        let url = '/App_Estacion/public/api/audios?';
        const params = new URLSearchParams();
        params.append('sort', sortBy);
        params.append('order', order);
        url += params.toString();
        
        const response = await fetch(url, { 
            method: 'GET',
            credentials: 'include'
        });
        
        const responseText = await response.text();
        console.log('Respuesta cruda del servidor:', responseText);
        
        if (!response.ok) {
            console.error('Error al cargar audios:', response.status, responseText);
            return;
        }
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Error al parsear JSON:', parseError);
            console.error('Respuesta del servidor:', responseText);
            return;
        }
        
        console.log('Respuesta del servidor:', data);
        
        const audios = data.audios || data; // Manejar ambos formatos

        if (!Array.isArray(audios)) {
            console.error('Los audios no son un array:', audios);
            return;
        }

        // Limpiar arrays de categorías
        audiosByCategory = {};
        
        // Limpiar todas las categorías existentes
        const allCategories = document.querySelectorAll('.announcements-section .category');
        allCategories.forEach(category => category.remove());
        
        // Obtener categorías únicas para el select
        const categorias = [...new Set(audios.map(audio => audio.categoria))];
        const categorySelect = document.getElementById('audio-category');
        if (categorySelect) {
            // Limpiar opciones dinámicas anteriores
            const dynamicOptions = categorySelect.querySelectorAll('.dynamic-option');
            dynamicOptions.forEach(option => option.remove());
            
            // Agregar categorías existentes
            categorias.forEach(categoria => {
                if (categoria !== 'ANUNCIOS GENERALES' && categoria !== 'ANUNCIOS DEL TREN') {
                    const option = document.createElement('option');
                    option.value = categoria;
                    option.textContent = categoria;
                    option.className = 'dynamic-option';
                    categorySelect.insertBefore(option, categorySelect.lastElementChild);
                }
            });
        }

        console.log(`Cargando ${audios.length} audios`);
        audios.forEach(audio => {
            const audioItem = document.createElement('div');
            audioItem.classList.add('audio-item');
            
            // Verificar permisos para mostrar botones de edición
            // Solo roles con permisos específicos pueden editar/eliminar
            const canEdit = userSession && userSession.permisos && userSession.permisos.includes('editar_audio') && userSession.rol !== 'reproductor';
            const canDelete = userSession && userSession.permisos && userSession.permisos.includes('eliminar_audio') && userSession.rol !== 'reproductor';
            
            const editButton = canEdit ? `<button class="edit-button" data-id="${audio.id}"><i class="fa-solid fa-pencil-alt"></i></button>` : '';
            const deleteButton = canDelete ? `<button class="delete-button" data-id="${audio.id}"><i class="fa-solid fa-times"></i></button>` : '';
            
            audioItem.innerHTML = `
                <button class="audio-button" data-id="${audio.id}" data-url="${audio.url}"><i class="fa-solid fa-volume-up"></i> ${audio.nombre}</button>
                ${editButton}
                ${deleteButton}
            `;
            
            // Asignar event listeners SIEMPRE
            const audioButton = audioItem.querySelector('.audio-button');
            audioButton.addEventListener('click', () => playAudio(audio.id, audio.url, audio.nombre));
            
            if (canEdit) {
                const editBtn = audioItem.querySelector('.edit-button');
                if (editBtn) editBtn.addEventListener('click', () => editAudio(audio.id));
            }
            
            if (canDelete) {
                const deleteBtn = audioItem.querySelector('.delete-button');
                if (deleteBtn) deleteBtn.addEventListener('click', () => deleteAudio(audio.id));
            }

            // Inicializar categoría si no existe
            if (!audiosByCategory[audio.categoria]) {
                audiosByCategory[audio.categoria] = [];
            }
            audiosByCategory[audio.categoria].push(audio);
            
            // Buscar sección existente o crear nueva
            let categorySection = document.querySelector(`[data-categoria="${audio.categoria}"]`);
            if (!categorySection) {
                categorySection = document.createElement('div');
                categorySection.className = 'category';
                categorySection.setAttribute('data-categoria', audio.categoria);
                const canEditCategory = userSession && (userSession.rol === 'administrador' || userSession.rol === 'operador');
                const editCategoryButton = canEditCategory ? `<button class="edit-category-button" data-categoria="${audio.categoria}" title="Editar categoría"><i class="fa-solid fa-pencil"></i></button>` : '';
                
                categorySection.innerHTML = `
                    <div class="category-header">
                        <h3><i class="fa-solid fa-music"></i> ${audio.categoria}</h3>
                        <div class="category-buttons">
                            ${editCategoryButton}
                            <button class="reproducir-todo">Reproducir Todo</button>
                        </div>
                    </div>
                    <div class="button-grid"></div>
                `;
                document.querySelector('.announcements-section').appendChild(categorySection);
            }
            const categoryGrid = categorySection.querySelector('.button-grid');
            categoryGrid.appendChild(audioItem);
        });
        
        // Agregar event listeners a los botones "Reproducir Todo"
        document.querySelectorAll('.reproducir-todo').forEach(btn => {
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
        });
        
        document.querySelectorAll('.reproducir-todo').forEach(btn => {
            const categorySection = btn.closest('.category');
            const categoria = categorySection.getAttribute('data-categoria');
            if (categoria) {
                btn.addEventListener('click', () => playAllCategory(categoria));
            }
        });
        // Event listeners para botones de editar categoría (administradores y operadores)
        if (userSession && (userSession.rol === 'administrador' || userSession.rol === 'operador')) {
            document.querySelectorAll('.edit-category-button').forEach(btn => {
                btn.addEventListener('click', () => editCategory(btn.dataset.categoria));
            });
        }
    } catch (error) {
        console.error('Error al obtener audios:', error);
    }
};

// Variables globales para audio
let currentAudio = null;
let isPlaying = false;
let currentAudioTitle = '';
let isRepeating = false;
let currentAudioId = null;
let currentAudioUrl = '';
let audiosByCategory = {
    'ANUNCIOS GENERALES': [],
    'ANUNCIOS DEL TREN': []
};
let statusCheckInterval = null;
let playAllInterval = null;
let isPlayingAll = false;
let playAllTimeoutId = null;
let justStartedPlaying = false;


const updatePlayButton = () => {
    const playButton = document.querySelector('.player-section .play-button i');
    if (playButton) {
        playButton.className = isPlaying ? 'fa-solid fa-pause' : 'fa-solid fa-play';
    }
};

const updateProgressBar = () => {
    if (!currentAudio) return;
    const progressBar = document.getElementById('progress-fill');
    const currentTimeEl = document.getElementById('current-time');
    const totalTimeEl = document.getElementById('total-time');
    const audioTitleEl = document.getElementById('audio-title');
    const audioProgressEl = document.getElementById('audio-progress');
    if (currentAudio.duration) {
        const progress = (currentAudio.currentTime / currentAudio.duration) * 100;
        progressBar.style.width = progress + '%';
        currentTimeEl.textContent = formatTime(currentAudio.currentTime);
        totalTimeEl.textContent = formatTime(currentAudio.duration);
        audioTitleEl.textContent = currentAudioTitle;
        audioProgressEl.classList.add('active');
    }
};

const formatTime = (seconds) => {
    if (!seconds || isNaN(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};

const hideProgressBar = () => {
    const audioProgressEl = document.getElementById('audio-progress');
    const audioTitleEl = document.getElementById('audio-title');
    audioProgressEl.classList.remove('active');
    audioTitleEl.textContent = 'Selecciona un audio';
};

const playAudio = async (id, url, title = 'Audio', forcePlay = false) => {
    try {
        
        const response = await fetch('/App_Estacion/public/api/player', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ audio_id: id, repeat: isRepeating, force_play: forcePlay })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Error parsing JSON:', responseText);
            throw new Error('Respuesta inválida del servidor');
        }
        
        // Verificar si hay un audio reproduciéndose
        if (result.audio_playing && !forcePlay) {
            const userConfirmed = confirm(result.message);
            if (userConfirmed) {
                // Reproducir con fuerza, deteniendo el actual
                return playAudio(id, url, title, true);
            } else {
                return; // No hacer nada si el usuario cancela
            }
        }
        
        console.log('Audio reproduciéndose en servidor:', result.message);
        
        // Guardar información del audio actual
        currentAudioId = id;
        currentAudioUrl = url;
        const audioTitle = result.title || title;
        const duration = result.duration || 0;
        
        // Actualizar UI inmediatamente
        isPlaying = true;
        currentAudioTitle = audioTitle;
        justStartedPlaying = true;
        
        document.getElementById('audio-title').textContent = `🔊 ${audioTitle} (${formatTime(duration)})`;
        document.getElementById('audio-progress').classList.add('active');
        
        // Mostrar duración total inmediatamente
        if (duration > 0) {
            document.getElementById('total-time').textContent = formatTime(duration);
            document.getElementById('current-time').textContent = '0:00';
            document.getElementById('progress-fill').style.width = '0%';
        }
        
        updatePlayButton();
        
        // Resetear bandera después de 3 segundos
        setTimeout(() => {
            justStartedPlaying = false;
        }, 3000);
        
    } catch (error) {
        console.error('Error de conexión:', error);
        alert('Error de conexión al servidor: ' + error.message);
    }
};

const stopAudio = async () => {
    try {
        // Detener secuencia "reproducir todo"
        isPlayingAll = false;
        if (playAllInterval) {
            clearInterval(playAllInterval);
            playAllInterval = null;
        }
        if (playAllTimeoutId) {
            clearTimeout(playAllTimeoutId);
            playAllTimeoutId = null;
        }
        

        const response = await fetch('/App_Estacion/public/api/player/stop', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({})
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Error parsing JSON:', responseText);
            // Si no es JSON válido, asumir que se detuvo correctamente
            result = { success: true, message: 'Audio detenido' };
        }
        
        console.log('Audio detenido:', result.message);
        
        // Actualizar interfaz inmediatamente
        isPlaying = false;
        currentAudioTitle = '';
        currentAudioId = null;
        updatePlayButton();
        hideProgressBar();
        
        // Limpiar barra de progreso
        document.getElementById('progress-fill').style.width = '0%';
        document.getElementById('current-time').textContent = '0:00';
        document.getElementById('total-time').textContent = '0:00';
        
    } catch (error) {
        console.error('Error al detener audio:', error);
        // Actualizar interfaz de todos modos
        isPlaying = false;
        currentAudioTitle = '';
        currentAudioId = null;
        updatePlayButton();
        hideProgressBar();
    }
};

// Verificar estado del reproductor cada 2 segundos
const checkPlayerStatus = async () => {
    try {
        const response = await fetch('/App_Estacion/public/api/player/status', {
            method: 'GET',
            credentials: 'include'
        });
        
        if (response.ok) {
            const text = await response.text();
            let status;
            
            try {
                status = JSON.parse(text);
            } catch (parseError) {
                console.error('Error parsing JSON:', text);
                return;
            }
            
            // Validar que status existe y tiene las propiedades necesarias
            if (!status || typeof status !== 'object') {
                console.error('Estado inválido:', status);
                return;
            }
            
            // Solo actualizar si el estado cambió para evitar conflictos
            const wasPlaying = isPlaying;
            const currentTitle = document.getElementById('audio-title').textContent;
            
            // Actualizar interfaz según el estado del servidor
            if (status.playing === true && status.title) {
                const titleWithDuration = status.duration > 0 ? 
                    `🔊 ${status.title} (${formatTime(status.duration)})` : 
                    `🔊 ${status.title}`;
                
                // Actualizar título y progreso
                document.getElementById('audio-title').textContent = titleWithDuration;
                document.getElementById('audio-progress').classList.add('active');
                
                // Mostrar progreso real si está disponible
                if (status.duration && status.duration > 0) {
                    const progress = Math.min((status.position / status.duration) * 100, 100);
                    document.getElementById('progress-fill').style.width = progress + '%';
                    document.getElementById('current-time').textContent = formatTime(status.position || 0);
                    document.getElementById('total-time').textContent = formatTime(status.duration || 0);
                } else {
                    document.getElementById('progress-fill').style.width = '10%';
                    document.getElementById('current-time').textContent = 'Cargando...';
                    document.getElementById('total-time').textContent = 'Obteniendo duración...';
                }
                
                // Actualizar estado global
                isPlaying = true;
                currentAudioTitle = status.title;
                currentAudioId = null;
                
                const repeatButton = document.getElementById('repeat-button');
                if (status.repeat !== undefined) {
                    isRepeating = status.repeat;
                    if (isRepeating) {
                        repeatButton.classList.add('active');
                    } else {
                        repeatButton.classList.remove('active');
                    }
                }
            } else if (status.paused) {
                // Audio pausado - mantener información pero cambiar estado
                const titleWithDuration = status.duration > 0 ? 
                    `⏸️ ${status.title} (${formatTime(status.duration)})` : 
                    `⏸️ ${status.title}`;
                
                document.getElementById('audio-title').textContent = titleWithDuration;
                document.getElementById('audio-progress').classList.add('active');
                
                // Mostrar progreso pausado
                if (status.duration && status.duration > 0) {
                    const progress = Math.min((status.position / status.duration) * 100, 100);
                    document.getElementById('progress-fill').style.width = progress + '%';
                    document.getElementById('current-time').textContent = formatTime(status.position || 0);
                    document.getElementById('total-time').textContent = formatTime(status.duration || 0);
                }
                
                // Actualizar estado global
                isPlaying = false;
                currentAudioTitle = status.title;
                
                // El botón de repetición ya está siempre visible
                const repeatButton = document.getElementById('repeat-button');
                
                // Sincronizar estado de repetición
                if (status.repeat !== undefined) {
                    isRepeating = status.repeat;
                    if (isRepeating) {
                        repeatButton.classList.add('active');
                    } else {
                        repeatButton.classList.remove('active');
                    }
                }
            } else {
                // Audio detenido completamente
                // SOLO ocultar si NO acabamos de iniciar y NO está reproduciendo localmente
                if (justStartedPlaying || (isPlaying && !wasPlaying)) {
                    // Mantener UI visible
                    return;
                }
                
                if (wasPlaying || isPlaying) {
                    document.getElementById('audio-title').textContent = 'Selecciona un audio';
                    document.getElementById('audio-progress').classList.remove('active');
                    document.getElementById('progress-fill').style.width = '0%';
                    document.getElementById('current-time').textContent = '0:00';
                    document.getElementById('total-time').textContent = '0:00';
                    
                    // Detener secuencia "Reproducir Todo" cuando el audio se detiene
                    isPlayingAll = false;
                    if (playAllInterval) {
                        clearInterval(playAllInterval);
                        playAllInterval = null;
                    }
                    if (playAllTimeoutId) {
                        clearTimeout(playAllTimeoutId);
                        playAllTimeoutId = null;
                    }
                    // Limpiar botones activos
                    document.querySelectorAll('.reproducir-todo').forEach(btn => btn.classList.remove('active-playall'));
                }
                isPlaying = false;
                currentAudioTitle = '';
                currentAudioId = null;
                
                // Sincronizar estado de repetición
                if (status.repeat !== undefined) {
                    isRepeating = status.repeat;
                    const repeatButton = document.getElementById('repeat-button');
                    if (isRepeating) {
                        repeatButton.classList.add('active');
                    } else {
                        repeatButton.classList.remove('active');
                    }
                }
                
                // Desactivar botón "Reproducir Todo" si no hay audio reproduciéndose

            }
            updatePlayButton();
        }
    } catch (error) {
        console.error('Error verificando estado:', error);
    }
};

const startStatusCheck = () => {
    if (statusCheckInterval) {
        clearInterval(statusCheckInterval);
    }
    statusCheckInterval = setInterval(checkPlayerStatus, 500); // Cada 500ms para sincronización rápida
    // Verificación inmediata
    checkPlayerStatus();
};

const stopStatusCheck = () => {
    if (statusCheckInterval) {
        clearInterval(statusCheckInterval);
        statusCheckInterval = null;
    }
};

const togglePlayPause = async () => {
    if (isPlaying) {
        // Pausar audio y secuencia si está activa
        if (playAllInterval) {
            clearInterval(playAllInterval);
            playAllInterval = null;
        }
        try {
            const response = await fetch('/App_Estacion/public/api/player/pause', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include'
            });
            if (response.ok) {
                const result = await response.json();
                console.log('Audio pausado:', result.message);
            }
        } catch (error) {
            console.error('Error al pausar audio:', error);
        }
    } else {
        // Verificar si hay audio pausado para reanudar
        try {
            const statusResponse = await fetch('/App_Estacion/public/api/player/status', {
                method: 'GET',
                credentials: 'include'
            });
            if (statusResponse.ok) {
                const status = await statusResponse.json();
                if (status.paused) {
                    // Reanudar audio pausado
                    const response = await fetch('/App_Estacion/public/api/player/resume', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'include'
                    });
                    if (response.ok) {
                        const result = await response.json();
                        console.log('Audio reanudado:', result.message);
                        // Reanudar secuencia si estaba activa
                        if (isPlayingAll && !playAllInterval) {
                            // Buscar la categoría y el índice actual
                            let categoriaActual = null;
                            let currentIndex = null;
                            // Buscar el audio actual en las categorías
                            for (const [cat, audios] of Object.entries(audiosByCategory)) {
                                const idx = audios.findIndex(a => a.id === currentAudioId);
                                if (idx !== -1) {
                                    categoriaActual = cat;
                                    currentIndex = idx;
                                    break;
                                }
                            }
                            if (categoriaActual !== null && currentIndex !== null) {
                                // Definir playNext local para continuar la secuencia
                                const audios = audiosByCategory[categoriaActual];
                                const playNext = async () => {
                                    if (!isPlayingAll || currentIndex >= audios.length) {
                                        isPlayingAll = false;
                                        return;
                                    }
                                    // Esperar a que termine el audio antes de reproducir el siguiente
                                    playAllInterval = setInterval(async () => {
                                        try {
                                            const statusResponse = await fetch('/App_Estacion/public/api/player/status', {
                                                method: 'GET',
                                                credentials: 'include'
                                            });
                                            if (statusResponse.ok) {
                                                const status = await statusResponse.json();
                                                if (status.paused) {
                                                    return;
                                                }
                                                if (!status.playing && !status.paused && isPlayingAll) {
                                                    clearInterval(playAllInterval);
                                                    playAllInterval = null;
                                                    currentIndex++;
                                                    if (currentIndex < audios.length) {
                                                        setTimeout(playNext, 500);
                                                    } else {
                                                        isPlayingAll = false;
                                                    }
                                                }
                                            }
                                        } catch (error) {
                                            clearInterval(playAllInterval);
                                            playAllInterval = null;
                                            isPlayingAll = false;
                                        }
                                    }, 1000);
                                };
                                playNext();
                            }
                        }
                    }
                } else {
                    alert('Selecciona un audio para reproducir');
                }
            }
        } catch (error) {
            console.error('Error al verificar estado:', error);
            alert('Selecciona un audio para reproducir');
        }
    }
};

const toggleRepeat = async () => {
    const newRepeatState = !isRepeating;
    
    try {
        // Enviar estado al servidor primero
        const response = await fetch('/App_Estacion/public/api/player/status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ repeat: newRepeatState })
        });
        
        if (response.ok) {
            const result = await response.json();
            
            // Solo actualizar la interfaz si el servidor confirmó el cambio
            isRepeating = newRepeatState;
            const repeatButton = document.getElementById('repeat-button');
            
            if (isRepeating) {
                repeatButton.classList.add('active');
                console.log('Modo repetición activado');
            } else {
                repeatButton.classList.remove('active');
                console.log('Modo repetición desactivado');
            }
        } else {
            console.error('Error al cambiar estado de repetición');
        }
    } catch (error) {
        console.error('Error sincronizando estado de repetición:', error);
    }
};

const playAllCategory = async (categoria) => {
    const audios = audiosByCategory[categoria];
    if (!audios || audios.length === 0) {
        alert('No hay audios en esta categoría');
        return;
    }
    
    // Detener audio actual
    if (isPlaying) {
        await stopAudio();
        await new Promise(resolve => setTimeout(resolve, 200));
    }
    
    isPlayingAll = true;
    let currentIndex = 0;
    // Quitar la clase activa de todos los botones
    document.querySelectorAll('.reproducir-todo').forEach(btn => btn.classList.remove('active-playall'));
    // Buscar el botón correcto según la categoría
    let playAllBtn = null;
    let categorySection = document.querySelector(`[data-categoria="${categoria}"]`);
    if (categorySection) {
        playAllBtn = categorySection.querySelector('.reproducir-todo');
    }
    if (playAllBtn) playAllBtn.classList.add('active-playall');

    const playNext = async () => {
        if (!isPlayingAll || currentIndex >= audios.length) {
            isPlayingAll = false;
            return;
        }
        await playAudio(audios[currentIndex].id, audios[currentIndex].url, audios[currentIndex].nombre);
        playAllInterval = setInterval(async () => {
            // Verificación doble de isPlayingAll
            if (!isPlayingAll) {
                clearInterval(playAllInterval);
                playAllInterval = null;
                document.querySelectorAll('.reproducir-todo').forEach(btn => btn.classList.remove('active-playall'));
                return;
            }
            try {
                const statusResponse = await fetch('/App_Estacion/public/api/player/status', {
                    method: 'GET',
                    credentials: 'include'
                });
                if (statusResponse.ok) {
                    const status = await statusResponse.json();
                    // Verificar nuevamente después de la respuesta
                    if (!isPlayingAll) {
                        clearInterval(playAllInterval);
                        playAllInterval = null;
                        document.querySelectorAll('.reproducir-todo').forEach(btn => btn.classList.remove('active-playall'));
                        return;
                    }
                    if (status.paused) {
                        return;
                    }
                    if (!status.playing && !status.paused && isPlayingAll) {
                        clearInterval(playAllInterval);
                        playAllInterval = null;
                        currentIndex++;
                        if (currentIndex < audios.length && isPlayingAll) {
                            playAllTimeoutId = setTimeout(() => {
                                if (isPlayingAll) {
                                    playNext();
                                }
                            }, 500);
                        } else {
                            isPlayingAll = false;
                            document.querySelectorAll('.reproducir-todo').forEach(btn => btn.classList.remove('active-playall'));
                        }
                    }
                }
            } catch (error) {
                clearInterval(playAllInterval);
                playAllInterval = null;
                isPlayingAll = false;
                document.querySelectorAll('.reproducir-todo').forEach(btn => btn.classList.remove('active-playall'));
            }
        }, 300);
    };
    playNext();
};
const editAudio = async (id) => {
    const newName = prompt('Ingrese el nuevo nombre:');
    if (!newName) return;
    
    try {
        const response = await fetch(`/App_Estacion/public/api/audios/${id}`, {
            method: 'PUT',
            credentials: 'include',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `nombre=${encodeURIComponent(newName)}`
        });
        
        const result = await response.json();
        if (response.ok && result.success) {
            loadAudios();
            alert('Audio renombrado exitosamente');
        } else if (response.status === 403) {
            alert('No tienes permisos para editar audios');
        } else {
            alert('Error al renombrar: ' + (result.error || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión');
    }
};

const deleteAudio = async (id) => {
    if (!confirm('¿Está seguro de eliminar este audio?')) return;
    
    try {
        const response = await fetch(`/App_Estacion/public/api/audios/${id}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        const result = await response.json();
        if (response.ok && result.success) {
            loadAudios();
            alert('Audio eliminado exitosamente');
        } else if (response.status === 403) {
            alert('No tienes permisos para eliminar audios');
        } else {
            alert('Error al eliminar: ' + (result.error || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión');
    }
};

const editCategory = async (oldCategory) => {
    const newCategory = prompt('Ingrese el nuevo nombre de la categoría:', oldCategory);
    if (!newCategory || newCategory === oldCategory) return;
    
    try {
        const response = await fetch('/App_Estacion/public/api/audios/category', {
            method: 'PATCH',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                old_category: oldCategory,
                new_category: newCategory.toUpperCase()
            })
        });
        
        const result = await response.json();
        if (response.ok && result.success) {
            loadAudios();
            alert('Categoría renombrada exitosamente');
        } else if (response.status === 403) {
            alert('No tienes permisos para editar categorías');
        } else {
            alert('Error al renombrar categoría: ' + (result.error || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión');
    }
};

// Función para cargar usuarios
const loadUsers = async () => {
    try {
        const response = await fetch('/App_Estacion/public/api/users', {
            method: 'GET',
            credentials: 'include'
        });
        
        const result = await response.json();
        if (result.success) {
            const usersList = document.getElementById('users-list');
            usersList.innerHTML = '';
            
            result.users.forEach(user => {
                const userItem = document.createElement('div');
                userItem.className = 'user-item';
                userItem.innerHTML = `
                    <div class="user-info">
                        <strong>${user.usuario}</strong>
                        <span class="user-role">${user.rol}</span>
                    </div>
                    <div class="user-actions">
                        <button class="btn-danger" onclick="deleteUser(${user.id}, '${user.usuario}')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                `;
                usersList.appendChild(userItem);
            });
        }
    } catch (error) {
        console.error('Error cargando usuarios:', error);
    }
};

// Función para eliminar usuario
const deleteUser = async (id, username) => {
    if (!confirm(`¿Está seguro de eliminar el usuario "${username}"?`)) return;
    
    try {
        const response = await fetch(`/App_Estacion/public/api/users/${id}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        const result = await response.json();
        if (result.success) {
            loadUsers();
            alert('Usuario eliminado exitosamente');
        } else {
            alert('Error: ' + (result.error || 'No se pudo eliminar el usuario'));
        }
    } catch (error) {
        alert('Error de conexión');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const errorMessage = document.getElementById('mensaje-error');
    const logoutBtn = document.getElementById('logout-btn');
    const fab = document.querySelector('.fab');
    const playButton = document.querySelector('.player-section .play-button');
    const usersBtn = document.getElementById('users-btn');
    const usersModal = document.getElementById('users-modal');
    const userFormModal = document.getElementById('user-form-modal');

    // Manejar el envío del formulario de inicio de sesión
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const usuario = document.getElementById('usuario').value;
        const password = document.getElementById('password').value;

        try {
            const response = await fetch('/App_Estacion/public/api/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({ usuario, password })
            });

            const data = await response.json();

            if (response.ok) {
                userSession = data;
                showApp(data);
                errorMessage.textContent = '';
            } else {
                errorMessage.textContent = data.error || 'Credenciales incorrectas';
            }
        } catch (error) {
            errorMessage.textContent = 'Error de conexión. Intente de nuevo.';
            console.error('Error:', error);
        }
    });

    // Manejar el cierre de sesión
    logoutBtn.addEventListener('click', async () => {
        stopStatusCheck();
        await fetch('/App_Estacion/public/api/logout', { method: 'POST' });
        userSession = null;
        showLogin();
    });

    // Botón de gestión de usuarios
    usersBtn.addEventListener('click', () => {
        loadUsers();
        usersModal.classList.remove('hidden');
    });
    
    // Cerrar modales de usuarios
    document.getElementById('users-close').addEventListener('click', () => {
        usersModal.classList.add('hidden');
    });
    
    document.getElementById('user-form-close').addEventListener('click', () => {
        userFormModal.classList.add('hidden');
    });
    
    document.getElementById('user-form-cancel').addEventListener('click', () => {
        userFormModal.classList.add('hidden');
    });
    
    // Botón agregar usuario
    document.getElementById('add-user-btn').addEventListener('click', () => {
        document.getElementById('user-form-title').textContent = 'Agregar Usuario';
        document.getElementById('user-form').reset();
        userFormModal.classList.remove('hidden');
    });
    
    // Formulario de usuario
    document.getElementById('user-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const userData = {
            usuario: formData.get('usuario'),
            password: formData.get('password'),
            rol: formData.get('rol')
        };
        
        try {
            const response = await fetch('/App_Estacion/public/api/users', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(userData)
            });
            
            const result = await response.json();
            if (result.success) {
                userFormModal.classList.add('hidden');
                loadUsers();
                alert('Usuario creado exitosamente');
            } else {
                alert('Error: ' + (result.error || 'No se pudo crear el usuario'));
            }
        } catch (error) {
            alert('Error de conexión');
        }
    });
    
    // Inicia la verificación de la sesión al cargar la página
    checkSession();

    // Modal y formulario de audio
    const modal = document.getElementById('audio-modal');
    const audioForm = document.getElementById('audio-form');
    const closeBtn = document.querySelector('.close');
    const cancelBtn = document.querySelector('.btn-cancel');
    const categorySelect = document.getElementById('audio-category');
    const nuevaCategoriaGroup = document.getElementById('nueva-categoria-group');
    const nuevaCategoriaInput = document.getElementById('nueva-categoria');
    
    // Mostrar/ocultar campo de nueva categoría
    categorySelect.addEventListener('change', () => {
        if (categorySelect.value === 'nueva') {
            nuevaCategoriaGroup.classList.remove('nueva-categoria-group');
            nuevaCategoriaInput.required = true;
        } else {
            nuevaCategoriaGroup.classList.add('nueva-categoria-group');
            nuevaCategoriaInput.required = false;
            nuevaCategoriaInput.value = '';
        }
    });

    // Botón flotante para agregar audio
    fab.addEventListener('click', () => {
        modal.classList.remove('hidden');
    });

    // Cerrar modal
    const closeModal = () => {
        modal.classList.add('hidden');
        audioForm.reset();
    };

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    
    // Cerrar modal al hacer clic fuera
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Manejar envío del formulario de audio
    audioForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = document.querySelector('.btn-submit');
        const originalText = submitBtn.textContent;
        
        try {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Subiendo...';
            
            const formData = new FormData(audioForm);
            
            // Si se seleccionó nueva categoría, usar el valor del input
            if (categorySelect.value === 'nueva') {
                const nuevaCategoria = nuevaCategoriaInput.value.trim();
                if (!nuevaCategoria) {
                    alert('Por favor ingrese el nombre de la nueva categoría');
                    return;
                }
                formData.set('categoria', nuevaCategoria.toUpperCase());
            }
            
            const response = await fetch('/App_Estacion/public/api/audios', {
                method: 'POST',
                credentials: 'include',
                body: formData
            });
            
            const responseText = await response.text();
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Error al parsear respuesta:', responseText);
                alert('Error del servidor: ' + responseText.substring(0, 100));
                return;
            }
            
            if (response.ok && result.success) {
                closeModal();
                loadAudios(); // Recargar la lista de audios
                alert('Audio subido exitosamente');
            } else if (response.status === 403) {
                alert('No tienes permisos para subir audios');
            } else {
                alert('Error al subir el audio: ' + (result.error || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error de conexión al subir el audio');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });

    // Botón de play/pause global
    playButton.addEventListener('click', togglePlayPause);
    
    // Botón de stop
    const stopButton = document.getElementById('stop-button');
    stopButton.addEventListener('click', stopAudio);
    
    // Botón de repetición
    const repeatButton = document.getElementById('repeat-button');
    repeatButton.addEventListener('click', toggleRepeat);
    
    // Barra de búsqueda
    const searchInput = document.querySelector('.search-bar input');
    searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        document.querySelectorAll('.audio-item').forEach(item => {
            const audioName = item.querySelector('.audio-button').textContent.toLowerCase();
            if (audioName.includes(searchTerm)) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });
        
        // Ocultar categorías vacías
        document.querySelectorAll('.category').forEach(category => {
            const visibleItems = category.querySelectorAll('.audio-item:not(.hidden)');
            if (visibleItems.length === 0 && searchTerm !== '') {
                category.classList.add('hidden');
            } else {
                category.classList.remove('hidden');
            }
        });
    });
    
    // Barra de progreso solo visual (no clickeable)
    // El audio se reproduce completamente en el servidor

    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('toggle-password');
    const eyeIcon = document.getElementById('eye-icon');
    if (togglePassword && passwordInput && eyeIcon) {
        togglePassword.addEventListener('click', () => {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            }
        });
    }


    // Event listeners para filtros
    const sortSelect = document.getElementById('sort-select');
    const orderSelect = document.getElementById('order-select');

    if (sortSelect && orderSelect) {
        const applyFilters = () => {
            const sortBy = sortSelect.value;
            const order = orderSelect.value;
            loadAudios(sortBy, order);
        };

        sortSelect.addEventListener('change', applyFilters);
        orderSelect.addEventListener('change', applyFilters);
    }

});

