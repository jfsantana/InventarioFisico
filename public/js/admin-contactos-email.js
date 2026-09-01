document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-admin-contacts]');
    if (!page) {
        return;
    }

    const contactModal = document.querySelector('[data-contact-modal]');
    const deleteModal = document.querySelector('[data-contact-delete-modal]');
    const contactForm = contactModal.querySelector('[data-contact-form]');

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

    function newContact() {
        contactForm.reset();
        contactForm.elements.id.value = '';
        contactModal.querySelector('[data-contact-modal-title]').textContent = 'Nuevo contacto';
        openModal(contactModal);
        contactForm.elements.nombre.focus();
    }

    function editContact(row) {
        contactForm.reset();
        contactModal.querySelector('[data-contact-modal-title]').textContent = 'Editar contacto';
        contactForm.elements.id.value = row.dataset.id;
        contactForm.elements.nombre.value = row.dataset.nombre;
        contactForm.elements.email.value = row.dataset.email;
        contactForm.elements.cargo.value = row.dataset.cargo;
        contactForm.elements.proceso.value = row.dataset.proceso;
        openModal(contactModal);
        contactForm.elements.nombre.focus();
    }

    page.querySelector('[data-open-contact-modal]').addEventListener('click', newContact);
    page.addEventListener('click', (event) => {
        const row = event.target.closest('[data-contact-row]');
        if (!row) {
            return;
        }

        if (event.target.closest('[data-edit-contact]')) {
            editContact(row);
        }

        if (event.target.closest('[data-delete-contact]')) {
            deleteModal.querySelector('[name="id"]').value = row.dataset.id;
            deleteModal.querySelector('[data-delete-contact-message]').textContent = `Se eliminara a ${row.dataset.nombre} (${row.dataset.email}).`;
            openModal(deleteModal);
        }
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('.correction-modal')));
    });

    [contactModal, deleteModal].forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.correction-modal.is-open').forEach(closeModal);
        }
    });
});