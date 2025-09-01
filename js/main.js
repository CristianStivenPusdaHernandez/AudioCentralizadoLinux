let userSession = null;
const appContainer = document.querySelector('.app-container');
const loginScreen = document.getElementById('login-screen');

// Muestra la pantalla de inicio de sesión
const showLogin = () => {
    loginScreen.style.display = 'flex'; // Usamos flex para centrar el contenido
    appContainer.style.display = 'none';
};

// Muestra la aplicación principal
const showApp = (userData) => {
    loginScreen.style.display = 'none';
    appContainer.style.display = 'grid'; // Usamos grid para el layout principal
    if (userData) {
        document.getElementById('user-name').textContent = userData.usuario;
        userSession = userData; // Guardar datos del usuario
        
        // Mostrar/ocultar FAB según permisos
        const fab = document.querySelector('.fab');
        if (userData.permisos && userData.permisos.includes('subir_audio')) {
            fab.style.display = 'flex';
        } else {
            fab.style.display = 'none';
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
        const response = await fetch('backend/login.php', { 
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
const loadAudios = async () => {
    try {
        const response = await fetch('backend/audios.php', { 
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
        const generalGrid = document.getElementById('general-grid');
        const trainGrid = document.getElementById('train-grid');
        
        generalGrid.innerHTML = ''; // Limpiar grids existentes
        trainGrid.innerHTML = '';

        if (!Array.isArray(audios)) {
            console.error('Los audios no son un array:', audios);
            return;
        }

        // Limpiar arrays de categorías
        audiosByCategory = {};
        
        // Limpiar grids existentes
        const allGrids = document.querySelectorAll('.button-grid');
        allGrids.forEach(grid => {
            if (grid.id !== 'general-grid' && grid.id !== 'train-grid') {
                grid.parentElement.remove();
            }
        });
        
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
            const canEdit = userSession && userSession.permisos && userSession.permisos.includes('editar_audio');
            const canDelete = userSession && userSession.permisos && userSession.permisos.includes('eliminar_audio');
            
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
            
            if (audio.categoria === 'ANUNCIOS GENERALES') {
                generalGrid.appendChild(audioItem);
            } else if (audio.categoria === 'ANUNCIOS DEL TREN') {
                trainGrid.appendChild(audioItem);
            } else {
                // Crear nueva sección para categoría personalizada
                let customSection = document.querySelector(`[data-categoria="${audio.categoria}"]`);
                if (!customSection) {
                    customSection = document.createElement('div');
                    customSection.className = 'category';
                    customSection.setAttribute('data-categoria', audio.categoria);
                    const canEditCategory = userSession && userSession.permisos && userSession.permisos.includes('editar_audio');
                    const editCategoryButton = canEditCategory ? `<button class="edit-category-button" data-categoria="${audio.categoria}" title="Editar categoría"><i class="fa-solid fa-pencil"></i></button>` : '';
                    
                    customSection.innerHTML = `
                        <div class="category-header">
                            <h3><i class="fa-solid fa-music"></i> ${audio.categoria}</h3>
                            <div class="category-buttons">
                                ${editCategoryButton}
                                <button class="reproducir-todo">Reproducir Todo</button>
                            </div>
                        </div>
                        <div class="button-grid"></div>
                    `;
                    document.querySelector('.announcements-section').appendChild(customSection);
                }
                const customGrid = customSection.querySelector('.button-grid');
                customGrid.appendChild(audioItem);
            }
        });
        
        // Agregar event listeners a los botones "Reproducir Todo"
        document.querySelectorAll('.reproducir-todo').forEach(btn => {
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
        });
        
        document.querySelectorAll('.reproducir-todo').forEach(btn => {
            const categorySection = btn.closest('.category');
            let categoria;
            if (categorySection.getAttribute('data-categoria')) {
                categoria = categorySection.getAttribute('data-categoria');
            } else if (categorySection.querySelector('h3').textContent.includes('Generales')) {
                categoria = 'ANUNCIOS GENERALES';
            } else if (categorySection.querySelector('h3').textContent.includes('Tren')) {
                categoria = 'ANUNCIOS DEL TREN';
            }
            if (categoria) {
                btn.addEventListener('click', () => playAllCategory(categoria));
            }
        });
        
        // Event listeners para botones de editar categoría (tanto predeterminadas como personalizadas)
        document.querySelectorAll('.edit-category-button').forEach(btn => {
            btn.addEventListener('click', () => editCategory(btn.dataset.categoria));
        });
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

const playAudio = async (id, url, title = 'Audio') => {
    try {
        // Detener audio actual antes de reproducir uno nuevo
        if (isPlaying) {
            await stopAudio();
            // Esperar un momento para asegurar que el audio anterior se detuvo completamente
            await new Promise(resolve => setTimeout(resolve, 200));
        }
        
        const response = await fetch('backend/player.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ action: 'play', audio_id: id, repeat: isRepeating })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const result = await response.json();
        console.log('Audio reproduciéndose en servidor:', result.message);
        
        // Guardar información del audio actual
        currentAudioId = id;
        currentAudioUrl = url;
        const audioTitle = result.title || title;
        const duration = result.duration || 0;
        
        document.getElementById('audio-title').textContent = `🔊 ${audioTitle} (${formatTime(duration)})`;
        document.getElementById('audio-progress').classList.add('active');
        
        // Mostrar duración total inmediatamente
        if (duration > 0) {
            document.getElementById('total-time').textContent = formatTime(duration);
        }
        
        // Actualizar estado del botón
        isPlaying = true;
        currentAudioTitle = audioTitle;
        updatePlayButton();
        
        // Mostrar botón de repetición
        document.getElementById('repeat-button').style.display = 'flex';
        
    } catch (error) {
        console.error('Error de conexión:', error);
        alert('Error de conexión al servidor: ' + error.message);
    }
};

const stopAudio = async () => {
    try {
        const response = await fetch('backend/player.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ action: 'stop' })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const result = await response.json();
        console.log('Audio detenido:', result.message);
        
        // Actualizar interfaz inmediatamente
        isPlaying = false;
        currentAudioTitle = '';
        currentAudioId = null; // Limpiar ID para evitar repetición
        updatePlayButton();
        hideProgressBar();
        
        // Limpiar barra de progreso
        document.getElementById('progress-fill').style.width = '0%';
        document.getElementById('current-time').textContent = '0:00';
        document.getElementById('total-time').textContent = '0:00';
        
    } catch (error) {
        console.error('Error al detener audio:', error);
        alert('Error al detener audio: ' + error.message);
    }
};

// Verificar estado del reproductor cada 2 segundos
const checkPlayerStatus = async () => {
    try {
        const response = await fetch('backend/player_status.php', {
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
            
            // Actualizar interfaz según el estado
            if (status.playing === true && status.title) {
                const titleWithDuration = status.duration > 0 ? 
                    `🔊 ${status.title} (${formatTime(status.duration)})` : 
                    `🔊 ${status.title}`;
                
                // Solo actualizar si cambió el título o el estado
                if (!wasPlaying || !currentTitle.includes(status.title)) {
                    document.getElementById('audio-title').textContent = titleWithDuration;
                    document.getElementById('audio-progress').classList.add('active');
                }
                
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
                
                isPlaying = true;
                currentAudioTitle = status.title;
                
                // Sincronizar estado de repetición solo si cambió desde otro dispositivo
                if (status.repeat !== undefined && status.repeat !== isRepeating) {
                    isRepeating = status.repeat;
                    const repeatButton = document.getElementById('repeat-button');
                    if (repeatButton.style.display !== 'none') {
                        if (isRepeating) {
                            repeatButton.classList.add('active');
                        } else {
                            repeatButton.classList.remove('active');
                        }
                    }
                }
            } else {
                // Si el audio terminó y está en modo repetición, reproducir de nuevo
                if (wasPlaying && isRepeating && currentAudioId) {
                    setTimeout(() => playAudio(currentAudioId, currentAudioUrl, currentAudioTitle), 500);
                    return;
                }
                
                // Si el audio terminó y está en modo repetición, reproducir de nuevo
                if (wasPlaying && isRepeating && currentAudioId) {
                    setTimeout(() => playAudio(currentAudioId, currentAudioUrl, currentAudioTitle), 500);
                    return;
                }
                
                // Solo limpiar si estaba reproduciendo y NO está en repetición
                if (wasPlaying && !isRepeating) {
                    document.getElementById('audio-title').textContent = 'Selecciona un audio';
                    document.getElementById('audio-progress').classList.remove('active');
                    document.getElementById('progress-fill').style.width = '0%';
                    document.getElementById('current-time').textContent = '0:00';
                    document.getElementById('total-time').textContent = '0:00';
                }
                isPlaying = false;
                currentAudioTitle = '';
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
    statusCheckInterval = setInterval(checkPlayerStatus, 1000); // Cada 1 segundo para evitar conflictos
    // Verificación inmediata
    setTimeout(checkPlayerStatus, 100);
};

const stopStatusCheck = () => {
    if (statusCheckInterval) {
        clearInterval(statusCheckInterval);
        statusCheckInterval = null;
    }
};

const togglePlayPause = () => {
    if (isPlaying) {
        stopAudio();
    } else {
        alert('Selecciona un audio para reproducir');
    }
};

const toggleRepeat = async () => {
    isRepeating = !isRepeating;
    const repeatButton = document.getElementById('repeat-button');
    if (isRepeating) {
        repeatButton.classList.add('active');
    } else {
        repeatButton.classList.remove('active');
    }
    
    // Enviar estado al servidor para sincronizar
    try {
        await fetch('backend/player_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ repeat: isRepeating })
        });
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
    
    let currentIndex = 0;
    const playNext = async () => {
        if (currentIndex < audios.length) {
            await playAudio(audios[currentIndex].id, audios[currentIndex].url, audios[currentIndex].nombre);
            
            // Esperar a que termine el audio antes de reproducir el siguiente
            const checkIfFinished = setInterval(async () => {
                if (!isPlaying) {
                    clearInterval(checkIfFinished);
                    currentIndex++;
                    if (currentIndex < audios.length) {
                        setTimeout(playNext, 500);
                    }
                }
            }, 1000);
        }
    };
    playNext();
};
const editAudio = async (id) => {
    const newName = prompt('Ingrese el nuevo nombre:');
    if (!newName) return;
    
    try {
        const response = await fetch(`backend/audios.php?id=${id}`, {
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
        const response = await fetch(`backend/audios.php?id=${id}`, {
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
        const response = await fetch('backend/audios.php', {
            method: 'PATCH',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'edit_category',
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


document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const errorMessage = document.getElementById('mensaje-error');
    const logoutBtn = document.getElementById('logout-btn');
    const fab = document.querySelector('.fab');
    const playButton = document.querySelector('.player-section .play-button');

    // Manejar el envío del formulario de inicio de sesión
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const usuario = document.getElementById('usuario').value;
        const password = document.getElementById('password').value;

        try {
            const response = await fetch('backend/login.php', {
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
        await fetch('backend/logout.php');
        userSession = null;
        showLogin();
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
            nuevaCategoriaGroup.style.display = 'block';
            nuevaCategoriaInput.required = true;
        } else {
            nuevaCategoriaGroup.style.display = 'none';
            nuevaCategoriaInput.required = false;
            nuevaCategoriaInput.value = '';
        }
    });

    // Botón flotante para agregar audio
    fab.addEventListener('click', () => {
        modal.style.display = 'flex';
    });

    // Cerrar modal
    const closeModal = () => {
        modal.style.display = 'none';
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
            
            const response = await fetch('backend/audios.php', {
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
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
        
        // Ocultar categorías vacías
        document.querySelectorAll('.category').forEach(category => {
            const visibleItems = category.querySelectorAll('.audio-item[style*="flex"], .audio-item:not([style*="none"])');
            if (visibleItems.length === 0 && searchTerm !== '') {
                category.style.display = 'none';
            } else {
                category.style.display = 'block';
            }
        });
    });
    
    // Barra de progreso solo visual (no clickeable)
    // El audio se reproduce completamente en el servidor
});