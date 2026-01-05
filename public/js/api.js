class ApiClient {
    constructor() {
        this.baseUrl = '/AudioCentralizadoLinux/public';
    }

    async request(endpoint, options = {}) {
        const config = {
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };

        try {
            const response = await fetch(`${this.baseUrl}${endpoint}`, config);
            
            if (!response.ok) {
                const error = await response.json().catch(() => ({ error: 'Error de red' }));
                throw new Error(error.error || `HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // Auth endpoints
    async login(usuario, password) {
        return this.request('/api/auth', {
            method: 'POST',
            body: JSON.stringify({ usuario, password })
        });
    }

    async checkSession() {
        return this.request('/api/auth');
    }

    async logout() {
        return this.request('/api/logout', { method: 'POST' });
    }

    // Audio endpoints
    async getAudios() {
        return this.request('/api/audios');
    }

    async createAudio(formData) {
        return this.request('/api/audios', {
            method: 'POST',
            headers: {},
            body: formData
        });
    }

    async updateAudio(id, nombre) {
        return this.request(`/api/audios/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `nombre=${encodeURIComponent(nombre)}`
        });
    }

    async deleteAudio(id) {
        return this.request(`/api/audios/${id}`, { method: 'DELETE' });
    }

    async updateCategory(oldCategory, newCategory) {
        return this.request('/api/audios/category', {
            method: 'PATCH',
            body: JSON.stringify({ old_category: oldCategory, new_category: newCategory })
        });
    }

    // Player endpoints
    async playAudio(audioId, repeat = false) {
        return this.request('/api/player', {
            method: 'POST',
            body: JSON.stringify({ audio_id: audioId, repeat })
        });
    }

    async stopAudio() {
        return this.request('/api/player/stop', { method: 'POST' });
    }

    async getPlayerStatus() {
        return this.request('/api/player/status');
    }

    async updatePlayerStatus(data) {
        return this.request('/api/player/status', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
}