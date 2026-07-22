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
       

        <section class="login-card" aria-labelledby="login-title">
             <div class="login-brand">
            <img src="<?= APP_URL ?>/public/media/logoAdyarca.png" alt="Logo Adyarca">
        </div>

            <h1 id="login-title" style="text-align: center;">Iniciar sesion</h1>

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

            <a class="public-access-link" href="<?= APP_URL ?>/salida" aria-label="Ir a registrar entrega sin iniciar sesion">
                <span aria-hidden="true">↗</span>
                <strong>Registrar entrega sin iniciar sesión</strong>
                <small>Acceso para usuarios que solo reportan salidas</small>
            </a>
        </section>
    </main>
    <script src="<?= APP_URL ?>/public/js/login.js"></script>
</body>
</html>