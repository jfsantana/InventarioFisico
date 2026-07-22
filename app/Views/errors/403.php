<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<section class="panel forbidden-panel">
    <p class="eyebrow">Acceso restringido</p>
    <h1>403</h1>
    <p class="intro">No tienes permiso para entrar a este modulo. Si necesitas acceso, solicita la autorizacion a un administrador.</p>
    <a class="button-link" href="<?= APP_URL ?>/">Volver al menu</a>
</section>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>