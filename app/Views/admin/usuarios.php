<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php $text = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>

<section class="panel report-panel admin-page" data-admin-users data-current-user="<?= (int) ($_SESSION['id_usuario'] ?? 0) ?>">
    <div class="admin-heading">
        <div>
            <p class="eyebrow">Administracion y seguridad</p>
            <h1>Gestión de accesos</h1>
            <p class="intro">Usuarios, estado de cuenta, rol asignado y acciones de seguridad.</p>
        </div>
        <button class="button-link button-link--submit" type="button" data-open-user-modal>+ Nuevo usuario</button>
    </div>

    <?php if (!empty($message)) : ?>
        <div class="message message--success" role="status"><?= $text($message) ?></div>
    <?php endif; ?>

    <form class="admin-filters" method="get" action="<?= APP_URL ?>/admin/usuarios">
        <input name="q" type="search" value="<?= $text($filters['q'] ?? '') ?>" placeholder="Buscar por nombre o usuario">
        <select name="id_rol">
            <option value="">Todos los roles</option>
            <?php foreach ($roles as $role) : ?>
                <option value="<?= (int) $role['id_rol'] ?>" <?= (string) ($filters['id_rol'] ?? '') === (string) $role['id_rol'] ? 'selected' : '' ?>><?= $text($role['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="estado">
            <option value="">Todos los estados</option>
            <option value="1" <?= (string) ($filters['estado'] ?? '') === '1' ? 'selected' : '' ?>>Activo</option>
            <option value="0" <?= (string) ($filters['estado'] ?? '') === '0' ? 'selected' : '' ?>>Inactivo</option>
        </select>
        <button class="button-link button-link--submit" type="submit">Filtrar</button>
    </form>

    <div class="admin-table-wrap">
        <table class="admin-table" data-admin-table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre completo</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Último acceso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $index => $usuario) : ?>
                    <?php $isCurrentUser = (int) $usuario['id_usuario'] === (int) ($_SESSION['id_usuario'] ?? 0); ?>
                    <tr data-user-row
                        data-id="<?= (int) $usuario['id_usuario'] ?>"
                        data-nombre="<?= $text($usuario['nombre_completo']) ?>"
                        data-username="<?= $text($usuario['username']) ?>"
                        data-rol="<?= (int) $usuario['id_rol'] ?>"
                        data-activo="<?= (int) $usuario['activo'] ?>">
                        <td><?= $index + 1 ?></td>
                        <td><?= $text($usuario['nombre_completo']) ?></td>
                        <td><?= $text($usuario['username']) ?></td>
                        <td><?= $text($usuario['rol_nombre']) ?></td>
                        <td><span class="status-pill <?= (int) $usuario['activo'] === 1 ? 'is-active' : 'is-inactive' ?>"><?= (int) $usuario['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                        <td><?= $usuario['ultimo_acceso'] ? $text($usuario['ultimo_acceso']) : 'Sin acceso' ?></td>
                        <td class="table-actions">
                            <button type="button" title="Editar" data-edit-user <?= $isCurrentUser ? 'disabled' : '' ?>>✏️</button>
                            <button type="button" title="Cambiar clave" data-password-user <?= $isCurrentUser ? 'disabled' : '' ?>>🔒</button>
                            <form method="post" action="<?= APP_URL ?>/admin/toggleUsuario" onsubmit="return confirm('¿Deseas cambiar el estado de este usuario?');">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="id_usuario" value="<?= (int) $usuario['id_usuario'] ?>">
                                <button type="submit" title="Activar/Inactivar" <?= $isCurrentUser ? 'disabled' : '' ?>><?= (int) $usuario['activo'] === 1 ? 'Inactivar' : 'Activar' ?></button>
                            </form>
                            <button type="button" title="Eliminar" data-delete-user <?= $isCurrentUser ? 'disabled' : '' ?>>Eliminar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <footer class="correction-pagination admin-pagination" aria-label="Paginacion de usuarios">
        <div class="pagination-buttons" data-admin-pagination></div>
        <span data-admin-pagination-info>Pagina 1 de 1 | Total: 0 usuarios</span>
    </footer>
</section>

<div class="correction-modal" data-user-modal hidden role="dialog" aria-modal="true" aria-labelledby="user-modal-title">
    <form class="correction-modal-card admin-modal-card" method="post" action="<?= APP_URL ?>/admin/guardarUsuario" data-user-form novalidate>
        <?= Auth::csrfField() ?>
        <header>
            <div>
                <h2 id="user-modal-title" data-user-modal-title>Nuevo usuario</h2>
                <p>Datos de acceso y rol del usuario.</p>
            </div>
            <button type="button" class="modal-close" data-modal-close aria-label="Cerrar modal">×</button>
        </header>
        <input type="hidden" name="id_usuario">
        <div class="correction-modal-grid">
            <label>Nombre completo<input name="nombre_completo" type="text" required></label>
            <label>Usuario<input name="username" type="text" pattern="[A-Za-z0-9_]{3,60}" required></label>
            <label>Rol<select name="id_rol" required><?php foreach ($roles as $role) : ?><option value="<?= (int) $role['id_rol'] ?>"><?= $text($role['nombre']) ?></option><?php endforeach; ?></select></label>
            <label class="switch-line"><input name="activo" type="checkbox" value="1" checked><span>Activo</span></label>
            <label data-create-password>Nueva contraseña<input name="password" type="password" minlength="6" autocomplete="new-password"></label>
            <label data-create-password>Confirmar contraseña<input name="password_confirm" type="password" minlength="6" autocomplete="new-password"></label>
                <small data-create-password data-create-password-help>Minimo 6 caracteres.</small>
        </div>
        <footer class="modal-actions"><button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button><button type="submit" class="button-link button-link--submit">Guardar</button></footer>
    </form>
</div>

<div class="correction-modal" data-password-modal hidden role="dialog" aria-modal="true" aria-labelledby="password-modal-title">
    <form class="correction-modal-card admin-modal-card" method="post" action="<?= APP_URL ?>/admin/cambiarClave" data-password-form novalidate>
        <?= Auth::csrfField() ?>
        <header><div><h2 id="password-modal-title">Cambiar contraseña</h2><p data-password-target></p></div><button type="button" class="modal-close" data-modal-close aria-label="Cerrar modal">×</button></header>
        <input type="hidden" name="id_usuario">
        <label>Nueva contraseña<input name="password" type="password" required minlength="6" autocomplete="new-password"></label>
        <small data-password-help>Minimo 6 caracteres.</small>
        <label>Confirmar nueva contraseña<input name="password_confirm" type="password" required minlength="6" autocomplete="new-password"></label>
        <footer class="modal-actions"><button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button><button type="submit" class="button-link button-link--submit">Guardar</button></footer>
    </form>
</div>

<div class="correction-modal correction-modal--confirm" data-user-delete-modal hidden role="dialog" aria-modal="true" aria-labelledby="user-delete-title">
    <form class="correction-modal-card correction-confirm-card" method="post" action="<?= APP_URL ?>/admin/eliminarUsuario">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="id_usuario">
        <div class="warning-icon" aria-hidden="true">⚠️</div>
        <h2 id="user-delete-title">¿Eliminar usuario?</h2>
        <p data-delete-user-message></p>
        <footer class="modal-actions"><button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button><button type="submit" class="button-link button-link--danger">Sí, eliminar</button></footer>
    </form>
</div>

<script src="<?= APP_URL ?>/public/js/admin-usuarios.js"></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>