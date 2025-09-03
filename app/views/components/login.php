<div id="login-screen" class="login-wrapper">
    <div class="login-container">
        <h2>Iniciar Sesión</h2>
        <form id="login-form">
            <input type="text" id="usuario" placeholder="Usuario" required>
            <div class="password-container">
                <input type="password" id="password" placeholder="Contraseña" required class="password-input">
                <span id="toggle-password">
                    <i class="fa-solid fa-eye-slash" id="eye-icon"></i>
                </span>
            </div>
            <button type="submit">Iniciar Sesión</button>
        </form>
        <div id="mensaje-error"></div>
    </div>
</div>