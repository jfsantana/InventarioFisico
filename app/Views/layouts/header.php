<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/css/styles.css">
</head>
<body>
    <?php if (Auth::check()) : ?>
        <?php $authUser = Auth::user(); ?>
        <header class="auth-topbar">
            <a class="auth-brand" href="<?= APP_URL ?>/">
                <img src="<?= APP_URL ?>/public/media/logoAdyarca.png" alt="Logo Adyarca">
                <strong><?= APP_NAME ?></strong>
            </a>
            <div class="auth-session">
                <span class="auth-greeting">👤 Hola, <?= htmlspecialchars($authUser['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="role-badge role-badge--<?= strtolower(str_replace(' ', '-', $authUser['rol_nombre'])) ?>"><?= htmlspecialchars($authUser['rol_nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                <form method="post" action="<?= APP_URL ?>/auth/logout" onsubmit="return confirm('¿Deseas cerrar tu sesión?');">
                    <?= Auth::csrfField() ?>
                    <button class="logout-button" type="submit">Cerrar sesión</button>
                </form>
            </div>
        </header>
    <?php else : ?>
        <a class="brand-logo <?= ($title ?? '') === 'Inicio' ? 'brand-logo--dashboard-hidden' : '' ?>" href="<?= APP_URL ?>/" aria-label="Ir al inicio">
            <img src="<?= APP_URL ?>/public/media/logoAdyarca.png" alt="Logo Adyarca">
        </a>
    <?php endif; ?>
    <main class="page-shell <?= ($title ?? '') === 'Inicio' ? 'page-shell--dashboard' : '' ?>">
