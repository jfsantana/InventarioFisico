<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<section class="panel">
    <p class="eyebrow">MVC en PHP</p>
    <h1><?= APP_NAME ?></h1>
    <p class="intro">Aplicacion base lista para conectar tus controladores, modelos y vistas.</p>

    <div class="status-card">
        <span>Estado de base de datos</span>
        <strong><?= htmlspecialchars($connectionStatus, ENT_QUOTES, 'UTF-8') ?></strong>
        <?php if (!empty($connectionError)) : ?>
            <small><?= htmlspecialchars($connectionError, ENT_QUOTES, 'UTF-8') ?></small>
        <?php endif; ?>
    </div>

    <nav class="main-menu" aria-label="Menu principal">
        <a class="menu-card" href="<?= APP_URL ?>/entrada">
            <span>Registrar recepcion</span>
            <strong>Entrada de inventario</strong>
        </a>
        <a class="menu-card" href="<?= APP_URL ?>/salida">
            <span>Registrar entrega</span>
            <strong>Salida de inventario</strong>
        </a>
        <a class="menu-card" href="<?= APP_URL ?>/reporte">
            <span>Consultar movimientos</span>
            <strong>Reporte por lote</strong>
        </a>
        <a class="menu-card" href="<?= APP_URL ?>/conexion">
            <span>Base de datos</span>
            <strong>Probar conexion</strong>
        </a>
    </nav>
</section>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
