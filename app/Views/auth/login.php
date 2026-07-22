<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesion - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/css/styles.css">
</head>
<body class="login-body">
    <main class="login-shell">
        <div class="login-brand">
            <img src="<?= APP_URL ?>/public/media/logoAdyarca.png" alt="Logo Adyarca">
            <strong><?= APP_NAME ?></strong>
        </div>

        <section class="login-card" aria-labelledby="login-title">
            <p class="eyebrow">Acceso seguro</p>
            <h1 id="login-title">Iniciar sesion</h1>

            <?php if (!empty($error)) : ?>
                <div class="message message--error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form class="login-form" method="post" action="<?= APP_URL ?>/auth/autenticar" novalidate>
                <?= Auth::csrfField() ?>
                <label class="icon-field">
                    <span>Usuario</span>
                    <div>
                        <i aria-hidden="true">👤</i>
                        <input name="username" type="text" value="<?= htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8') ?>" required autocomplete="username" autofocus>
                    </div>
                </label>

                <label class="icon-field">
                    <span>Contraseña</span>
                    <div>
                        <i aria-hidden="true">🔒</i>
                        <input id="loginPassword" name="password" type="password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" data-password-toggle="loginPassword">Mostrar</button>
                    </div>
                </label>

                <label class="checkbox-line">
                    <input name="remember" type="checkbox" value="1">
                    <span>Recordar sesión por 8 horas</span>
                </label>

                <button class="button-link button-link--submit login-submit" type="submit">Ingresar</button>
            </form>
        </section>
    </main>
    <script src="<?= APP_URL ?>/public/js/login.js"></script>
</body>
</html>