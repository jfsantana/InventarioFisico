<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/css/styles.css">
</head>
<body>
    <a class="brand-logo <?= ($title ?? '') === 'Inicio' ? 'brand-logo--dashboard-hidden' : '' ?>" href="<?= APP_URL ?>/" aria-label="Ir al inicio">
        <img src="<?= APP_URL ?>/public/media/logoAdyarca.png" alt="Logo Adyarca">
    </a>
    <main class="page-shell <?= ($title ?? '') === 'Inicio' ? 'page-shell--dashboard' : '' ?>">
