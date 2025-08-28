let userSession = null;

function showLoginModal(onSuccess) {
    // Modal básico
    const modalBg = document.createElement('div');
    modalBg.style.position = 'fixed';
    modalBg.style.top = 0;
    modalBg.style.left = 0;
    modalBg.style.width = '100vw';
    modalBg.style.height = '100vh';
    modalBg.style.background = 'rgba(0,0,0,0.5)';
    modalBg.style.display = 'flex';
    modalBg.style.alignItems = 'center';
    modalBg.style.justifyContent = 'center';
    modalBg.style.zIndex = 10000;

    const modal = document.createElement('div');
    modal.style.background = '#222';
    modal.style.padding = '2rem';
    modal.style.borderRadius = '12px';
    modal.style.minWidth = '300px';
    modal.style.color = 'white';
    modal.innerHTML = `
        <h3>Iniciar sesión</h3>
        <input id="login-user" type="text" placeholder="Usuario" style="width:100%;margin:0.5rem 0;padding:0.5rem;" />
        <input id="login-pass" type="password" placeholder="Contraseña" style="width:100%;margin:0.5rem 0;padding:0.5rem;" />
        <div id="login-error" style="color:#e57373;margin-bottom:0.5rem;"></div>
        <div style="margin-top:1rem;text-align:right;">
            <button id="login-ok">Entrar</button>
        </div>
    `;
    modalBg.appendChild(modal);
    document.body.appendChild(modalBg);

    modal.querySelector('#login-ok').onclick = async () => {
        const usuario = modal.querySelector('#login-user').value.trim();
        const password = modal.querySelector('#login-pass').value;
        if (!usuario || !password) {
            modal.querySelector('#login-error').textContent = 'Completa todos los campos';
            return;
        }
        try {
            const res = await fetch('backend/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ usuario, password })
            });
            if (!res.ok) {
                const err = await res.json();
                modal.querySelector('#login-error').textContent = err.error || 'Error de autenticación';
                return;
            }
            userSession = await res.json();
            document.body.removeChild(modalBg);
            if (onSuccess) onSuccess();
        } catch (e) {
            modal.querySelector('#login-error').textContent = 'Error de red';
        }
    };
}

async function checkSession() {
    try {
        const res = await fetch('backend/login.php');
        if (!res.ok) return false;
        userSession = await res.json();
        return true;
    } catch {
        return false;
    }
}

function hasPermiso(perm) {
    return userSession && userSession.permisos && userSession.permisos.includes(perm);
}

