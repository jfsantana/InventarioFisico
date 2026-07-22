document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-admin-users]');
    if (!page) {
        return;
    }

    const currentUserId = page.dataset.currentUser;
    const userModal = document.querySelector('[data-user-modal]');
    const passwordModal = document.querySelector('[data-password-modal]');
    const deleteModal = document.querySelector('[data-user-delete-modal]');
    const userForm = userModal.querySelector('[data-user-form]');
    const passwordForm = passwordModal.querySelector('[data-password-form]');
    const rows = Array.from(page.querySelectorAll('[data-user-row]'));
    const pagination = page.querySelector('[data-admin-pagination]');
    const paginationInfo = page.querySelector('[data-admin-pagination-info]');
    const perPage = 10;
    let currentPage = 1;

    function openModal(modal) {
        modal.removeAttribute('hidden');
        document.body.classList.add('modal-is-open');
        requestAnimationFrame(() => modal.classList.add('is-open'));
    }

    function closeModal(modal) {
        modal.classList.remove('is-open');
        document.body.classList.remove('modal-is-open');
        setTimeout(() => modal.setAttribute('hidden', 'hidden'), 180);
    }

    function clearUserForm() {
        userForm.reset();
        userForm.elements.id_usuario.value = '';
        userModal.querySelector('[data-user-modal-title]').textContent = 'Nuevo usuario';
        userModal.querySelectorAll('[data-create-password]').forEach((field) => field.hidden = false);
        userForm.elements.password.required = true;
        userForm.elements.password_confirm.required = true;
        userModal.querySelector('[data-create-password-help]').textContent = 'Minimo 6 caracteres.';
    }

    function passwordIsValid(password, confirm) {
        return password.length > 5 && password === confirm;
    }

    function editUser(row) {
        clearUserForm();
        userModal.querySelector('[data-user-modal-title]').textContent = 'Editar usuario';
        userForm.elements.id_usuario.value = row.dataset.id;
        userForm.elements.nombre_completo.value = row.dataset.nombre;
        userForm.elements.username.value = row.dataset.username;
        userForm.elements.id_rol.value = row.dataset.rol;
        userForm.elements.activo.checked = row.dataset.activo === '1';
        userModal.querySelectorAll('[data-create-password]').forEach((field) => field.hidden = true);
        userForm.elements.password.required = false;
        userForm.elements.password_confirm.required = false;
        userModal.querySelector('[data-create-password-help]').textContent = 'Minimo 6 caracteres.';
        userForm.elements.password.value = '';
        userForm.elements.password_confirm.value = '';
        openModal(userModal);
    }

    function validatePasswordForm(event) {
        const password = passwordForm.elements.password.value;
        const confirm = passwordForm.elements.password_confirm.value;
        if (!passwordIsValid(password, confirm)) {
            event.preventDefault();
            passwordModal.querySelector('[data-password-help]').textContent = 'La contraseña debe tener al menos 6 caracteres y coincidir.';
        }
    }

    function renderPagination() {
        const totalPages = Math.max(1, Math.ceil(rows.length / perPage));
        currentPage = Math.min(currentPage, totalPages);
        const start = (currentPage - 1) * perPage;
        rows.forEach((row, index) => {
            row.hidden = index < start || index >= start + perPage;
        });
        pagination.innerHTML = '';
        const prev = document.createElement('button');
        prev.type = 'button';
        prev.className = 'pagination-button';
        prev.textContent = '<< Anterior';
        prev.disabled = currentPage <= 1;
        prev.addEventListener('click', () => { currentPage -= 1; renderPagination(); });
        pagination.appendChild(prev);
        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'pagination-button';
        next.textContent = 'Siguiente >>';
        next.disabled = currentPage >= totalPages;
        next.addEventListener('click', () => { currentPage += 1; renderPagination(); });
        pagination.appendChild(next);
        paginationInfo.textContent = `Pagina ${currentPage} de ${totalPages} | Total: ${rows.length} usuarios`;
    }

    page.querySelector('[data-open-user-modal]').addEventListener('click', () => {
        clearUserForm();
        openModal(userModal);
    });

    page.addEventListener('click', (event) => {
        const row = event.target.closest('[data-user-row]');
        if (!row) {
            return;
        }
        if (event.target.closest('[data-edit-user]')) {
            if (row.dataset.id === currentUserId) {
                alert('No puede modificar su propio usuario.');
                return;
            }
            editUser(row);
        }
        if (event.target.closest('[data-password-user]')) {
            passwordForm.reset();
            passwordForm.elements.id_usuario.value = row.dataset.id;
            passwordModal.querySelector('[data-password-target]').textContent = row.dataset.username;
            openModal(passwordModal);
        }
        if (event.target.closest('[data-delete-user]')) {
            if (row.dataset.id === currentUserId) {
                alert('No puede eliminar su propio usuario.');
                return;
            }
            deleteModal.querySelector('[name="id_usuario"]').value = row.dataset.id;
            deleteModal.querySelector('[data-delete-user-message]').textContent = `Se eliminara el usuario ${row.dataset.username}. Esta accion no se puede deshacer.`;
            openModal(deleteModal);
        }
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('.correction-modal')));
    });

    passwordForm.addEventListener('submit', validatePasswordForm);

    userForm.addEventListener('submit', (event) => {
        const username = userForm.elements.username.value.trim();
        if (!/^[A-Za-z0-9_]{3,60}$/.test(username)) {
            event.preventDefault();
            userForm.elements.username.focus();
            return;
        }

        if (!userForm.elements.id_usuario.value && !passwordIsValid(userForm.elements.password.value, userForm.elements.password_confirm.value)) {
            event.preventDefault();
            userModal.querySelector('[data-create-password-help]').textContent = 'La contraseña debe tener al menos 6 caracteres y coincidir.';
            userForm.elements.password.focus();
        }
    });

    renderPagination();
});