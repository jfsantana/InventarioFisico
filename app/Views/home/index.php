<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$canSalida = Auth::can('salida');
$canAdmin = Auth::can('administracion');
$authUser = Auth::user();
?>

<section class="panel dashboard-home">


    <div class="dashboard-hero">
        <h1><?= APP_NAME ?></h1>
    </div>

    <nav class="main-menu main-menu--operational" aria-label="Menu principal">
        <section class="menu-section menu-section--primary menu-section--movimientos">
            <span>Operacion diaria</span>
            <h2>Movimientos</h2>
            <div class="menu-section-grid">
                <a class="menu-card menu-card--entrada" href="<?= APP_URL ?>/entrada">
                    <span class="menu-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 3v12m0 0 5-5m-5 5-5-5M4 19h16" /></svg>
                    </span>
                    <span>Registrar recepcion</span>
                    <strong>Entrada de mercancia</strong>
                    <i aria-hidden="true">→</i>
                </a>
                <a class="menu-card menu-card--predespacho" href="<?= APP_URL ?>/predespacho">
                    <span class="menu-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M3 7h11v9H3z" /><path d="M14 10h3l3 3v3h-6z" /><path d="M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" /><path d="M17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" /><path d="M9 4v3" /><path d="M7 5h4" /></svg>
                    </span>
                    <span>Gestionar despacho</span>
                    <strong>Pre Despacho</strong>
                    <i aria-hidden="true">→</i>
                </a>
                <a class="menu-card menu-card--salida <?= $canSalida ? '' : 'menu-card--disabled' ?>" href="<?= $canSalida ? APP_URL . '/salida' : '#' ?>" <?= $canSalida ? '' : 'aria-disabled="true" title="Sin acceso"' ?>>
                    <span class="menu-card-icon" aria-hidden="true">
                        <?php if ($canSalida) : ?>
                            <svg viewBox="0 0 24 24"><path d="M12 21V9m0 0 5 5m-5-5-5 5M4 5h16" /></svg>
                        <?php else : ?>
                            🔒
                        <?php endif; ?>
                    </span>
                    <span>Registrar entrega</span>
                    <strong>Salida de inventario</strong>
                    <i aria-hidden="true">→</i>
                </a>
            </div>
        </section>

        <section class="menu-section menu-section--correcciones">
            <span>Correcciones</span>
            <h2>Auditar y ajustar</h2>
            <div class="menu-section-grid">
                <a class="menu-card menu-card--correccion" href="<?= APP_URL ?>/entrada/detalle">
                    <span class="menu-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 20h4L18.5 9.5a2.8 2.8 0 0 0-4-4L4 16v4Z" /><path d="m13.5 6.5 4 4" /></svg>
                    </span>
                    <span>Detalle creado</span>
                    <strong>Corregir entradas</strong>
                    <i aria-hidden="true">→</i>
                </a>
                <a class="menu-card menu-card--correccion" href="<?= APP_URL ?>/salida/detalle">
                    <span class="menu-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 20h4L18.5 9.5a2.8 2.8 0 0 0-4-4L4 16v4Z" /><path d="m13.5 6.5 4 4" /></svg>
                    </span>
                    <span>Detalle creado</span>
                    <strong>Corregir salidas</strong>
                    <i aria-hidden="true">→</i>
                </a>
            </div>
        </section>

        <section class="menu-section menu-section--wide menu-section--reportes">
            <span>Consulta y direccion</span>
            <h2>Reportes</h2>
            <div class="menu-section-grid menu-section-grid--three">
                <a class="menu-card menu-card--reporte" href="<?= APP_URL ?>/reporte">
                    <span class="menu-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M5 3h14v18H5z" /><path d="M8 8h8M8 12h8M8 16h5" /></svg>
                    </span>
                    <span>Consultar movimientos</span>
                    <strong>Reporte por lote</strong>
                    <i aria-hidden="true">→</i>
                </a>
                <a class="menu-card menu-card--reporte" href="<?= APP_URL ?>/analitica">
                    <span class="menu-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 19V5" /><path d="M4 19h16" /><path d="M8 16v-5M12 16V8M16 16v-8" /></svg>
                    </span>
                    <span>Junta directiva</span>
                    <strong>Inteligencia de inventario</strong>
                    <i aria-hidden="true">→</i>
                </a>
                <a class="menu-card menu-card--conexion" href="<?= APP_URL ?>/conexion">
                    <span class="menu-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 4c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3Z" /><path d="M4 7v5c0 1.7 3.6 3 8 3s8-1.3 8-3V7" /><path d="M4 12v5c0 1.7 3.6 3 8 3s8-1.3 8-3v-5" /></svg>
                    </span>
                    <span>Base de datos</span>
                    <strong>Probar conexion</strong>
                    <i aria-hidden="true">→</i>
                </a>
            </div>
        </section>

        <?php if ($canAdmin) : ?>
            <section class="menu-section menu-section--wide menu-section--admin">
                <span>Administración y seguridad</span>
                <h2>Gestión del sistema</h2>
                <div class="menu-section-grid menu-section-grid--three">
                    <a class="menu-card menu-card--admin" href="<?= APP_URL ?>/admin/usuarios">
                        <span class="menu-card-icon" aria-hidden="true">👥</span>
                        <span>Gestión de accesos</span>
                        <strong>Usuarios</strong>
                        <i aria-hidden="true">→</i>
                    </a>
                    <a class="menu-card menu-card--admin" href="<?= APP_URL ?>/admin/roles">
                        <span class="menu-card-icon" aria-hidden="true">🛡️</span>
                        <span>Control de roles</span>
                        <strong>Roles y permisos</strong>
                        <i aria-hidden="true">→</i>
                    </a>
                    <a class="menu-card menu-card--admin" href="<?= APP_URL ?>/admin/log">
                        <span class="menu-card-icon" aria-hidden="true">📋</span>
                        <span>Auditoría</span>
                        <strong>Log de accesos</strong>
                        <i aria-hidden="true">→</i>
                    </a>
                </div>
            </section>
        <?php endif; ?>
    </nav>
</section>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
