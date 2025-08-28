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
                    customSection.innerHTML = `
                        <div class="category-header">
                            <h3><i class="fa-solid fa-music"></i> ${audio.categoria}</h3>
                            <button class="reproducir-todo">Reproducir Todo</button>
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
    } catch (error) {
        console.error('Error al obtener audios:', error);
    }
};

// Variables globales para audio
let currentAudio = null;
let isPlaying = false;
let currentAudioTitle = '';
let audiosByCategory = {
    'ANUNCIOS GENERALES': [],
    'ANUNCIOS DEL TREN': []
};

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

const playAudio = (id, url, title = 'Audio') => {
    if (currentAudio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
    }
    
    console.log('Intentando reproducir:', url);
    currentAudio = new Audio(url);
    currentAudioTitle = title;
    
    currentAudio.addEventListener('play', () => {
        isPlaying = true;
        updatePlayButton();
        console.log('Audio iniciado');
    });
    
    currentAudio.addEventListener('pause', () => {
        isPlaying = false;
        updatePlayButton();
    });
    
    currentAudio.addEventListener('ended', () => {
        isPlaying = false;
        updatePlayButton();
        hideProgressBar();
    });
    
    currentAudio.addEventListener('timeupdate', updateProgressBar);
    
    currentAudio.addEventListener('loadedmetadata', updateProgressBar);
    
    currentAudio.addEventListener('error', (e) => {
        console.error('Error al cargar audio:', e);
        console.error('URL del audio:', url);
        alert('Error al reproducir el audio. Verifique que el archivo sea válido.');
        isPlaying = false;
        updatePlayButton();
        hideProgressBar();
    });
    
    currentAudio.addEventListener('loadstart', () => {
        console.log('Iniciando carga del audio');
    });
    
    currentAudio.addEventListener('canplay', () => {
        console.log('Audio listo para reproducir');
    });
    
    currentAudio.play().catch(error => {
        console.error('Error al reproducir:', error);
        alert('No se pudo reproducir el audio: ' + error.message);
    });
};

const togglePlayPause = () => {
    if (!currentAudio) {
        alert('No hay audio seleccionado');
        return;
    }
    
    if (isPlaying) {
        currentAudio.pause();
    } else {
        currentAudio.play();
    }
};

const playAllCategory = async (categoria) => {
    const audios = audiosByCategory[categoria];
    if (!audios || audios.length === 0) {
        alert('No hay audios en esta categoría');
        return;
    }
    
    if (currentAudio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
    }
    
    let currentIndex = 0;
    
    const playNext = () => {
        if (currentIndex < audios.length) {
            currentAudio = new Audio(audios[currentIndex].url);
            currentAudio.addEventListener('play', () => {
                isPlaying = true;
                updatePlayButton();
            });
            currentAudio.addEventListener('pause', () => {
                isPlaying = false;
                updatePlayButton();
            });
            currentAudio.addEventListener('ended', () => {
                currentIndex++;
                if (currentIndex >= audios.length) {
                    isPlaying = false;
                    updatePlayButton();
                }
                playNext();
            });
            currentAudio.play();
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
    
    // Barra de progreso clickeable
    const progressBar = document.getElementById('progress-bar');
    progressBar.addEventListener('click', (e) => {
        if (!currentAudio || !currentAudio.duration) return;
        
        const rect = progressBar.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const width = rect.width;
        const percentage = clickX / width;
        
        currentAudio.currentTime = percentage * currentAudio.duration;
        updateProgressBar();
    });
});