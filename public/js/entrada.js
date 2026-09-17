const entradaForm = document.querySelector('[data-entrada-form]');

if (entradaForm) {
    const maxDocumentBytes = 10 * 1024 * 1024;
    const fileFields = [
        entradaForm.elements.ticketRomana,
        entradaForm.elements.facturaProveedor,
        entradaForm.elements.documentoSeniat,
    ];
    const requiredFields = [
        entradaForm.elements.idTipoCompra,
        entradaForm.elements.CardCode,
        entradaForm.elements.FabricanteCode,
        entradaForm.elements.PaisCode,
        entradaForm.elements.idProducto,
        entradaForm.elements.NumLote,
        entradaForm.elements.idPresentacion,
        entradaForm.elements.idUbicacion,
        entradaForm.elements.Sector,
        entradaForm.elements.CantidadEntrante,
        entradaForm.elements.fecha_factura,
        entradaForm.elements.peso_romana,
        entradaForm.elements.nro_factura,
    ];
    const submitButton = document.getElementById('guardarEntrada');
    const message = document.getElementById('entradaFormMessage');
    const providerModal = document.querySelector('[data-provider-quick-modal]');
    const providerForm = providerModal?.querySelector('[data-provider-quick-form]');
    const providerMessage = providerModal?.querySelector('[data-provider-quick-message]');
    const providerTitle = providerModal?.querySelector('[data-provider-quick-title]');
    let providerTarget = null;

    function isValidEntryQuantity(value) {
        return /^\d+(?:\.\d{1,3})?$/.test(value) && Number(value) > 0;
    }

    function isPositiveNumber(value) {
        return value !== '' && Number.isFinite(Number(value)) && Number(value) > 0;
    }

    function validateField(field) {
        const value = field?.value.trim() || '';
        if (!field || value === '') {
            return { valid: false, message: 'Pendiente' };
        }

        if (field.name === 'CantidadEntrante') {
            return {
                valid: isValidEntryQuantity(value),
                message: 'Use kilos, máximo 3 decimales',
            };
        }

        if (field.name === 'peso_romana') {
            return { valid: isPositiveNumber(value), message: 'Debe ser mayor que cero' };
        }

        if (field.name === 'nro_factura') {
            return {
                valid: /^[A-Za-z0-9 \-]+$/.test(value) && value.length <= 50,
                message: 'Use letras, números, guiones o espacios',
            };
        }

        return { valid: field.checkValidity(), message: 'Revise este campo' };
    }

    function isFormComplete() {
        return requiredFields.every((field) => validateField(field).valid);
    }

    function fieldLabel(field) {
        const label = field.closest('.form-field')?.querySelector('label')?.textContent.trim() || field.name;
        return label.replace(/^\d+\.\s*/, '').replace(/\s*\([^)]*\)\s*$/, '').trim();
    }

    function updateFieldFeedback(field) {
        const container = field.closest('.form-field');
        if (!container) {
            return validateField(field);
        }

        const result = validateField(field);
        let status = container.querySelector('[data-field-live-status]');
        if (!status) {
            status = document.createElement('small');
            status.dataset.fieldLiveStatus = '';
            status.className = 'field-live-status';
            container.appendChild(status);
        }

        const hasValue = field.value.trim() !== '';
        container.classList.toggle('is-field-complete', result.valid);
        container.classList.toggle('is-field-invalid', hasValue && !result.valid);
        container.classList.toggle('is-field-pending', !hasValue);
        field.setAttribute('aria-invalid', String(hasValue && !result.valid));
        status.textContent = result.valid ? 'Listo' : result.message;

        return result;
    }

    function focusField(field) {
        const container = field.closest('.form-field');
        const searchableInput = container?.querySelector('.searchable-select-input');
        (searchableInput || field).focus();
        container?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function updateProgress() {
        const progress = entradaForm.closest('.form-panel')?.querySelector('[data-entry-progress]');
        if (!progress) {
            return;
        }

        const pendingFields = requiredFields.filter((field) => !updateFieldFeedback(field).valid);
        const completed = requiredFields.length - pendingFields.length;
        const percentage = Math.round((completed / requiredFields.length) * 100);
        progress.querySelector('[data-entry-progress-bar]').style.width = `${percentage}%`;
        progress.querySelector('[data-entry-progress-count]').textContent = `${completed} de ${requiredFields.length}`;
        progress.querySelector('[data-entry-progress-title]').textContent = pendingFields.length ? 'Campos pendientes' : 'Entrada lista para guardar';
        progress.querySelector('[data-entry-progress-message]').textContent = pendingFields.length
            ? `Faltan ${pendingFields.length} campo${pendingFields.length === 1 ? '' : 's'}. Toca uno para completarlo.`
            : 'Todos los campos obligatorios están completos.';

        const list = progress.querySelector('[data-entry-progress-list]');
        list.innerHTML = '';
        pendingFields.forEach((field) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = fieldLabel(field);
            button.addEventListener('click', () => focusField(field));
            item.appendChild(button);
            list.appendChild(item);
        });

        progress.classList.toggle('is-complete', pendingFields.length === 0);
    }

    function documentsWithinLimit() {
        const totalBytes = fileFields.reduce((total, field) => total + (field.files[0]?.size || 0), 0);
        return totalBytes <= maxDocumentBytes;
    }

    function updateSubmitState() {
        const formComplete = isFormComplete() && documentsWithinLimit();

        submitButton.disabled = !formComplete;
        updateProgress();
        message.textContent = !documentsWithinLimit()
            ? 'Los tres documentos no pueden superar 10 MB en total.'
            : (formComplete ? 'Todos los campos obligatorios están completos.' : 'Revisa los campos pendientes indicados arriba.');
    }

    function openProviderModal(targetName) {
        if (!providerModal || !providerForm) {
            return;
        }

        providerTarget = entradaForm.elements[targetName];
        providerForm.reset();
        providerMessage.hidden = true;
        providerMessage.textContent = '';
        providerTitle.textContent = targetName === 'FabricanteCode' ? 'Nuevo fabricante' : 'Nuevo proveedor';
        providerModal.hidden = false;
        providerModal.classList.add('is-open');
        document.body.classList.add('modal-is-open');
        providerForm.elements.CardCode.focus();
    }

    function closeProviderModal() {
        if (!providerModal) {
            return;
        }

        providerModal.classList.remove('is-open');
        providerModal.hidden = true;
        document.body.classList.remove('modal-is-open');
        providerTarget = null;
    }

    function addProviderOption(select, provider) {
        let option = Array.from(select.options).find((item) => item.value === provider.CardCode);
        if (!option) {
            option = new Option(`${provider.CardCode} - ${provider.CardName}`, provider.CardCode);
            select.add(option);
        }

        return option;
    }

    [...requiredFields, ...fileFields].forEach((field) => {
        if (!field) {
            return;
        }

        field.addEventListener('input', updateSubmitState);
        field.addEventListener('change', updateSubmitState);
    });

    entradaForm.addEventListener('submit', (event) => {
        updateSubmitState();

        if (!isFormComplete() || !documentsWithinLimit()) {
            event.preventDefault();
        }
    });

    entradaForm.querySelectorAll('[data-open-provider-quick]').forEach((button) => {
        button.addEventListener('click', () => openProviderModal(button.dataset.targetSelect));
    });

    providerModal?.querySelectorAll('[data-provider-quick-close]').forEach((button) => {
        button.addEventListener('click', closeProviderModal);
    });

    providerForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        providerMessage.hidden = true;

        if (!providerForm.reportValidity()) {
            return;
        }

        const saveButton = providerForm.querySelector('[type="submit"]');
        saveButton.disabled = true;

        try {
            const payload = Object.fromEntries(new FormData(providerForm).entries());
            const response = await fetch(entradaForm.dataset.providerEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': entradaForm.dataset.csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json().catch(() => ({ error: 'Respuesta invalida del servidor.' }));
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'No se pudo crear el registro.');
            }

            [entradaForm.elements.CardCode, entradaForm.elements.FabricanteCode].forEach((select) => {
                addProviderOption(select, data.proveedor);
            });
            providerTarget.value = data.proveedor.CardCode;
            providerTarget.dispatchEvent(new Event('change', { bubbles: true }));
            closeProviderModal();
            updateSubmitState();
        } catch (error) {
            providerMessage.textContent = error.message;
            providerMessage.className = 'message message--error';
            providerMessage.hidden = false;
        } finally {
            saveButton.disabled = false;
        }
    });

    providerModal?.addEventListener('click', (event) => {
        if (event.target === providerModal) {
            closeProviderModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && providerModal?.classList.contains('is-open')) {
            closeProviderModal();
        }
    });

    updateSubmitState();
}