<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<section class="panel">
    <p class="eyebrow">Base de datos</p>
    <h1>Test de conexion</h1>
    <p class="intro">Prueba de conexion a MySQL local usando la base de datos configurada para el proyecto.</p>

    <div class="status-card <?= empty($error) ? 'status-card--success' : 'status-card--error' ?>">
        <span>Resultado</span>
        <strong><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></strong>
        <?php if (!empty($serverInfo)) : ?>
            <small>Version del servidor MySQL: <?= htmlspecialchars($serverInfo, ENT_QUOTES, 'UTF-8') ?></small>
        <?php endif; ?>
        <?php if (!empty($error)) : ?>
            <small><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></small>
        <?php endif; ?>
    </div>

    <dl class="connection-list">
        <?php foreach ($connectionData as $label => $value) : ?>
            <div>
                <dt><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></dt>
                <dd><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>

    <a class="button-link" href="<?= APP_URL ?>/">Volver al inicio</a>
</section>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
