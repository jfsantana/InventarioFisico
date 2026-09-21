<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$text = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$clientes = $clientes ?? [];
$filters = $filters ?? [];
$messageType = $messageType ?? 'success';
$status = (string) ($filters['status'] ?? 'all');
$pagination = $pagination ?? ['page' => 1, 'totalPages' => 1, 'total' => count($clientes)];
$page = (int) $pagination['page'];
$totalPages = (int) $pagination['totalPages'];

$pageUrl = static function (int $targetPage) use ($filters): string {
    $params = array_filter([
        'q' => $filters['q'] ?? '',
        'status' => $filters['status'] ?? 'all',
        'page' => $targetPage,
    ], static fn (mixed $value): bool => $value !== '' && $value !== null);

    return APP_URL . '/admin/clientesCotizaciones?' . http_build_query($params);
};
?>

<section class="panel report-panel admin-page clientes-cotizaciones-page" data-admin-clientes-cotizaciones>
    <div class="admin-heading">
        <div>
            <p class="eyebrow">Administración y seguridad</p>
            <h1>Clientes de Cotizaciones</h1>
            <p class="intro">Catálogo independiente de la información de SAP y Predespacho.</p>
        </div>
        <button class="button-link button-link--submit" type="button" data-open-client-modal>+ Nuevo cliente</button>
    </div>

    <?php if (!empty($message)) : ?>
        <div class="message message--<?= $messageType === 'error' ? 'error' : 'success' ?>" role="status"><?= $text($message) ?></div>
    <?php endif; ?>

    <form class="admin-filters clientes-cotizaciones-filters" method="get" action="<?= APP_URL ?>/admin/clientesCotizaciones">
        <input name="q" type="search" value="<?= $text($filters['q'] ?? '') ?>" placeholder="Buscar por RIF, nombre o correo">
        <select name="status" aria-label="Filtrar clientes por estado">
            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Disponibles</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Desactivados</option>
        </select>
        <button class="button-link button-link--submit" type="submit">Buscar</button>
        <?php if (!empty($filters['q']) || $status !== 'all') : ?><a class="button-link button-link--secondary" href="<?= APP_URL ?>/admin/clientesCotizaciones?status=all">Limpiar</a><?php endif; ?>
    </form>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Estado</th><th>RIF</th><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Cotizaciones</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php if (!$clientes) : ?>
                    <tr><td colspan="7">No hay clientes que coincidan con la búsqueda.</td></tr>
                <?php else : ?>
                    <?php foreach ($clientes as $cliente) : ?>
                        <tr data-client-row class="<?= (int) $cliente['activo'] === 1 ? 'is-client-active' : 'is-client-inactive' ?>"
                            data-id="<?= $text($cliente['idCliente']) ?>"
                            data-rif="<?= $text($cliente['rif']) ?>"
                            data-name="<?= $text($cliente['nombre']) ?>"
                            data-email="<?= $text($cliente['email'] ?? '') ?>"
                            data-phone="<?= $text($cliente['telefono'] ?? '') ?>"
                            data-address="<?= $text($cliente['direccion'] ?? '') ?>"
                            data-active="<?= (int) $cliente['activo'] === 1 ? 'true' : 'false' ?>">
                            <td data-label="Estado"><span class="client-status-badge client-status-badge--<?= (int) $cliente['activo'] === 1 ? 'active' : 'inactive' ?>"><?= (int) $cliente['activo'] === 1 ? 'Disponible' : 'Desactivado' ?></span></td>
                            <td data-label="RIF"><strong><?= $text($cliente['rif']) ?></strong><small class="client-code"><?= $text($cliente['idCliente']) ?></small></td>
                            <td data-label="Nombre"><?= $text($cliente['nombre']) ?></td>
                            <td data-label="Correo"><?= $text($cliente['email'] ?: 'Sin correo') ?></td>
                            <td data-label="Teléfono"><?= $text($cliente['telefono'] ?: 'Sin teléfono') ?></td>
                            <td data-label="Cotizaciones"><strong><?= (int) $cliente['cantidadCotizaciones'] ?></strong></td>
                            <td class="table-actions" data-label="Acciones">
                                <button type="button" title="Editar" data-edit-client>&#9998;</button>
                                <?php if ((int) $cliente['activo'] === 1) : ?>
                                    <a href="<?= APP_URL ?>/cotizacion/crear?idCliente=<?= rawurlencode((string) $cliente['idCliente']) ?>" title="Nueva cotización" aria-label="Nueva cotización">&#128221;</a>
                                    <a href="<?= APP_URL ?>/cotizacion?cliente=<?= rawurlencode((string) $cliente['idCliente']) ?>" title="Ver cotizaciones" aria-label="Ver cotizaciones">&#128196;</a>
                                <?php endif; ?>
                                <?php if ((int) $cliente['activo'] === 1) : ?>
                                    <button type="button" title="Desactivar" data-delete-client>&#128465;</button>
                                <?php else : ?>
                                    <button type="button" title="Activar" data-activate-client>&#10003;</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1) : ?>
        <nav class="admin-pagination" aria-label="Paginación de clientes">
            <?php if ($page > 1) : ?><a class="button-link button-link--secondary" href="<?= $pageUrl($page - 1) ?>">Anterior</a><?php endif; ?>
            <span>Página <?= $page ?> de <?= $totalPages ?> · <?= (int) $pagination['total'] ?> clientes</span>
            <?php if ($page < $totalPages) : ?><a class="button-link button-link--secondary" href="<?= $pageUrl($page + 1) ?>">Siguiente</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>

