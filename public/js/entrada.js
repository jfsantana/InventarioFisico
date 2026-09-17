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
    const siloSection = entradaForm.querySelector('[data-silo-allocation]');
    const siloRows = entradaForm.querySelector('[data-silo-rows]');
    const initialSiloAssignments = JSON.parse(entradaForm.querySelector('[data-initial-silo-assignments]')?.textContent || '[]');
    let availableSilos = [];
    let initialSilosRestored = false;
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
        return requiredFields.every((field) => validateField(field).valid) && isSiloDistributionValid();
    }

    function isSector3() {
        return String(entradaForm.elements.Sector.value).replace(/\s+/g, '').toLowerCase() === 'sector3';
    }

    function siloQuantityFields() {
        return Array.from(siloRows?.querySelectorAll('[name="cantidadSilo[]"]') || []);
    }

    function isSiloDistributionValid() {
        if (!isSector3()) return true;
        const required = Number(entradaForm.elements.CantidadEntrante.value) || 0;
        const rows = Array.from(siloRows.querySelectorAll('.silo-assignment-row'));
        if (required <= 0 || rows.length === 0) return false;
        const ids = rows.map((row) => row.querySelector('[name="idSilo[]"]').value).filter(Boolean);
        const assigned = siloQuantityFields().reduce((sum, field) => sum + (Number(field.value) || 0), 0);
        return ids.length === rows.length && new Set(ids).size === ids.length && rows.every((row) => {
            const select = row.querySelector('[name="idSilo[]"]');
            const quantity = Number(row.querySelector('[name="cantidadSilo[]"]').value) || 0;
            const available = Number(select.selectedOptions[0]?.dataset.available || 0);
            return quantity > 0 && quantity <= available + 0.0005;
        }) && Math.abs(assigned - required) < 0.0005;
    }

    function updateSiloTotals() {
        if (!siloSection) return;
        const required = Number(entradaForm.elements.CantidadEntrante.value) || 0;
        const assigned = siloQuantityFields().reduce((sum, field) => sum + (Number(field.value) || 0), 0);
        const pending = required - assigned;
        siloSection.querySelector('[data-silo-required]').textContent = `${required.toFixed(3)} kg`;
        siloSection.querySelector('[data-silo-assigned]').textContent = `${assigned.toFixed(3)} kg`;
        const status = siloSection.querySelector('[data-silo-status]');
        status.textContent = Math.abs(pending) < 0.0005 && required > 0
            ? 'Distribución completa'
            : `${pending >= 0 ? 'Faltan' : 'Exceden'} ${Math.abs(pending).toFixed(3)} kg`;
        status.classList.toggle('is-complete', Math.abs(pending) < 0.0005 && required > 0);
    }

    function updateSiloOptionStates() {
        const selected = new Set(Array.from(siloRows.querySelectorAll('[name="idSilo[]"]')).map((field) => field.value).filter(Boolean));
        siloRows.querySelectorAll('[name="idSilo[]"]').forEach((select) => {
            Array.from(select.options).forEach((option) => {
                option.disabled = option.value !== '' && option.value !== select.value && selected.has(option.value);
            });
        });
    }

    function addSiloRow(values = {}) {
        const row = document.createElement('div');
        row.className = 'silo-assignment-row';
        const select = document.createElement('select');
        select.name = 'idSilo[]';
        select.required = true;
        select.appendChild(new Option('Seleccione un silo', ''));
        availableSilos.forEach((silo) => {
            const option = new Option(`${silo.codigo} - ${silo.nombre} (${Number(silo.capacidadDisponible).toFixed(3)} kg disponibles)`, silo.idSilo);
            option.dataset.available = silo.capacidadDisponible;
            select.appendChild(option);
        });
        select.value = String(values.idSilo || '');

        const quantity = document.createElement('input');
        quantity.name = 'cantidadSilo[]';
        quantity.type = 'number';
        quantity.min = '0.001';
        quantity.step = '0.001';
        quantity.inputMode = 'decimal';
        quantity.placeholder = 'Cantidad en kg';
        quantity.required = true;
        quantity.value = values.cantidad || '';

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.title = 'Quitar silo';
        remove.setAttribute('aria-label', 'Quitar silo');
        remove.innerHTML = '&times;';
        remove.addEventListener('click', () => {
            row.remove();
            updateSiloOptionStates();
            updateSubmitState();
        });
        [select, quantity].forEach((field) => field.addEventListener('input', () => {
            updateSiloOptionStates();
            updateSubmitState();
        }));
        row.append(select, quantity, remove);
        siloRows.appendChild(row);
        updateSiloOptionStates();
        updateSiloTotals();
    }

    async function updateSiloSection(resetRows = false) {
        if (!siloSection) return;
        const enabled = isSector3();
        siloSection.hidden = !enabled;
        siloRows.querySelectorAll('select,input').forEach((field) => { field.disabled = !enabled; });
        if (!enabled) {
            updateSubmitState();
            return;
        }

        const productId = entradaForm.elements.idProducto.value;
        if (!productId) {
            availableSilos = [];
            siloRows.replaceChildren();
            updateSiloTotals();
            updateSubmitState();
            return;
        }

        try {
            const response = await fetch(`${entradaForm.dataset.siloEndpoint}?idProducto=${encodeURIComponent(productId)}`);
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'No se pudieron consultar los silos.');
            availableSilos = data;
            if (resetRows) siloRows.replaceChildren();
            if (!initialSilosRestored && initialSiloAssignments.length) {
                initialSiloAssignments.forEach(addSiloRow);
                initialSilosRestored = true;
            } else if (!siloRows.children.length) {
                addSiloRow();
            } else {
                const current = Array.from(siloRows.querySelectorAll('.silo-assignment-row')).map((row) => ({
                    idSilo: row.querySelector('[name="idSilo[]"]').value,
                    cantidad: row.querySelector('[name="cantidadSilo[]"]').value,
                }));
                siloRows.replaceChildren();
                current.forEach(addSiloRow);
            }
        } catch (error) {
            availableSilos = [];
            siloRows.replaceChildren();
            message.textContent = error.message;
        }
        updateSiloTotals();
        updateSubmitState();
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
        const siloPending = isSector3() && !isSiloDistributionValid();
        const totalFields = requiredFields.length + (isSector3() ? 1 : 0);
        const completed = totalFields - pendingFields.length - (siloPending ? 1 : 0);
        const percentage = Math.round((completed / totalFields) * 100);
        progress.querySelector('[data-entry-progress-bar]').style.width = `${percentage}%`;
        const pendingCount = pendingFields.length + (siloPending ? 1 : 0);
        progress.querySelector('[data-entry-progress-count]').textContent = `${completed} de ${totalFields}`;
        progress.querySelector('[data-entry-progress-title]').textContent = pendingCount ? 'Campos pendientes' : 'Entrada lista para guardar';
        progress.querySelector('[data-entry-progress-message]').textContent = pendingCount
            ? `Faltan ${pendingCount} campo${pendingCount === 1 ? '' : 's'}. Toca uno para completarlo.`
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

        if (siloPending) {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = 'Distribución en silos';
            button.addEventListener('click', () => siloSection.scrollIntoView({ behavior: 'smooth', block: 'center' }));
            item.appendChild(button);
            list.appendChild(item);
        }

        progress.classList.toggle('is-complete', pendingCount === 0);
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
        updateSiloTotals();
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

    entradaForm.elements.Sector.addEventListener('change', () => updateSiloSection(false));
    entradaForm.elements.idProducto.addEventListener('change', () => updateSiloSection(true));
    entradaForm.querySelector('[data-add-silo]')?.addEventListener('click', () => {
        addSiloRow();
        updateSubmitState();
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

    updateSiloSection(false);
}