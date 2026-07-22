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
        userForm.elements.password.value = '';
        userForm.elements.password_confirm.value = '';
        openModal(userModal);
    }

    function scorePassword(password) {
        let score = 0;
        if (password.length >= 8) score += 1;
        if (/[A-Z]/.test(password)) score += 1;
        if (/[0-9]/.test(password)) score += 1;
        if (/[^A-Za-z0-9]/.test(password)) score += 1;
        return score;
    }

    function updateStrength() {
        const password = passwordForm.elements.password.value;
        const score = scorePassword(password);
        const bar = passwordModal.querySelector('[data-strength-bar]');
        const label = passwordModal.querySelector('[data-strength-label]');
        bar.className = '';
        bar.style.width = `${Math.max(8, score * 25)}%`;
        if (score <= 2) {
            bar.classList.add('is-weak');
            label.textContent = 'Fortaleza débil';
        } else if (score === 3) {
            bar.classList.add('is-medium');
            label.textContent = 'Fortaleza media';
        } else {
            bar.classList.add('is-strong');
            label.textContent = 'Fortaleza fuerte';
        }
    }

    function validatePasswordForm(event) {
        const password = passwordForm.elements.password.value;
        const confirm = passwordForm.elements.password_confirm.value;
        if (scorePassword(password) < 4 || password !== confirm) {
            event.preventDefault();
            passwordModal.querySelector('[data-strength-label]').textContent = 'La contraseña debe cumplir los requisitos y coincidir.';
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
            updateStrength();
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

    passwordForm.elements.password.addEventListener('input', updateStrength);
    passwordForm.addEventListener('submit', validatePasswordForm);

    userForm.addEventListener('submit', (event) => {
        const username = userForm.elements.username.value.trim();
        if (!/^[A-Za-z0-9_]{3,60}$/.test(username)) {
            event.preventDefault();
            userForm.elements.username.focus();
        }
    });

    renderPagination();
});