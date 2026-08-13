document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-salida-sector]');
    if (!page) {
        return;
    }

    const apiUrl = page.dataset.apiUrl;
    const sectorSelect = page.querySelector('[data-sector-select]');
    const productsBody = page.querySelector('[data-products-body]');
    const form = page.querySelector('[data-delivery-form]');
    const messageBox = page.querySelector('[data-sector-message]');
    const errorBox = page.querySelector('[data-sector-error]');

    if (sectorSelect) {
        sectorSelect.addEventListener('change', () => {
            if (sectorSelect.value) {
                sectorSelect.form.submit();
            }
        });
    }

    if (!productsBody || !form) {
        return;
    }

    const emptyMessage = page.querySelector('[data-empty-delivery-message]');
    const productoInput = form.querySelector('[data-delivery-producto]');
    const loteInput = form.querySelector('[data-delivery-lote]');
    const disponibleInput = form.querySelector('[data-delivery-disponible]');
    const cantidadInput = form.querySelector('[data-delivery-cantidad]');
    const submitButton = form.querySelector('[data-delivery-submit]');
    let selectedRow = null;

    function showMessage(text, isError = false) {
        const target = isError ? errorBox : messageBox;
        const other = isError ? messageBox : errorBox;
        if (!target || !other) {
            return;
        }

        other.hidden = true;
        target.textContent = text;
        target.hidden = false;
    }

    function clearMessages() {
        if (messageBox) {
            messageBox.hidden = true;
        }
        if (errorBox) {
            errorBox.hidden = true;
        }
    }

    function apiPost(action, formData) {
        formData.set('accion', action);

        return fetch(apiUrl, {
            method: 'POST',
            body: formData
        }).then((response) => response.json().then((data) => {
            if (!response.ok || data.success === false) {
                throw new Error(data.mensaje || data.error || 'No se pudo completar la solicitud.');
            }
            return data;
        }));
    }

    function clearForm() {
        selectedRow = null;
        form.reset();
        form.elements.idItem.value = '';
        productoInput.value = '';
        loteInput.value = '';
        disponibleInput.value = '';
        cantidadInput.value = '';
        cantidadInput.disabled = true;
        cantidadInput.removeAttribute('max');
        submitButton.disabled = true;
        if (emptyMessage) {
            emptyMessage.hidden = false;
        }
        productsBody.querySelectorAll('[data-product-row]').forEach((row) => row.classList.remove('is-selected-row'));
    }

    productsBody.addEventListener('click', (event) => {
        const row = event.target.closest('[data-product-row]');
        if (!row) {
            return;
        }

        const disponible = Number(row.dataset.disponible || 0);
        if (disponible <= 0) {
            showMessage('Este producto ya no tiene cantidad pendiente por entregar.', true);
            return;
        }

        clearMessages();
        productsBody.querySelectorAll('[data-product-row]').forEach((item) => item.classList.remove('is-selected-row'));
        row.classList.add('is-selected-row');
        selectedRow = row;

        form.elements.idItem.value = row.dataset.itemId;
        productoInput.value = row.dataset.producto || '';
        loteInput.value = row.dataset.lote || '';
        disponibleInput.value = String(disponible);
        cantidadInput.disabled = false;
        cantidadInput.max = String(disponible);
        cantidadInput.value = '';
        submitButton.disabled = false;

        if (emptyMessage) {
            emptyMessage.hidden = true;
        }

        cantidadInput.focus();
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearMessages();

        if (!selectedRow || !form.elements.idItem.value) {
            showMessage('Seleccione un producto de la lista para registrar la entrega.', true);
            return;
        }

        const cantidad = Number(cantidadInput.value || 0);
        const disponible = Number(disponibleInput.value || 0);

        if (cantidad <= 0) {
            showMessage('La cantidad a entregar debe ser mayor que cero.', true);
            cantidadInput.focus();
            return;
        }

        if (cantidad > disponible) {
            showMessage('Cantidad supera el disponible.', true);
            cantidadInput.focus();
            return;
        }

        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Guardando...';

        apiPost('registrarSalida', new FormData(form))
            .then((response) => {
                showMessage('Entrega registrada correctamente');

                if (response.predespacho_embarcado || response.predespachoEmbarcado) {
                    window.alert(`Predespacho ${page.dataset.predespachoCodigo} completado y embarcado`);
                }

                clearForm();
                const url = new URL(window.location.href);
                window.location.href = url.toString();
            })
            .catch((error) => {
                showMessage(error.message, true);
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
    });
});
