document.addEventListener('DOMContentLoaded', () => {
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
    // --- NUEVO: Selección de la barra de búsqueda ---
    const searchInput = document.querySelector('.search-bar input');


    // --- 2. CONFIGURACIÓN INICIAL ---
    const audioPlayer = new Audio();
    let currentAudioSrc = null;
    let currentPlaylist = [];
    let currentPlaylistIndex = 0;

    const predefinedAudios = {
        'general-grid': [
            { id: 'predefined-1', name: 'Bienvenida', path: 'audio/Bienvenida.m4a' },
            { id: 'predefined-2', name: 'Permanecer Asientos', path: 'audio/Permanecer en sus asientos.m4a' }
        ],
        'train-grid': [
            { id: 'predefined-3', name: 'Autoferro 26', path: 'audio/Autoferro 26.m4a' },
            { id: 'predefined-4', name: 'Tren 253', path: 'audio/Tren 253.m4a' },
            { id: 'predefined-5', name: 'Tren 254', path: 'audio/Tren 254.m4a' },
            { id: 'predefined-6', name: 'Tren 255', path: 'audio/Tren 255.m4a' },
            { id: 'predefined-7', name: 'Tren 256', path: 'audio/Tren 256.m4a' },
            { id: 'predefined-8', name: 'Tren 257', path: 'audio/Tren 257.m4a' },
            { id: 'predefined-9', name: 'Tren 258', path: 'audio/Tren 258.m4a' },
            { id: 'predefined-10', name: 'Tren 577', path: 'audio/Tren 577.m4a' }
        ]
    };

    // --- 3. MANEJO DE DATOS CON BACKEND (GET) ---
    async function getAddedAudios() {
        try {
            const response = await fetch('backend/audios.php');
            if (!response.ok) throw new Error('Error al obtener audios');
            const audios = await response.json();
            // Adaptar para que coincida con el resto del código (id, name, path)
            return audios.map(a => ({
                id: a.id,
                name: a.nombre,
                path: `backend/audios.php?id=${a.id}&action=download`,
                extension: a.extension
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

        const actionsContainer = document.createElement('div');
        actionsContainer.className = 'button-actions';

        const renameBtn = document.createElement('button');
        renameBtn.className = 'rename-audio-btn';
        renameBtn.innerHTML = '<i class="fa-solid fa-pencil"></i>';
        renameBtn.title = 'Cambiar nombre';
        renameBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            handleRenameAudio(audio, button);
        });

        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'delete-audio-btn';
        deleteBtn.innerHTML = '<i class="fa-solid fa-times"></i>';
        deleteBtn.title = 'Eliminar este audio';
        deleteBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            handleDeleteAudio(audio, button);
        });

        actionsContainer.appendChild(renameBtn);
        actionsContainer.appendChild(deleteBtn);
        button.appendChild(actionsContainer);

        if (audio.isAdded) {
            button.classList.add('added-audio');
        }

        button.addEventListener('click', () => selectAndPlayAnnouncement(button));
        return button;
    }

    async function renderAllAudios() {
        for (const gridId in predefinedAudios) {
            const grid = document.getElementById(gridId);
            const category = grid.parentElement;
            grid.innerHTML = '';
            addPlayAllButton(category, predefinedAudios[gridId]);
            predefinedAudios[gridId].forEach(audio => {
                grid.appendChild(createButton(audio));
            });
        }

        const addedAudios = await getAddedAudios();
        let addedCategory = document.querySelector('#added-audio-category');

        if (addedAudios.length === 0 && addedCategory) {
            addedCategory.remove();
            return;
        }

        if (addedAudios.length > 0) {
            if (!addedCategory) {
                addedCategory = document.createElement('div');
                addedCategory.className = 'category';
                addedCategory.id = 'added-audio-category';
                addedCategory.innerHTML = `
                    <div class="category-header">
                        <h3><i class="fa-solid fa-upload"></i> Audios Añadidos</h3>
                    </div>
                    <div class="button-grid" id="added-audio-grid"></div>`;
                announcementsSection.appendChild(addedCategory);
            }

            addPlayAllButton(addedCategory, addedAudios);

            const addedGrid = document.querySelector('#added-audio-grid');
            addedGrid.innerHTML = '';
            addedAudios.forEach(audio => {
                addedGrid.appendChild(createButton({ ...audio, isAdded: true }));
            });
        }
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


    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'audio/mp4, .m4a';
    fileInput.multiple = true;
    fileInput.style.display = 'none';

    fab.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', async (event) => {
        const files = event.target.files;
        if (files.length === 0) return;

        for (const file of files) {
            const formData = new FormData();
            formData.append('audio', file);
            formData.append('nombre', file.name.replace(/\.[^/.]+$/, ''));

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