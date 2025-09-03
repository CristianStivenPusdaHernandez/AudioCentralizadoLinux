<!-- Modal para agregar audio -->
<div id="audio-modal" class="modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Agregar Nuevo Audio</h3>
            <span class="close">&times;</span>
        </div>
        <form id="audio-form" enctype="multipart/form-data">
            <div class="form-group">
                <label for="audio-name">Nombre del audio:</label>
                <input type="text" id="audio-name" name="nombre" required>
            </div>
            <div class="form-group">
                <label for="audio-category">Categoría:</label>
                <select id="audio-category" name="categoria" required>
                    <option value="">Seleccionar categoría</option>
                    <option value="ANUNCIOS GENERALES">Anuncios Generales</option>
                    <option value="ANUNCIOS DEL TREN">Anuncios del Tren</option>
                    <option value="nueva">+ Crear nueva categoría</option>
                </select>
            </div>
            <div class="form-group nueva-categoria-group" id="nueva-categoria-group">
                <label for="nueva-categoria">Nueva categoría:</label>
                <input type="text" id="nueva-categoria" placeholder="Nombre de la nueva categoría">
            </div>
            <div class="form-group">
                <label for="audio-file">Archivo de audio (mp3/m4a):</label>
                <input type="file" id="audio-file" name="audio" accept="audio/*" required>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel">Cancelar</button>
                <button type="submit" class="btn-submit">Subir Audio</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para gestión de usuarios -->
<div id="users-modal" class="modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Gestión de Usuarios</h3>
            <span class="close" id="users-close">&times;</span>
        </div>
        <div class="users-content">
            <div class="users-actions">
                <button id="add-user-btn" class="btn-primary">Agregar Usuario</button>
            </div>
            <div class="users-list" id="users-list">
                <!-- Lista de usuarios se carga aquí -->
            </div>
        </div>
    </div>
</div>

<!-- Modal para agregar/editar usuario -->
<div id="user-form-modal" class="modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="user-form-title">Agregar Usuario</h3>
            <span class="close" id="user-form-close">&times;</span>
        </div>
        <form id="user-form">
            <div class="form-group">
                <label for="user-username">Usuario:</label>
                <input type="text" id="user-username" name="usuario" required>
            </div>
            <div class="form-group">
                <label for="user-password">Contraseña:</label>
                <input type="password" id="user-password" name="password" required>
            </div>
            <div class="form-group">
                <label for="user-role">Rol:</label>
                <select id="user-role" name="rol" required>
                    <option value="admin">Administrador</option>
                    <option value="operator">Operador</option>
                    <option value="viewer">Solo lectura</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" id="user-form-cancel">Cancelar</button>
                <button type="submit" class="btn-submit">Guardar</button>
            </div>
        </form>
    </div>
</div>