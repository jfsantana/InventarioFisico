<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$text = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$silos = $silos ?? [];
$entradasPendientes = $entradasPendientes ?? [];
$filters = $filters ?? [];
$messageType = $messageType ?? 'success';
?>

<section class="panel report-panel admin-page silo-admin-page" data-silo-admin data-availability-endpoint="<?= APP_URL ?>/admin/silosDisponibles">
    <div class="admin-heading">
        <div>
            <p class="eyebrow">Administración y seguridad</p>
            <h1>Control de silos</h1>
            <p class="intro">Capacidad y ocupación física de los silos utilizados por productos de Sector3.</p>
        </div>
        <button class="button-link button-link--submit" type="button" data-open-silo-modal>+ Nuevo silo</button>
    </div>

    <?php if (!empty($message)) : ?>
        <div class="message message--<?= $messageType === 'error' ? 'error' : 'success' ?>" role="status"><?= $text($message) ?></div>
    <?php endif; ?>

    <form class="admin-filters" method="get" action="<?= APP_URL ?>/admin/silos">
        <input name="q" type="search" value="<?= $text($filters['q'] ?? '') ?>" placeholder="Buscar por código, nombre o producto">
        <button class="button-link button-link--submit" type="submit">Buscar</button>
        <?php if (!empty($filters['q'])) : ?><a class="button-link button-link--secondary" href="<?= APP_URL ?>/admin/silos">Limpiar</a><?php endif; ?>
    </form>

    <section class="silo-visual-report" aria-labelledby="silo-visual-title">
        <div class="silo-visual-heading">
            <div>
                <p class="eyebrow">Estado en tiempo real</p>
                <h2 id="silo-visual-title">Nivel de ocupación</h2>
            </div>
            <div class="silo-visual-legend" aria-label="Leyenda de ocupación">
                <span><i class="is-available"></i> Menos de 60%</span>
                <span><i class="is-warning"></i> Entre 60% y 84%</span>
                <span><i class="is-critical"></i> 85% o más</span>
            </div>
        </div>

        <?php if (!$silos) : ?>
            <div class="message">Registre un silo para visualizar su nivel.</div>
        <?php else : ?>
            <div class="silo-visual-grid">
                <?php foreach ($silos as $silo) : ?>
                    <?php
                    $capacidad = (float) $silo['capacidad'];
                    $ocupado = (float) $silo['cantidadOcupada'];
                    $disponible = (float) $silo['capacidadDisponible'];
                    $porcentaje = $capacidad > 0 ? max(0, min(100, ($ocupado / $capacidad) * 100)) : 0;
                    $nivelClase = $ocupado <= 0.0005
                        ? 'is-empty'
                        : ($porcentaje >= 85 ? 'is-critical' : ($porcentaje >= 60 ? 'is-warning' : 'is-available'));
                    ?>
                    <article class="silo-visual-card <?= (int) $silo['activo'] === 1 ? '' : 'is-inactive' ?>">
                        <div class="silo-visual-code">
                            <span><?= $text($silo['nombre']) ?></span>
                            <strong><?= $text($silo['codigo']) ?></strong>
                        </div>
                        <div class="silo-vessel <?= $nivelClase ?>" role="img" aria-label="Silo <?= $text($silo['codigo']) ?> al <?= number_format($porcentaje, 1) ?> por ciento, <?= number_format($ocupado, 3) ?> kilogramos ocupados">
                            <div class="silo-vessel-cap"></div>
                            <div class="silo-vessel-body">
                                <div class="silo-vessel-fill" style="height: <?= number_format($porcentaje, 2, '.', '') ?>%"></div>
                                <span class="silo-vessel-percent"><?= number_format($porcentaje, 1) ?>%</span>
                                <span class="silo-vessel-quantity"><?= number_format($ocupado, 3) ?> kg</span>
                            </div>
                            <div class="silo-vessel-hopper"></div>
                            <div class="silo-vessel-legs"><i></i><i></i></div>
                        </div>
                        <div class="silo-visual-product">
                            <span>Producto almacenado</span>
                            <strong><?= $text($silo['productoActual'] ?: 'Silo vacío') ?></strong>
                        </div>
                        <dl class="silo-visual-values">
                            <div><dt>Disponible</dt><dd><?= number_format($disponible, 3) ?> kg</dd></div>
                            <div><dt>Capacidad</dt><dd><?= number_format($capacidad, 3) ?> kg</dd></div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="admin-table-wrap">
        <table class="admin-table silo-table">
            <thead><tr><th>Código</th><th>Nombre</th><th>Producto actual</th><th>Capacidad</th><th>Ocupado</th><th>Disponible</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php if (!$silos) : ?>
                    <tr><td colspan="8">No hay silos registrados.</td></tr>
                <?php else : ?>
                    <?php foreach ($silos as $silo) : ?>
                        <?php $porcentaje = (float) $silo['capacidad'] > 0 ? min(100, ((float) $silo['cantidadOcupada'] / (float) $silo['capacidad']) * 100) : 0; ?>
                        <tr data-silo-row data-id="<?= (int) $silo['idSilo'] ?>" data-codigo="<?= $text($silo['codigo']) ?>" data-nombre="<?= $text($silo['nombre']) ?>" data-capacidad="<?= $text($silo['capacidad']) ?>" data-activo="<?= (int) $silo['activo'] ?>">
                            <td data-label="Código"><strong><?= $text($silo['codigo']) ?></strong></td>
                            <td data-label="Nombre"><?= $text($silo['nombre']) ?></td>
                            <td data-label="Producto"><?= $text($silo['productoActual'] ?: 'Vacío') ?></td>
                            <td data-label="Capacidad"><?= number_format((float) $silo['capacidad'], 3) ?> kg</td>
                            <td data-label="Ocupado">
                                <?= number_format((float) $silo['cantidadOcupada'], 3) ?> kg
                                <span class="silo-level"><i style="width: <?= number_format($porcentaje, 2, '.', '') ?>%"></i></span>
                            </td>
                            <td data-label="Disponible"><strong><?= number_format((float) $silo['capacidadDisponible'], 3) ?> kg</strong></td>
                            <td data-label="Estado"><span class="stock-pill <?= (int) $silo['activo'] === 1 ? '' : 'stock-pill--risk' ?>"><?= (int) $silo['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            <td class="table-actions" data-label="Acciones">
                                <button type="button" title="Editar" data-edit-silo>&#9998;</button>
                                <button type="button" title="Eliminar" data-delete-silo>&#128465;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <section class="silo-pending-section">
        <div>
            <p class="eyebrow">Regularización</p>
            <h2>Entradas de Sector3 pendientes de ubicar</h2>
        </div>
        <?php if (!$entradasPendientes) : ?>
            <div class="message message--success">Todas las entradas de Sector3 están distribuidas.</div>
        <?php else : ?>
            <div class="silo-pending-list">
                <?php foreach ($entradasPendientes as $entrada) : ?>
                    <article class="silo-pending-item">
                        <div><strong><?= $text($entrada['producto']) ?></strong><span>Lote <?= $text($entrada['NumLote']) ?> · Entrada #<?= (int) $entrada['idInventarioEntrante'] ?></span></div>
                        <div><span>Pendiente</span><strong><?= number_format((float) $entrada['cantidadPendiente'], 3) ?> kg</strong></div>
                        <button class="button-link button-link--submit" type="button" data-assign-entry data-entry-id="<?= (int) $entrada['idInventarioEntrante'] ?>" data-product-id="<?= (int) $entrada['idProducto'] ?>" data-product="<?= $text($entrada['producto']) ?>" data-lot="<?= $text($entrada['NumLote']) ?>" data-quantity="<?= $text($entrada['cantidadPendiente']) ?>">Distribuir</button>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="form-actions"><a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menú</a></div>
</section>

<div class="correction-modal" data-silo-modal hidden role="dialog" aria-modal="true" aria-labelledby="silo-modal-title">
    <form class="correction-modal-card admin-modal-card" method="post" action="<?= APP_URL ?>/admin/guardarSilo" data-silo-form>
        <?= Auth::csrfField() ?><input type="hidden" name="idSilo">
        <header><div><h2 id="silo-modal-title" data-silo-modal-title>Nuevo silo</h2><p>Capacidad expresada en kilogramos.</p></div><button type="button" class="modal-close" data-modal-close aria-label="Cerrar">×</button></header>
        <div class="correction-modal-grid">
            <label>Código<input name="codigo" type="text" maxlength="30" pattern="[A-Za-z0-9_-]+" required></label>
            <label>Nombre<input name="nombre" type="text" maxlength="100" required></label>
            <label>Capacidad (kg)<input name="capacidad" type="number" min="0.001" step="0.001" required></label>
            <label class="admin-check"><input name="activo" type="checkbox" value="1" checked> Silo activo</label>
        </div>
        <footer class="modal-actions"><button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button><button type="submit" class="button-link button-link--submit">Guardar</button></footer>
    </form>
</div>

<div class="correction-modal" data-assignment-modal hidden role="dialog" aria-modal="true" aria-labelledby="assignment-modal-title">
    <form class="correction-modal-card admin-modal-card" method="post" action="<?= APP_URL ?>/admin/asignarEntradaSilos" data-assignment-form>
        <?= Auth::csrfField() ?><input type="hidden" name="idInventarioEntrante">
        <header><div><h2 id="assignment-modal-title">Distribuir entrada</h2><p data-assignment-subtitle></p></div><button type="button" class="modal-close" data-modal-close aria-label="Cerrar">×</button></header>
        <div class="silo-assignment-summary"><span>Cantidad por distribuir</span><strong data-assignment-required>0.000 kg</strong></div>
        <div class="silo-assignment-rows" data-assignment-rows></div>
        <button type="button" class="button-link button-link--secondary" data-add-assignment>+ Agregar silo</button>
        <div class="message" data-assignment-message role="status"></div>
        <footer class="modal-actions"><button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button><button type="submit" class="button-link button-link--submit">Guardar distribución</button></footer>
    </form>
</div>

<div class="correction-modal correction-modal--confirm" data-silo-delete-modal hidden role="dialog" aria-modal="true" aria-labelledby="silo-delete-title">
    <form class="correction-modal-card correction-confirm-card" method="post" action="<?= APP_URL ?>/admin/eliminarSilo">
        <?= Auth::csrfField() ?><input type="hidden" name="idSilo">
        <div class="warning-icon" aria-hidden="true">!</div><h2 id="silo-delete-title">¿Eliminar silo?</h2><p data-delete-silo-message></p>
        <footer class="modal-actions"><button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button><button type="submit" class="button-link button-link--danger">Sí, eliminar</button></footer>
    </form>
</div>

<script src="<?= APP_URL ?>/public/js/admin-silos.js?v=<?= filemtime(__DIR__ . '/../../../public/js/admin-silos.js') ?>"></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>