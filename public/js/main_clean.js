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

    const playNext = async () => {
        if (!isPlayingAll || currentIndex >= audios.length) {
            isPlayingAll = false;
            return;
        }
        await playAudio(audios[currentIndex].id, audios[currentIndex].url, audios[currentIndex].nombre);
        playAllInterval = setInterval(async () => {
            try {
                const statusResponse = await fetch('/App_Estacion/api/player/status', {
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
                if (!isPlaying || !isPlayingAll) {
                    clearInterval(playAllInterval);
                    playAllInterval = null;
                    if (isPlayingAll) {
                        currentIndex++;
                        if (currentIndex < audios.length) {
                            setTimeout(playNext, 500);
                        } else {
                            isPlayingAll = false;
                        }
                    }
                }
            }
        }, 1000);
    };
    playNext();
};