document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-cotizacion-page]');
    if (!page) {
        return;
    }

    const form = page.querySelector('[data-cotizacion-form]');
    const clientSelect = page.querySelector('[data-client-select]');
    const quotationFields = page.querySelector('[data-quotation-fields]');
    const issueDate = page.querySelector('[data-issue-date]');
    const validityDays = page.querySelector('[data-validity-days]');
    const expirationDate = page.querySelector('[data-expiration-date]');
    const rows = page.querySelector('[data-detail-rows]');
    const rowTemplate = document.querySelector('[data-detail-template]');
    const emptyProducts = page.querySelector('[data-detail-empty]');
    const quotationSubtotal = page.querySelector('[data-quotation-subtotal]');
    const quotationTax = page.querySelector('[data-quotation-tax]');
    const grandTotal = page.querySelector('[data-grand-total]');
    const pageMessage = page.querySelector('[data-cotizacion-message]');
    const saveButton = page.querySelector('[data-save-quotation]');
    const clientModal = document.querySelector('[data-client-modal]');
    const clientForm = clientModal.querySelector('[data-client-form]');
    const clientMessage = clientModal.querySelector('[data-client-message]');
    const productModal = document.querySelector('[data-product-modal]');
    const productForm = productModal.querySelector('[data-product-form]');
    const productInput = productModal.querySelector('[data-product-input]');
    const presentationInput = productModal.querySelector('[data-presentation-input]');
    const quantityInput = productModal.querySelector('[data-quantity-input]');
    const priceInput = productModal.querySelector('[data-price-input]');
    const productSubtotal = productModal.querySelector('[data-product-subtotal]');
    const productMessage = productModal.querySelector('[data-product-message]');
    const productModalTitle = productModal.querySelector('[data-product-modal-title]');
    const productSaveButton = productModal.querySelector('[data-product-save]');
    const generationModal = document.querySelector('[data-generation-modal]');
    const generationTitle = generationModal.querySelector('[data-generation-title]');
    const generationMessage = generationModal.querySelector('[data-generation-message]');
    const generationButtons = Array.from(generationModal.querySelectorAll('[data-generation-mode]'));
    const emailModal = document.querySelector('[data-email-modal]');
    const emailForm = emailModal.querySelector('[data-email-form]');
    const emailMessage = emailModal.querySelector('[data-email-message]');
    const emailClientName = emailModal.querySelector('[data-email-client-name]');
    const csrfToken = page.dataset.csrfToken;
    const detalles = [];
    let editingIndex = null;

    document.body.appendChild(productModal);
    document.body.appendChild(clientModal);
    document.body.appendChild(generationModal);
    document.body.appendChild(emailModal);

    function formatAmount(value) {
        return Number(value || 0).toLocaleString('es-VE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function parseDecimal(value) {
        const input = String(value ?? '').trim().replace(/[\s\u00a0]/g, '');
        if (!input || !/^\d+(?:[.,]\d+)*(?:[.,]\d+)?$/.test(input)) {
            return Number.NaN;
        }

        const lastComma = input.lastIndexOf(',');
        const lastDot = input.lastIndexOf('.');
        const decimalIndex = Math.max(lastComma, lastDot);
        const normalized = decimalIndex === -1
            ? input
            : input.slice(0, decimalIndex).replace(/[.,]/g, '') + '.' + input.slice(decimalIndex + 1);
        const number = Number(normalized);

        return Number.isFinite(number) ? number : Number.NaN;
    }

    function showMessage(element, message, type) {
        element.textContent = message;
        element.className = `message message--${type}`;
        element.hidden = false;
    }

    function hideMessage(element) {
        element.hidden = true;
        element.textContent = '';
    }

    function calculateTotals() {
        const subtotal = detalles.reduce(
            (sum, detalle) => Math.round((sum + detalle.subtotal + Number.EPSILON) * 100) / 100,
            0
        );
        const tax = Math.round((subtotal * 0.16 + Number.EPSILON) * 100) / 100;
        const total = Math.round((subtotal + tax + Number.EPSILON) * 100) / 100;

        quotationSubtotal.dataset.value = String(subtotal);
        quotationSubtotal.textContent = formatAmount(subtotal);
        quotationTax.textContent = formatAmount(tax);
        grandTotal.dataset.value = String(total);
        grandTotal.textContent = formatAmount(total);
    }

    function optionText(select) {
        return select.options[select.selectedIndex]?.textContent.trim() || '';
    }

    function renderDetails() {
        rows.innerHTML = '';
        emptyProducts.hidden = detalles.length > 0;

        detalles.forEach((detalle, index) => {
            const item = rowTemplate.content.firstElementChild.cloneNode(true);
            item.dataset.index = String(index);
            item.querySelector('[data-detail-product-name]').textContent = detalle.producto;
            item.querySelector('[data-detail-presentation-name]').textContent = detalle.presentacion;
            item.querySelector('[data-detail-quantity]').textContent = formatAmount(detalle.cantidad);
            item.querySelector('[data-detail-price]').textContent = formatAmount(detalle.precioUnitario);
            item.querySelector('[data-detail-subtotal]').textContent = formatAmount(detalle.subtotal);
            rows.appendChild(item);
        });

        calculateTotals();
    }

    function updateExpirationDate() {
        const parts = issueDate.dataset.value.split('-').map(Number);
        const days = Number.parseInt(validityDays.value, 10);
        if (parts.length !== 3 || parts.some(Number.isNaN) || !Number.isInteger(days) || days < 1 || days > 999) {
            expirationDate.dateTime = '';
            expirationDate.textContent = '';
            return;
        }

        const expiration = new Date(parts[0], parts[1] - 1, parts[2]);
        expiration.setDate(expiration.getDate() + days);
        const year = expiration.getFullYear();
        const month = String(expiration.getMonth() + 1).padStart(2, '0');
        const day = String(expiration.getDate()).padStart(2, '0');
        expirationDate.dateTime = `${year}-${month}-${day}`;
        expirationDate.textContent = `${day}/${month}/${year}`;
    }

    function updateClientGate() {
        const hasClient = Boolean(clientSelect.value);
        quotationFields.disabled = !hasClient;
    }

    function updateProductSubtotal() {
        const quantity = parseDecimal(quantityInput.value) || 0;
        const price = parseDecimal(priceInput.value) || 0;
        productSubtotal.textContent = formatAmount(Math.round((quantity * price + Number.EPSILON) * 100) / 100);
    }

    function openProductModal(index = null) {
        editingIndex = index;
        hideMessage(productMessage);
        productForm.reset();

        if (index === null) {
            productModalTitle.textContent = 'Agregar producto';
            productSaveButton.textContent = 'Agregar producto';
        } else {
            const detalle = detalles[index];
            productModalTitle.textContent = 'Editar producto';
            productSaveButton.textContent = 'Guardar cambios';
            productInput.value = String(detalle.idProducto);
            presentationInput.value = String(detalle.idPresentacion);
            quantityInput.value = String(detalle.cantidad);
            priceInput.value = String(detalle.precioUnitario);
        }

        productInput.dispatchEvent(new Event('change', { bubbles: true }));
        updateProductSubtotal();
        productModal.hidden = false;
        productModal.classList.add('is-open');
        document.body.classList.add('modal-is-open');
        productModalTitle.focus();
    }

    function closeProductModal() {
        productModal.classList.remove('is-open');
        productModal.hidden = true;
        document.body.classList.remove('modal-is-open');
        editingIndex = null;
    }

    function openClientModal() {
        hideMessage(clientMessage);
        clientForm.reset();
        clientModal.hidden = false;
        clientModal.classList.add('is-open');
        document.body.classList.add('modal-is-open');
        clientForm.elements.rif.focus();
    }

    function closeClientModal() {
        clientModal.classList.remove('is-open');
        clientModal.hidden = true;
        document.body.classList.remove('modal-is-open');
    }

    function openGenerationModal() {
        hideMessage(generationMessage);
        const clientHasEmail = !selectedClientNeedsEmail();
        generationButtons.forEach((button) => {
            button.hidden = !clientHasEmail && button.dataset.generationMode !== 'pdf';
        });
        generationModal.hidden = false;
        generationModal.classList.add('is-open');
        document.body.classList.add('modal-is-open');
        generationTitle.focus();
    }

    function closeGenerationModal() {
        generationModal.classList.remove('is-open');
        generationModal.hidden = true;
        document.body.classList.remove('modal-is-open');
    }

    async function downloadPdf(url, filename) {
        const response = await fetch(url, { credentials: 'same-origin' });
        const contentType = response.headers.get('Content-Type') || '';
        if (!response.ok || !contentType.toLowerCase().includes('application/pdf')) {
            throw new Error('La cotización se guardó, pero el servidor no pudo generar el PDF.');
        }

        const blobUrl = URL.createObjectURL(await response.blob());
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename || 'cotizacion.pdf';
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
    }

    function selectedClientNeedsEmail() {
        const option = clientSelect.options[clientSelect.selectedIndex];
        return Boolean(option?.value) && !option.dataset.email?.trim();
    }

    function openEmailModal() {
        const option = clientSelect.options[clientSelect.selectedIndex];
        if (!option?.value) {
            return;
        }

        hideMessage(emailMessage);
        emailForm.reset();
        emailClientName.textContent = option.textContent.trim();
        emailModal.hidden = false;
        emailModal.classList.add('is-open');
        document.body.classList.add('modal-is-open');
        emailForm.elements.email.focus();
    }

    function closeEmailModal() {
        emailModal.classList.remove('is-open');
        emailModal.hidden = true;
        document.body.classList.remove('modal-is-open');
    }

    async function sendJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({ error: 'El servidor devolvió una respuesta inválida.' }));

        if (!response.ok || !data.success) {
            throw new Error(data.error || 'No se pudo completar la solicitud.');
        }

        return data;
    }

    page.querySelector('[data-add-detail]').addEventListener('click', () => openProductModal());
    page.querySelector('[data-open-client-modal]').addEventListener('click', openClientModal);

    clientModal.querySelectorAll('[data-client-close]').forEach((button) => {
        button.addEventListener('click', closeClientModal);
    });

    productModal.querySelectorAll('[data-product-close]').forEach((button) => {
        button.addEventListener('click', closeProductModal);
    });

    productForm.addEventListener('input', (event) => {
        if (event.target.matches('[data-quantity-input], [data-price-input]')) {
            event.target.setCustomValidity('');
            updateProductSubtotal();
        }
    });

    productForm.addEventListener('submit', (event) => {
        event.preventDefault();
        hideMessage(productMessage);

        if (!productForm.reportValidity()) {
            return;
        }

        const cantidadIngresada = parseDecimal(quantityInput.value);
        const precioIngresado = parseDecimal(priceInput.value);
        if (!Number.isFinite(cantidadIngresada) || cantidadIngresada <= 0) {
            quantityInput.setCustomValidity('Ingrese una cantidad válida usando punto o coma decimal.');
            quantityInput.reportValidity();
            return;
        }
        if (!Number.isFinite(precioIngresado) || precioIngresado <= 0) {
            priceInput.setCustomValidity('Ingrese un precio válido usando punto o coma decimal.');
            priceInput.reportValidity();
            return;
        }

        const cantidad = Math.round(cantidadIngresada * 100) / 100;
        const precioUnitario = Math.round(precioIngresado * 100) / 100;

        const detalle = {
            idProducto: Number(productInput.value),
            producto: optionText(productInput),
            idPresentacion: Number(presentationInput.value),
            presentacion: optionText(presentationInput),
            cantidad,
            precioUnitario,
            subtotal: Math.round((cantidad * precioUnitario + Number.EPSILON) * 100) / 100,
        };

        if (editingIndex === null) {
            detalles.push(detalle);
        } else {
            detalles[editingIndex] = detalle;
        }

        renderDetails();
        closeProductModal();
    });

    generationModal.querySelectorAll('[data-generation-close]').forEach((button) => {
        button.addEventListener('click', closeGenerationModal);
    });

    rows.addEventListener('click', (event) => {
        const item = event.target.closest('[data-index]');
        const editButton = event.target.closest('[data-edit-detail]');
        const removeButton = event.target.closest('[data-remove-detail]');
        if (!item || (!editButton && !removeButton)) {
            return;
        }

        const index = Number(item.dataset.index);
        if (editButton) {
            openProductModal(index);
            return;
        }

        detalles.splice(index, 1);
        renderDetails();
    });

    clientSelect.addEventListener('change', () => {
        updateClientGate();
        if (selectedClientNeedsEmail()) {
            openEmailModal();
        }
    });

    validityDays.addEventListener('input', updateExpirationDate);

    emailModal.querySelectorAll('[data-email-close]').forEach((button) => {
        button.addEventListener('click', closeEmailModal);
    });

    emailForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideMessage(emailMessage);

        if (!emailForm.reportValidity()) {
            return;
        }

        const submitButton = emailForm.querySelector('[type="submit"]');
        submitButton.disabled = true;

        try {
            const email = emailForm.elements.email.value.trim();
            await sendJson(page.dataset.emailEndpoint, {
                idCliente: clientSelect.value,
                email,
                emailFaltante: true,
            });
            clientSelect.options[clientSelect.selectedIndex].dataset.email = email;
            updateClientGate();
            closeEmailModal();
            showMessage(pageMessage, 'Email del cliente actualizado correctamente.', 'success');
        } catch (error) {
            showMessage(emailMessage, error.message, 'error');
        } finally {
            submitButton.disabled = false;
        }
    });

    clientForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideMessage(clientMessage);

        if (!clientForm.reportValidity()) {
            return;
        }

        const submitButton = clientForm.querySelector('[type="submit"]');
        submitButton.disabled = true;

        try {
            const response = await sendJson(page.dataset.clientCreateEndpoint, {
                rif: clientForm.elements.rif.value.trim(),
                nombre: clientForm.elements.nombre.value.trim(),
                email: clientForm.elements.email.value.trim(),
                telefono: clientForm.elements.telefono.value.trim(),
                direccion: clientForm.elements.direccion.value.trim(),
            });
            const cliente = response.cliente;
            const option = new Option(`${cliente.rif} - ${cliente.nombre}`, cliente.idCliente);
            option.dataset.email = cliente.email || '';
            clientSelect.add(option);
            clientSelect.value = String(cliente.idCliente);
            closeClientModal();
            clientSelect.dispatchEvent(new Event('change', { bubbles: true }));
            showMessage(pageMessage, 'Cliente creado y seleccionado correctamente.', 'success');
        } catch (error) {
            showMessage(clientMessage, error.message, 'error');
        } finally {
            submitButton.disabled = false;
        }
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        hideMessage(pageMessage);

        if (!form.reportValidity() || detalles.length === 0) {
            showMessage(pageMessage, 'Completa todos los datos de la cotización.', 'error');
            return;
        }

        openGenerationModal();
    });

    generationButtons.forEach((button) => button.addEventListener('click', async () => {
        hideMessage(generationMessage);

        calculateTotals();
        const subtotal = Number(quotationSubtotal.dataset.value || 0);
        const total = Number(grandTotal.dataset.value || 0);
        const modo = button.dataset.generationMode;

        saveButton.disabled = true;
        generationButtons.forEach((option) => { option.disabled = true; });

        try {
            const data = await sendJson(page.dataset.saveEndpoint, {
                modo,
                cabecera: {
                    idCliente: clientSelect.value,
                    diasVigencia: Number(form.elements.diasVigencia.value),
                    condicionPago: form.elements.condicionPago.value,
                    observacion: form.elements.observacion.value.trim(),
                    subtotal,
                    total,
                },
                detalles: detalles.map(({ idProducto, idPresentacion, cantidad, precioUnitario, subtotal }) => ({
                    idProducto,
                    idPresentacion,
                    cantidad,
                    precioUnitario,
                    subtotal,
                })),
            });
            closeGenerationModal();
            showMessage(pageMessage, `${data.mensaje} Número: ${data.idCotizacion}.`, data.correoEnviado === false ? 'error' : 'success');
            if (data.pdfUrl) {
                try {
                    await downloadPdf(data.pdfUrl, data.pdfFilename);
                } catch (error) {
                    showMessage(pageMessage, error.message, 'error');
                }
            }
            form.reset();
            form.querySelectorAll('select').forEach((select) => {
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
            updateExpirationDate();
            detalles.length = 0;
            renderDetails();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (error) {
            showMessage(generationMessage, error.message, 'error');
        } finally {
            saveButton.disabled = false;
            generationButtons.forEach((option) => { option.disabled = false; });
        }
    }));

    renderDetails();
    updateExpirationDate();
    updateClientGate();
});