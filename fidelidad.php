<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';

requireAuth(); // Redirige a login si no hay sesión
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Fidelidad - Kosheru</title>
    <script>
        (function () {
            var saved = localStorage.getItem('kosheru_theme');
            document.documentElement.setAttribute('data-theme', saved === 'dark' ? 'dark' : 'light');
        })();
    </script>
    <link rel="stylesheet" href="css/style.css?v=mobile-20260927-6">
    <link rel="stylesheet" href="css/fidelidad.css">
    <link rel="icon" href="img/logos/Isotipo transparente.png" type="image/png">
</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <section class="tienda-hero">
        <div class="tienda-breadcrumb"><a href="index.php">Inicio</a> / <span>Mi Fidelidad</span></div>
        <h1 class="tienda-hero-title">Mi Fidelidad Kosheru</h1>
        <p class="tienda-hero-desc">Gana puntos con cada compra y canjéalos por descuentos en tu próximo pedido.</p>
    </section>

    <div class="fidelidad-container" id="fidelidadRoot">
        <div class="fidelidad-loading">Cargando tu progreso...</div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="js/app.js"></script>
    <script src="js/theme.js"></script>
    <script src="js/header.js"></script>
    <script src="js/carrito.js"></script>
    <script src="js/fidelidad.js"></script>
</body>
</html>