<div class="correction-modal" data-client-modal hidden role="dialog" aria-modal="true" aria-labelledby="client-modal-title">
    <form class="correction-modal-card admin-modal-card" method="post" action="<?= APP_URL ?>/admin/guardarClienteCotizacion" data-client-form>
        <?= Auth::csrfField() ?>
        <header>
            <div><h2 id="client-modal-title" data-client-modal-title>Nuevo cliente</h2><p>Información usada únicamente por Cotizaciones.</p></div>
            <button type="button" class="modal-close" data-modal-close aria-label="Cerrar modal">×</button>
        </header>
        <input type="hidden" name="idCliente">
        <div class="correction-modal-grid">
            <label>RIF<input name="rif" type="text" maxlength="20" required></label>
            <label>Nombre o razón social<input name="nombre" type="text" maxlength="150" required></label>
            <label>Correo electrónico<input name="email" type="email" maxlength="150"></label>
            <label>Teléfono<input name="telefono" type="text" maxlength="50"></label>
            <label class="correction-field--full">Dirección<textarea name="direccion" maxlength="2000" rows="3"></textarea></label>
            <label class="client-active-field correction-field--full"><input name="activo" type="checkbox" value="1" checked> Cliente disponible para nuevas cotizaciones</label>
        </div>
        <footer class="modal-actions"><button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button><button type="submit" class="button-link button-link--submit">Guardar</button></footer>
    </form>
</div>

<div class="correction-modal correction-modal--confirm" data-client-delete-modal hidden role="dialog" aria-modal="true" aria-labelledby="client-delete-title">
    <form class="correction-modal-card correction-confirm-card" method="post" action="<?= APP_URL ?>/admin/eliminarClienteCotizacion">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="idCliente">
        <div class="warning-icon" aria-hidden="true">!</div>
        <h2 id="client-delete-title">¿Desactivar cliente?</h2>
        <p data-delete-client-message></p>
        <footer class="modal-actions"><button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button><button type="submit" class="button-link button-link--danger">Sí, desactivar</button></footer>
    </form>
</div>

<form method="post" action="<?= APP_URL ?>/admin/activarClienteCotizacion" data-client-activate-form hidden>
    <?= Auth::csrfField() ?>
    <input type="hidden" name="idCliente">
</form>

<script src="<?= APP_URL ?>/public/js/admin-clientes-cotizaciones.js?v=<?= filemtime(__DIR__ . '/../../../public/js/admin-clientes-cotizaciones.js') ?>"></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>