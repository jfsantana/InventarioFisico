document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-admin-clientes-cotizaciones]');
    if (!page) {
        return;
    }

    const modal = document.querySelector('[data-client-modal]');
    const deleteModal = document.querySelector('[data-client-delete-modal]');
    const activateForm = document.querySelector('[data-client-activate-form]');
    const form = modal.querySelector('[data-client-form]');

    function openModal(target) {
        target.hidden = false;
        document.body.classList.add('modal-is-open');
        requestAnimationFrame(() => target.classList.add('is-open'));
    }

    function closeModal(target) {
        target.classList.remove('is-open');
        document.body.classList.remove('modal-is-open');
        setTimeout(() => { target.hidden = true; }, 180);
    }

    function resetForm() {
        form.reset();
        form.elements.idCliente.value = '';
        form.elements.rif.readOnly = false;
        form.elements.activo.checked = true;
        modal.querySelector('[data-client-modal-title]').textContent = 'Nuevo cliente';
    }

    page.querySelector('[data-open-client-modal]').addEventListener('click', () => {
        resetForm();
        openModal(modal);
        form.elements.rif.focus();
    });

    page.addEventListener('click', (event) => {
        const row = event.target.closest('[data-client-row]');
        if (!row) {
            return;
        }

        if (event.target.closest('[data-edit-client]')) {
            resetForm();
            form.elements.idCliente.value = row.dataset.id;
            form.elements.rif.value = row.dataset.rif;
            form.elements.rif.readOnly = true;
            form.elements.nombre.value = row.dataset.name;
            form.elements.email.value = row.dataset.email;
            form.elements.telefono.value = row.dataset.phone;
            form.elements.direccion.value = row.dataset.address;
            form.elements.activo.checked = row.dataset.active === 'true';
            modal.querySelector('[data-client-modal-title]').textContent = 'Editar cliente';
            openModal(modal);
            form.elements.nombre.focus();
        }

        if (event.target.closest('[data-delete-client]')) {
            deleteModal.querySelector('[name="idCliente"]').value = row.dataset.id;
            deleteModal.querySelector('[data-delete-client-message]').textContent = `Se desactivará ${row.dataset.rif} - ${row.dataset.name}.`;
            openModal(deleteModal);
        }

        if (event.target.closest('[data-activate-client]')) {
            activateForm.elements.idCliente.value = row.dataset.id;
            activateForm.submit();
        }
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('.correction-modal')));
    });

    [modal, deleteModal].forEach((target) => target.addEventListener('click', (event) => {
        if (event.target === target) {
            closeModal(target);
        }
    }));
});