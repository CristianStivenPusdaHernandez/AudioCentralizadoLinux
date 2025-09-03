<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="assets/LogoTren.PNG">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consola de Control - Tren del Tayta Imbabura</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="css/modals.css">
    <link rel="stylesheet" href="css/player-controls.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'components/login.php'; ?>

    <div class="app-container hidden">
        <header>
            <div class="logo">
                <img src="assets/LogoTren.PNG" alt="Logo Tren del Tayta Imbabura">
            </div>
            <h2 id="titulo">Consola de Audio</h2>
            <div class="user-info" id="user-info">
                <span id="user-name"></span>
                <span id="user-role"></span>
                <button id="users-btn" class="icon-button" style="display: none;" title="Gestionar usuarios">
                    <i class="fa-solid fa-users"></i>
                </button>
                <button id="logout-btn" class="icon-button">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </button>
            </div>
        </header>

        <main>
            <section class="player-section">
                <div class="player-controls">
                    <button class="stop-button" id="stop-button" title="Detener audio">
                        <i class="fa-solid fa-stop"></i>
                    </button>
                    <button class="play-button large">
                        <i class="fa-solid fa-play"></i>
                    </button>
                    <button class="repeat-button" id="repeat-button" title="Repetir audio">
                        <i class="fa-solid fa-repeat"></i>
                    </button>
                </div>
                <div class="audio-progress" id="audio-progress">
                    <div class="audio-title" id="audio-title">Selecciona un audio</div>
                    <div class="progress-bar" id="progress-bar">
                        <div class="progress-fill" id="progress-fill"></div>
                    </div>
                    <div class="time-display">
                        <span id="current-time">0:00</span>
                        <span id="total-time">0:00</span>
                    </div>
                    <div class="server-status" id="connection-status">
                        <span class="status-dot"></span> Servidor
                    </div>
                </div>
            </section>

            <section class="announcements-section">
                <div class="search-bar">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Buscar anuncio...">
                </div>

                <!--Filtros-->
                <div class="filter-controls">
                    <div class="filter-group">
                        <label for="sort-select">Ordenar por:</label>
                        <select id="sort-select">
                            <option value="nombre">Nombre</option>
                            <option value="categoria">Categoría</option>
                            <option value="fecha_subida">Fecha</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="order-select">Orden:</label>
                        <select id="order-select">
                            <option value="asc">Ascendente</option>
                            <option value="desc">Descendente</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="category-filter">Categoría:</label>
                        <select id="category-filter">
                            <option value="">Todas</option>
                        </select>
                    </div>
                </div>

                <div class="category">
                    <div class="category-header">
                        <h3><i class="fa-solid fa-play"></i> Anuncios Generales</h3>
                        <div class="category-buttons">
                            <button class="reproducir-todo">Reproducir Todo</button>
                        </div>
                    </div>
                    <div class="button-grid" id="general-grid"></div>
                </div>

                <div class="category">
                    <div class="category-header">
                        <h3><i class="fa-solid fa-train"></i> Anuncios de Tren</h3>
                        <div class="category-buttons">
                            <button class="reproducir-todo">Reproducir Todo</button>
                        </div>
                    </div>
                    <div class="button-grid" id="train-grid"></div>
                </div>
            </section>
        </main>

        <button class="fab">
            <i class="fa-solid fa-plus"></i>
        </button>
    </div>

    <?php include 'components/modals.php'; ?>

    <script src="js/main.js"></script>
</body>
</html>