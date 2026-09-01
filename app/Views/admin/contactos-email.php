<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$text = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$contactos = $contactos ?? [];
$procesos = $procesos ?? ['Entrada', 'PreDespacho', 'Salida', 'En_Camino'];
$filters = $filters ?? [];
$messageType = $messageType ?? 'success';
?>

<section class="panel report-panel admin-page" data-admin-contacts>
    <div class="admin-heading">
        <div>
            <p class="eyebrow">Administracion y seguridad</p>
            <h1>Contactos de notificacion</h1>
            <p class="intro">Personas que reciben correos automaticos segun el proceso asignado.</p>
        </div>
        <button class="button-link button-link--submit" type="button" data-open-contact-modal>+ Nuevo contacto</button>
    </div>

    <?php if (!empty($message)) : ?>
        <div class="message message--<?= $messageType === 'error' ? 'error' : 'success' ?>" role="status"><?= $text($message) ?></div>
    <?php endif; ?>

    <form class="admin-filters" method="get" action="<?= APP_URL ?>/admin/contactosEmail">
        <input name="q" type="search" value="<?= $text($filters['q'] ?? '') ?>" placeholder="Buscar por nombre, correo, cargo o proceso">
        <button class="button-link button-link--submit" type="submit">Buscar</button>
        <?php if (!empty($filters['q'])) : ?>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/admin/contactosEmail">Limpiar</a>
        <?php endif; ?>
    </form>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th>#</th><th>Nombre</th><th>Correo</th><th>Cargo</th><th>Proceso</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php if (empty($contactos)) : ?>
                    <tr><td colspan="6">No hay contactos que coincidan con la busqueda.</td></tr>
                <?php else : ?>
                    <?php foreach ($contactos as $index => $contacto) : ?>
                        <tr data-contact-row
                            data-id="<?= (int) $contacto['id'] ?>"
                            data-nombre="<?= $text($contacto['nombre']) ?>"
                            data-email="<?= $text($contacto['email']) ?>"
                            data-cargo="<?= $text($contacto['cargo'] ?? '') ?>"
                            data-proceso="<?= $text($contacto['proceso']) ?>">
                            <td><?= $index + 1 ?></td>
                            <td><strong><?= $text($contacto['nombre']) ?></strong></td>
                            <td><a href="mailto:<?= $text($contacto['email']) ?>"><?= $text($contacto['email']) ?></a></td>
                            <td><?= $text($contacto['cargo'] ?: 'Sin cargo') ?></td>
                            <td><span class="status-pill is-active"><?= $text($contacto['proceso']) ?></span></td>
                            <td class="table-actions">
                                <button type="button" title="Editar contacto" data-edit-contact>Editar</button>
                                <button type="button" title="Eliminar contacto" data-delete-contact>Eliminar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menu</a>
    </div>
</section>

<div class="correction-modal" data-contact-modal hidden role="dialog" aria-modal="true" aria-labelledby="contact-modal-title">
    <form class="correction-modal-card admin-modal-card" method="post" action="<?= APP_URL ?>/admin/guardarContactoEmail" data-contact-form>
        <?= Auth::csrfField() ?>
        <header>
            <div><h2 id="contact-modal-title" data-contact-modal-title>Nuevo contacto</h2><p>Datos del destinatario y proceso que debe notificarlo.</p></div>
            <button type="button" class="modal-close" data-modal-close aria-label="Cerrar modal">×</button>
        </header>
        <input type="hidden" name="id">
        <div class="correction-modal-grid">
            <label>Nombre<input name="nombre" type="text" maxlength="100" required></label>
            <label>Correo electronico<input name="email" type="email" maxlength="150" required></label>
            <label>Cargo<input name="cargo" type="text" maxlength="50"></label>
            <label>Proceso
                <select name="proceso" required>
                    <?php foreach ($procesos as $proceso) : ?>
                        <option value="<?= $text($proceso) ?>"><?= $text(str_replace('_', ' ', $proceso)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <footer class="modal-actions">
            <button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button>
            <button type="submit" class="button-link button-link--submit">Guardar contacto</button>
        </footer>
    </form>
</div>

<div class="correction-modal correction-modal--confirm" data-contact-delete-modal hidden role="dialog" aria-modal="true" aria-labelledby="contact-delete-title">
    <form class="correction-modal-card correction-confirm-card" method="post" action="<?= APP_URL ?>/admin/eliminarContactoEmail">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="id">
        <div class="warning-icon" aria-hidden="true">!</div>
        <h2 id="contact-delete-title">¿Eliminar contacto?</h2>
        <p data-delete-contact-message></p>
        <footer class="modal-actions">
            <button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button>
            <button type="submit" class="button-link button-link--danger">Si, eliminar</button>
        </footer>
    </form>
</div>

<script src="<?= APP_URL ?>/public/js/admin-contactos-email.js?v=<?= filemtime(__DIR__ . '/../../../public/js/admin-contactos-email.js') ?>"></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>