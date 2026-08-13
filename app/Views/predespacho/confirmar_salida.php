<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$text = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$resumen = $resumen ?? null;
$resultado = $resultado ?? null;
$codigoInterno = (string) ($codigoInterno ?? '');
$tokenCierre = (string) ($tokenCierre ?? '');
$errorAutenticacion = $errorAutenticacion ?? null;
$requiereAutenticacion = (bool) ($requiereAutenticacion ?? false);
$usuarioAutorizado = $usuarioAutorizado ?? null;
$qrDisponible = (bool) ($qrDisponible ?? false);
$predespachoCerrado = (bool) ($predespachoCerrado ?? false);
$estado = (string) ($resumen['statusGeneralPredespacho'] ?? '');
?>

<?php if (!$resumen) : ?>
    <section class="dispatch-confirmation" aria-labelledby="dispatch-confirmation-title">
        <div class="dispatch-confirmation__notice message message--error" role="alert">
            <h1 id="dispatch-confirmation-title">Enlace no válido</h1>
            <p>Este código QR no corresponde a un predespacho registrado.</p>
        </div>
    </section>
<?php elseif ($predespachoCerrado) : ?>
    <section class="dispatch-confirmation" aria-labelledby="dispatch-confirmation-title">
        <div class="dispatch-confirmation__notice message message--success" role="status">
            <p class="eyebrow">Control de salida</p>
            <h1 id="dispatch-confirmation-title">Predespacho ya cerrado</h1>
            <p class="dispatch-confirmation__code"><?= $text($resumen['codigoInterno']) ?></p>
            <p>Este código QR ya fue utilizado. No se realizó ninguna operación adicional.</p>
            <?php if (!empty($resumen['usuarioCierre'])) : ?>
                <p>Cerrado por <?= $text($resumen['usuarioCierre']) ?> el <?= $text($resumen['fechaCierre']) ?>.</p>
            <?php endif; ?>
        </div>
    </section>
<?php elseif (!$qrDisponible) : ?>
    <section class="dispatch-confirmation" aria-labelledby="dispatch-confirmation-title">
        <div class="dispatch-confirmation__notice message message--error" role="alert">
            <h1 id="dispatch-confirmation-title">Código QR no disponible</h1>
            <p>Este predespacho debe estar embarcado o cerrado para utilizar el control de salida.</p>
        </div>
    </section>
<?php elseif ($requiereAutenticacion) : ?>
    <section class="panel salida-auth-page" aria-hidden="true">
        <p class="eyebrow">Control de salida</p>
        <h1>Registrar despacho</h1>
    </section>

    <div class="delivery-auth-modal" role="dialog" aria-modal="true" aria-label="Iniciar sesión para registrar despacho">
        <section class="delivery-auth-card">
            <?php if ($errorAutenticacion) : ?>
                <div class="message message--error" role="alert"><?= $text($errorAutenticacion) ?></div>
            <?php endif; ?>

            <form class="delivery-auth-form" method="post">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="accion" value="autenticar">
                <input type="hidden" name="codigo" value="<?= $text($codigoInterno) ?>">
                <input type="hidden" name="token" value="<?= $text($tokenCierre) ?>">
                <label>
                    Usuario
                    <input name="username" type="text" required autocomplete="username" autofocus>
                </label>
                <label>
                    Contraseña
                    <span class="delivery-password-field">
                        <input id="dispatchLoginPassword" name="password" type="password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" data-password-toggle="dispatchLoginPassword">Mostrar</button>
                    </span>
                </label>
                <button class="button-link button-link--submit" type="submit">Ingresar</button>
            </form>
        </section>
    </div>
<?php else : ?>
    <section class="dispatch-confirmation" aria-labelledby="dispatch-confirmation-title">
        <header class="dispatch-confirmation__header">
            <p class="eyebrow">Control de salida</p>
            <h1 id="dispatch-confirmation-title"><?= $estado === 'cerrado' ? 'Predespacho cerrado' : 'Confirmar despacho' ?></h1>
            <p class="dispatch-confirmation__code"><?= $text($resumen['codigoInterno']) ?></p>
        </header>

        <?php if ($resultado) : ?>
            <div class="message <?= !empty($resultado['success']) ? 'message--success' : 'message--error' ?>" role="status">
                <?= $text($resultado['mensaje'] ?? '') ?>
            </div>
        <?php endif; ?>

        <dl class="dispatch-confirmation__summary">
            <div><dt>Cliente</dt><dd><?= $text($resumen['nombreCliente']) ?></dd></div>
            <div><dt>RIF</dt><dd><?= $text($resumen['rifCliente']) ?></dd></div>
            <div><dt>Fecha de retiro</dt><dd><?= $text($resumen['fechaRetiro']) ?></dd></div>
            <div><dt>Código SAP</dt><dd><?= $text($resumen['codigoNotaEntregaSAP'] ?: 'Sin código') ?></dd></div>
            <div><dt>Estado</dt><dd><span class="status-pill <?= $estado === 'embarcado' ? 'is-pending' : 'is-closed' ?>"><?= $text($estado) ?></span></dd></div>
            <?php if ($usuarioAutorizado) : ?>
                <div><dt>Responsable</dt><dd><?= $text($usuarioAutorizado['nombre']) ?> (<?= $text($usuarioAutorizado['username']) ?>)</dd></div>
            <?php elseif (!empty($resumen['usuarioCierre'])) : ?>
                <div><dt>Cerrado por</dt><dd><?= $text($resumen['usuarioCierre']) ?></dd></div>
                <div><dt>Fecha de cierre</dt><dd><?= $text($resumen['fechaCierre']) ?></dd></div>
            <?php endif; ?>
        </dl>

        <section class="dispatch-confirmation__items" aria-labelledby="dispatch-items-title">
            <h2 id="dispatch-items-title">Resumen de productos</h2>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Producto</th><th>Cantidad despachada</th></tr></thead>
                    <tbody>
                        <?php foreach ($resumen['items'] as $item) : ?>
                            <tr>
                                <td data-label="Producto"><?= $text($item['nombreProducto']) ?></td>
                                <td data-label="Cantidad despachada"><?= number_format((float) $item['cantidadDespachada'], 2, '.', '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($estado === 'embarcado') : ?>
            <form class="dispatch-confirmation__action" method="post">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="accion" value="confirmar">
                <input type="hidden" name="codigo" value="<?= $text($codigoInterno) ?>">
                <input type="hidden" name="token" value="<?= $text($tokenCierre) ?>">
                <p>¿Está seguro de dar salida y cerrar definitivamente este predespacho?</p>
                <button class="button-link button-link--submit" type="submit">Sí, despachar y cerrar</button>
            </form>
        <?php elseif ($estado !== 'cerrado') : ?>
            <div class="message message--error" role="status">El predespacho todavía no está embarcado y no puede cerrarse.</div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<script src="<?= APP_URL ?>/public/js/login.js"></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>