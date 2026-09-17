<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$text = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$proveedores = $proveedores ?? [];
$filters = $filters ?? [];
$messageType = $messageType ?? 'success';
?>

<section class="panel report-panel admin-page provider-admin-page" data-admin-providers>
    <div class="admin-heading">
        <div>
            <p class="eyebrow">Administración y seguridad</p>
            <h1>Proveedores y fabricantes</h1>
            <p class="intro">Catálogo compartido por los campos Proveedor y Fabricante de las entradas.</p>
        </div>
        <button class="button-link button-link--submit" type="button" data-open-provider-modal>+ Nuevo registro</button>
    </div>

    <?php if (!empty($message)) : ?>
        <div class="message message--<?= $messageType === 'error' ? 'error' : 'success' ?>" role="status"><?= $text($message) ?></div>
    <?php endif; ?>

    <form class="admin-filters" method="get" action="<?= APP_URL ?>/admin/proveedores">
        <input name="q" type="search" value="<?= $text($filters['q'] ?? '') ?>" placeholder="Buscar por código, nombre, RIF o correo">
        <button class="button-link button-link--submit" type="submit">Buscar</button>
        <?php if (!empty($filters['q'])) : ?>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/admin/proveedores">Limpiar</a>
        <?php endif; ?>
    </form>

    <div class="admin-table-wrap provider-table-wrap">
        <table class="admin-table provider-table">
            <thead>
                <tr><th>Código</th><th>Nombre</th><th>RIF</th><th>Contacto</th><th>Correo</th><th>Ubicación</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php if (!$proveedores) : ?>
                    <tr><td colspan="7">No hay registros que coincidan con la búsqueda.</td></tr>
                <?php else : ?>
                    <?php foreach ($proveedores as $proveedor) : ?>
                        <tr data-provider-row
                            data-card-code="<?= $text($proveedor['CardCode']) ?>"
                            data-card-name="<?= $text($proveedor['CardName'] ?? '') ?>"
                            data-group-code="<?= $text($proveedor['GroupCode'] ?? '') ?>"
                            data-mail-address="<?= $text($proveedor['MailAddres'] ?? '') ?>"
                            data-mail-zip-code="<?= $text($proveedor['MailZipCod'] ?? '') ?>"
                            data-contact-person="<?= $text($proveedor['CntctPrsn'] ?? '') ?>"
                            data-notes="<?= $text($proveedor['Notes'] ?? '') ?>"
                            data-balance="<?= $text($proveedor['Balance'] ?? '') ?>"
                            data-tax-id="<?= $text($proveedor['LicTradNum'] ?? '') ?>"
                            data-country="<?= $text($proveedor['Country'] ?? '') ?>"
                            data-city="<?= $text($proveedor['MailCity'] ?? '') ?>"
                            data-county="<?= $text($proveedor['MailCounty'] ?? '') ?>"
                            data-mail-country="<?= $text($proveedor['MailCountr'] ?? '') ?>"
                            data-email="<?= $text($proveedor['E_Mail'] ?? '') ?>">
                            <td data-label="Código"><strong><?= $text($proveedor['CardCode']) ?></strong></td>
                            <td data-label="Nombre"><?= $text($proveedor['CardName'] ?: 'Sin nombre') ?></td>
                            <td data-label="RIF"><?= $text($proveedor['LicTradNum'] ?: 'Sin RIF') ?></td>
                            <td data-label="Contacto"><?= $text($proveedor['CntctPrsn'] ?: 'Sin contacto') ?></td>
                            <td data-label="Correo"><?= $text($proveedor['E_Mail'] ?: 'Sin correo') ?></td>
                            <td data-label="Ubicación"><?= $text(implode(', ', array_filter([$proveedor['MailCity'] ?? '', $proveedor['Country'] ?? ''])) ?: 'Sin ubicación') ?></td>
                            <td class="table-actions" data-label="Acciones">
                                <button type="button" title="Editar" data-edit-provider>&#9998;</button>
                                <button type="button" title="Eliminar" data-delete-provider>&#128465;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menú</a>
    </div>
</section>

<div class="correction-modal" data-provider-modal hidden role="dialog" aria-modal="true" aria-labelledby="provider-modal-title">
    <form class="correction-modal-card admin-modal-card provider-admin-modal" method="post" action="<?= APP_URL ?>/admin/guardarProveedor" data-provider-form>
        <?= Auth::csrfField() ?>
        <header>
            <div><h2 id="provider-modal-title" data-provider-modal-title>Nuevo registro</h2><p>Datos del proveedor o fabricante.</p></div>
            <button type="button" class="modal-close" data-modal-close aria-label="Cerrar modal">×</button>
        </header>
        <input type="hidden" name="originalCardCode">
        <div class="correction-modal-grid provider-modal-grid">
            <label>Código<input name="CardCode" type="text" maxlength="15" required></label>
            <label>Nombre<input name="CardName" type="text" maxlength="100" required></label>
            <label>RIF<input name="LicTradNum" type="text" maxlength="32"></label>
            <label>Persona de contacto<input name="CntctPrsn" type="text" maxlength="90"></label>
            <label>Correo electrónico<input name="E_Mail" type="email" maxlength="100"></label>
            <label>Grupo<input name="GroupCode" type="number" step="1"></label>
            <label>País<input name="Country" type="text" maxlength="100"></label>
            <label>Ciudad<input name="MailCity" type="text" maxlength="100"></label>
            <label>Estado / Provincia<input name="MailCounty" type="text" maxlength="100"></label>
            <label>País de correspondencia<input name="MailCountr" type="text" maxlength="100"></label>
            <label>Código postal<input name="MailZipCod" type="text" maxlength="20"></label>
            <label>Balance<input name="Balance" type="number" step="0.000001"></label>
            <label class="provider-modal-wide">Dirección<textarea name="MailAddres" maxlength="254" rows="3"></textarea></label>
            <label class="provider-modal-wide">Notas<textarea name="Notes" rows="3"></textarea></label>
        </div>
        <footer class="modal-actions">
            <button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button>
            <button type="submit" class="button-link button-link--submit">Guardar</button>
        </footer>
    </form>
</div>

<div class="correction-modal correction-modal--confirm" data-provider-delete-modal hidden role="dialog" aria-modal="true" aria-labelledby="provider-delete-title">
    <form class="correction-modal-card correction-confirm-card" method="post" action="<?= APP_URL ?>/admin/eliminarProveedor">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="CardCode">
        <div class="warning-icon" aria-hidden="true">!</div>
        <h2 id="provider-delete-title">¿Eliminar registro?</h2>
        <p data-delete-provider-message></p>
        <footer class="modal-actions">
            <button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button>
            <button type="submit" class="button-link button-link--danger">Sí, eliminar</button>
        </footer>
    </form>
</div>

<script src="<?= APP_URL ?>/public/js/admin-proveedores.js?v=<?= filemtime(__DIR__ . '/../../../public/js/admin-proveedores.js') ?>"></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>