document.addEventListener('DOMContentLoaded', async () => {
    // Verificar sesión
    const logged = await checkSession();
    if (!logged) {
        showLoginModal(() => location.reload());
        return;
    }
    // --- 1. SELECCIÓN DE ELEMENTOS DEL DOM ---
    const announcementsSection = document.querySelector('.announcements-section');
    const playerSection = document.querySelector('.player-section');
    const trackNameDisplay = playerSection.querySelector('.track-name');
    const statusDisplay = playerSection.querySelector('.status');
    const playButton = playerSection.querySelector('.play-button');
    const playButtonIcon = playButton.querySelector('i');
    const progressBar = playerSection.querySelector('.progress');
    const progressBarContainer = playerSection.querySelector('.progress-bar');
        const fab = document.querySelector('.fab'); 
        // Ocultar FAB si no tiene permiso
        if (!hasPermiso('subir_audio')) {
            fab.style.display = 'none';
        } else {
            fab.addEventListener('click', () => fileInput.click());
        }
    // --- NUEVO: Selección de la barra de búsqueda ---
    const searchInput = document.querySelector('.search-bar input');


    // --- 2. CONFIGURACIÓN INICIAL ---
    const audioPlayer = new Audio();
    let currentAudioSrc = null;
    let currentPlaylist = [];
    let currentPlaylistIndex = 0;

    // No hay audios predefinidos, todo viene de la base de datos

    // --- 3. MANEJO DE DATOS CON BACKEND (GET) ---
    async function getAllAudios() {
        try {
            const response = await fetch('backend/audios.php');
            if (!response.ok) throw new Error('Error al obtener audios');
            const audios = await response.json();
            // Adaptar para que coincida con el resto del código (id, name, path, categoria)
            return audios.map(a => ({
                id: a.id,
                name: a.nombre,
                path: `backend/audios.php?id=${a.id}&action=download`,
                extension: a.extension,
                categoria: a.categoria || 'General'
            }));
        } catch (e) {
            console.error(e);
            return [];
        }
    }

    // saveAddedAudios ya no es necesario, se elimina

    // --- 4. RENDERIZADO Y LÓGICA DE LA UI ---

    function createButton(audio) {
        const button = document.createElement('button');
        button.className = 'announcement-button';
        button.dataset.src = audio.path;
        
        const mainContent = document.createElement('div');
        mainContent.className = 'button-main-content';
        mainContent.innerHTML = `<i class="fa-solid fa-volume-high"></i> <span class="audio-name">${audio.name}</span>`;
        button.appendChild(mainContent);

            // Acciones solo si tiene permisos
            if (hasPermiso('editar_audio') || hasPermiso('eliminar_audio')) {
                const actionsContainer = document.createElement('div');
                actionsContainer.className = 'button-actions';

                if (hasPermiso('editar_audio')) {
                    const renameBtn = document.createElement('button');
                    renameBtn.className = 'rename-audio-btn';
                    renameBtn.innerHTML = '<i class="fa-solid fa-pencil"></i>';
                    renameBtn.title = 'Cambiar nombre';
                    renameBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        handleRenameAudio(audio, button);
                    });
                    actionsContainer.appendChild(renameBtn);
                }
                if (hasPermiso('eliminar_audio')) {
                    const deleteBtn = document.createElement('button');
                    deleteBtn.className = 'delete-audio-btn';
                    deleteBtn.innerHTML = '<i class="fa-solid fa-times"></i>';
                    deleteBtn.title = 'Eliminar este audio';
                    deleteBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        handleDeleteAudio(audio, button);
                    });
                    actionsContainer.appendChild(deleteBtn);
                }
                button.appendChild(actionsContainer);
            }

        if (audio.isAdded) {
            button.classList.add('added-audio');
        }

        button.addEventListener('click', () => selectAndPlayAnnouncement(button));
        return button;
    }

    async function renderAllAudios() {
        // Eliminar todas las categorías actuales
        document.querySelectorAll('.category').forEach(cat => cat.remove());

        // Renderizar audios de la base de datos agrupados por categoría
        const allAudios = await getAllAudios();
        // Agrupar por categoría
        const categorias = {};
        allAudios.forEach(audio => {
            if (!categorias[audio.categoria]) categorias[audio.categoria] = [];
            categorias[audio.categoria].push(audio);
        });

        // Renderizar cada categoría
        const announcementsSection = document.querySelector('.announcements-section');
        Object.entries(categorias).forEach(([cat, audios]) => {
            let icon = '<i class="fa-solid fa-folder"></i>';
            if (cat.toLowerCase() === 'general') icon = '<i class="fa-solid fa-star"></i>';
            if (cat.toLowerCase() === 'tren') icon = '<i class="fa-solid fa-train"></i>';
            const categoryDiv = document.createElement('div');
            categoryDiv.className = 'category dynamic-category';
            categoryDiv.innerHTML = `
                <div class="category-header">
                    <h3>${icon} ${cat}</h3>
                </div>
                <div class="button-grid"></div>`;
            announcementsSection.appendChild(categoryDiv);
            addPlayAllButton(categoryDiv, audios);
            const grid = categoryDiv.querySelector('.button-grid');
            audios.forEach(audio => grid.appendChild(createButton({ ...audio, isAdded: true })));
        });
    }
    
    function addPlayAllButton(categoryElement, audioList) {
        const header = categoryElement.querySelector('.category-header, h3');
        if (header.querySelector('.play-all-btn')) {
            header.querySelector('.play-all-btn').remove();
        }

        const playAllBtn = document.createElement('button');
        playAllBtn.className = 'play-all-btn';
        playAllBtn.innerHTML = '<i class="fa-solid fa-play"></i> Reproducir Todo';
        playAllBtn.title = 'Reproducir todos los audios de esta categoría';
        
        playAllBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            handlePlayAll(audioList);
        });

        if (header.tagName === 'H3') {
           const headerContainer = document.createElement('div');
           headerContainer.className = 'category-header';
           header.parentNode.insertBefore(headerContainer, header);
           headerContainer.appendChild(header);
           headerContainer.appendChild(playAllBtn);
        } else {
            header.appendChild(playAllBtn);
        }
    }

    function selectAndPlayAnnouncement(clickedButton) {
        currentPlaylist = [];
        currentPlaylistIndex = 0;

        document.querySelectorAll('.announcement-button').forEach(btn => btn.classList.remove('selected'));
        clickedButton.classList.add('selected');

        currentAudioSrc = clickedButton.dataset.src;
        audioPlayer.src = currentAudioSrc;
        audioPlayer.play();

        const name = clickedButton.querySelector('.audio-name').textContent.trim();
        trackNameDisplay.textContent = name;
        playerSection.classList.add('ready');
    }

    // --- 5. LÓGICA DE FUNCIONALIDADES ---

    function togglePlayPause() {
        if (!currentAudioSrc) return;
        if (audioPlayer.paused) audioPlayer.play();
        else audioPlayer.pause();
    }
    
    async function handleDeleteAudio(audio, buttonElement) {
        const userConfirmed = confirm(`¿Deseas eliminar el audio "${audio.name}"?`);
        if (userConfirmed) {
            if (audio.isAdded) {
                try {
                    const response = await fetch(`backend/audios.php?id=${audio.id}`, {
                        method: 'DELETE'
                    });
                    if (!response.ok) throw new Error('Error al eliminar');
                } catch (e) {
                    alert('No se pudo eliminar el audio');
                }
                renderAllAudios();
            }

            if(currentAudioSrc === buttonElement.dataset.src) {
                audioPlayer.pause();
                currentAudioSrc = null;
                trackNameDisplay.textContent = '';
                playerSection.classList.remove('ready');
            }
        }
    }
    
    async function handleRenameAudio(audio, buttonElement) {
        const currentName = audio.name;
        const newName = prompt("Introduce el nuevo nombre para el audio:", currentName);

        if (newName && newName.trim() !== '' && newName !== currentName) {
            if (audio.isAdded) {
                try {
                    const params = new URLSearchParams();
                    params.append('nombre', newName.trim());
                    const response = await fetch(`backend/audios.php?id=${audio.id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: params.toString()
                    });
                    if (!response.ok) throw new Error('Error al renombrar');
                } catch (e) {
                    alert('No se pudo renombrar el audio');
                }
                renderAllAudios();
            } else {
                for (const gridId in predefinedAudios) {
                    const audioIndex = predefinedAudios[gridId].findIndex(a => a.id === audio.id);
                     if (audioIndex > -1) {
                        predefinedAudios[gridId][audioIndex].name = newName.trim();
                        break;
                    }
                }
                buttonElement.querySelector('.audio-name').textContent = newName.trim();
                if (currentAudioSrc === buttonElement.dataset.src) {
                    trackNameDisplay.textContent = newName.trim();
                }
            }
        }
    }
    
    function handlePlayAll(playlist) {
        if (!playlist || playlist.length === 0) return;
        currentPlaylist = playlist;
        currentPlaylistIndex = 0;
        playNextInPlaylist();
    }

    function playNextInPlaylist() {
        if (currentPlaylistIndex >= currentPlaylist.length) {
            currentPlaylist = [];
            return;
        }
        const track = currentPlaylist[currentPlaylistIndex];
        const trackPath = track.path || track.data;

        document.querySelectorAll('.announcement-button').forEach(btn => btn.classList.remove('selected'));
        const buttonToSelect = document.querySelector(`.announcement-button[data-src="${CSS.escape(trackPath)}"]`);
        if(buttonToSelect) {
            buttonToSelect.classList.add('selected');
        }

        currentAudioSrc = trackPath;
        audioPlayer.src = currentAudioSrc;
        audioPlayer.play();
        trackNameDisplay.textContent = track.name;
        playerSection.classList.add('ready');
    }
    
    // --- NUEVO: Función para filtrar los audios según la búsqueda ---
    function handleSearchFilter() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const categories = document.querySelectorAll('.category');

        categories.forEach(category => {
            const buttons = category.querySelectorAll('.announcement-button');
            let visibleButtonsCount = 0;

            buttons.forEach(button => {
                const audioName = button.querySelector('.audio-name').textContent.toLowerCase();
                // Si el nombre del audio incluye el término de búsqueda, se muestra
                if (audioName.includes(searchTerm)) {
                    button.style.display = 'flex';
                    visibleButtonsCount++;
                } else {
                    // Si no, se oculta
                    button.style.display = 'none';
                }
            });

            // Si no hay botones visibles en la categoría, se oculta la categoría entera
            if (visibleButtonsCount > 0) {
                category.style.display = 'block';
            } else {
                category.style.display = 'none';
            }
        });
    }


    // --- NUEVO: Selector de categoría y subida de audio ---
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'audio/mp4, .m4a';
    fileInput.multiple = true;
    fileInput.style.display = 'none';

    // Crear modal simple para elegir/crear categoría
    function showCategoryModal(onSelect) {
        // Obtener categorías existentes
        getAllAudios().then(audios => {
            const categorias = Array.from(new Set(audios.map(a => a.categoria)));
            // Modal básico
            const modalBg = document.createElement('div');
            modalBg.style.position = 'fixed';
            modalBg.style.top = 0;
            modalBg.style.left = 0;
            modalBg.style.width = '100vw';
            modalBg.style.height = '100vh';
            modalBg.style.background = 'rgba(0,0,0,0.5)';
            modalBg.style.display = 'flex';
            modalBg.style.alignItems = 'center';
            modalBg.style.justifyContent = 'center';
            modalBg.style.zIndex = 10000;

            const modal = document.createElement('div');
            modal.style.background = '#222';
            modal.style.padding = '2rem';
            modal.style.borderRadius = '12px';
            modal.style.minWidth = '300px';
            modal.style.color = 'white';
            modal.innerHTML = `
                <h3>Selecciona o crea una categoría</h3>
                <select id="cat-select" style="width:100%;margin:1rem 0;padding:0.5rem;">
                    ${categorias.map(c => `<option value="${c}">${c}</option>`).join('')}
                </select>
                <input id="cat-new" type="text" placeholder="Nueva categoría..." style="width:100%;padding:0.5rem;" />
                <div style="margin-top:1rem;text-align:right;">
                    <button id="cat-cancel" style="margin-right:1rem;">Cancelar</button>
                    <button id="cat-ok">Aceptar</button>
                </div>
            `;
            modalBg.appendChild(modal);
            document.body.appendChild(modalBg);

            modal.querySelector('#cat-cancel').onclick = () => document.body.removeChild(modalBg);
            modal.querySelector('#cat-ok').onclick = () => {
                let cat = modal.querySelector('#cat-new').value.trim() || modal.querySelector('#cat-select').value;
                if (!cat) cat = 'General';
                document.body.removeChild(modalBg);
                onSelect(cat);
            };
        });
    }

    fab.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', async (event) => {
        const files = event.target.files;
        if (files.length === 0) return;

        showCategoryModal(async (categoria) => {
            for (const file of files) {
                const formData = new FormData();
                formData.append('audio', file);
                formData.append('nombre', file.name.replace(/\.[^/.]+$/, ''));
                formData.append('categoria', categoria);

                try {
                    const response = await fetch('backend/audios.php', {
                        method: 'POST',
                        body: formData
                    });
                    if (!response.ok) throw new Error('Error al subir el audio');
                } catch (e) {
                    alert('No se pudo subir el audio: ' + file.name);
                }
            }
            fileInput.value = '';
            renderAllAudios();
        });
    });

    // --- 6. EVENTOS DEL REPRODUCTOR Y OTROS ---
    audioPlayer.addEventListener('play', () => playButtonIcon.className = 'fa-solid fa-pause');
    audioPlayer.addEventListener('pause', () => playButtonIcon.className = 'fa-solid fa-play');
    audioPlayer.addEventListener('ended', () => {
        playButtonIcon.className = 'fa-solid fa-play';
        if (currentPlaylist.length > 0) {
            currentPlaylistIndex++;
            playNextInPlaylist();
        }
    });
    audioPlayer.addEventListener('timeupdate', () => {
        if (audioPlayer.duration) {
            progressBar.style.width = `${(audioPlayer.currentTime / audioPlayer.duration) * 100}%`;
        }
    });

    playButton.addEventListener('click', togglePlayPause);
    
    function handleSeek(event) {
        if (!audioPlayer.duration || isNaN(audioPlayer.duration)) {
            return;
        }
        const progressBarWidth = progressBarContainer.offsetWidth;
        const clickPositionX = event.offsetX;
        const seekPercentage = clickPositionX / progressBarWidth;
        audioPlayer.currentTime = seekPercentage * audioPlayer.duration;
    }

    progressBarContainer.addEventListener('click', handleSeek);
    
    // --- NUEVO: Evento que activa el filtro cada vez que el usuario escribe ---
    searchInput.addEventListener('input', handleSearchFilter);

    // --- 7. INICIALIZACIÓN ---
    renderAllAudios();
});