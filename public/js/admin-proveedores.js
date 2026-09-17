document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-admin-providers]');
    if (!page) {
        return;
    }

    const modal = document.querySelector('[data-provider-modal]');
    const deleteModal = document.querySelector('[data-provider-delete-modal]');
    const form = modal.querySelector('[data-provider-form]');

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

    function newProvider() {
        form.reset();
        form.elements.originalCardCode.value = '';
        form.elements.CardCode.readOnly = false;
        modal.querySelector('[data-provider-modal-title]').textContent = 'Nuevo registro';
        openModal(modal);
        form.elements.CardCode.focus();
    }

    function editProvider(row) {
        form.reset();
        form.elements.originalCardCode.value = row.dataset.cardCode;
        form.elements.CardCode.value = row.dataset.cardCode;
        form.elements.CardCode.readOnly = true;
        form.elements.CardName.value = row.dataset.cardName;
        form.elements.GroupCode.value = row.dataset.groupCode;
        form.elements.MailAddres.value = row.dataset.mailAddress;
        form.elements.MailZipCod.value = row.dataset.mailZipCode;
        form.elements.CntctPrsn.value = row.dataset.contactPerson;
        form.elements.Notes.value = row.dataset.notes;
        form.elements.Balance.value = row.dataset.balance;
        form.elements.LicTradNum.value = row.dataset.taxId;
        form.elements.Country.value = row.dataset.country;
        form.elements.MailCity.value = row.dataset.city;
        form.elements.MailCounty.value = row.dataset.county;
        form.elements.MailCountr.value = row.dataset.mailCountry;
        form.elements.E_Mail.value = row.dataset.email;
        modal.querySelector('[data-provider-modal-title]').textContent = 'Editar registro';
        openModal(modal);
        form.elements.CardName.focus();
    }

    page.querySelector('[data-open-provider-modal]').addEventListener('click', newProvider);
    page.addEventListener('click', (event) => {
        const row = event.target.closest('[data-provider-row]');
        if (!row) {
            return;
        }

        if (event.target.closest('[data-edit-provider]')) {
            editProvider(row);
        }

        if (event.target.closest('[data-delete-provider]')) {
            deleteModal.querySelector('[name="CardCode"]').value = row.dataset.cardCode;
            deleteModal.querySelector('[data-delete-provider-message]').textContent = `Se eliminará ${row.dataset.cardCode} - ${row.dataset.cardName}.`;
            openModal(deleteModal);
        }
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('.correction-modal')));
    });

    [modal, deleteModal].forEach((target) => {
        target.addEventListener('click', (event) => {
            if (event.target === target) {
                closeModal(target);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.correction-modal.is-open').forEach(closeModal);
        }
    });
});