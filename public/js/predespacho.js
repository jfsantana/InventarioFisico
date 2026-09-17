document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-predespacho-page]');
    if (!page) {
        return;
    }

    const apiUrl = page.dataset.apiUrl;
    const csrfToken = page.dataset.csrfToken;
    const rowsBody = page.querySelector('[data-predespacho-rows]');
    const searchInput = page.querySelector('[data-search-predespacho]');
    const statusFilter = page.querySelector('[data-status-filter]');
    const messageBox = page.querySelector('[data-predespacho-message]');
    const errorBox = page.querySelector('[data-predespacho-error]');
    const predespachoModal = document.querySelector('[data-predespacho-modal]');
    const clienteModal = document.querySelector('[data-cliente-modal]');
    const sapModal = document.querySelector('[data-sap-modal]');
    const detalleModal = document.querySelector('[data-detalle-modal]');
    const deleteModal = document.querySelector('[data-predespacho-delete-modal]');
    const predespachoForm = predespachoModal.querySelector('[data-predespacho-form]');
    const clienteForm = clienteModal.querySelector('[data-cliente-form]');
    const sapForm = sapModal.querySelector('[data-sap-form]');
    const clientesSelect = predespachoForm.querySelector('[data-clientes-select]');
    const detalleItems = detalleModal.querySelector('[data-detalle-items]');
    const detalleSummary = detalleModal.querySelector('[data-detalle-summary]');
    let predespachos = [];
    let clientes = [];
    let predespachoAEliminar = null;

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

    function showMessage(text, isError = false) {
        const target = isError ? errorBox : messageBox;
        const other = isError ? messageBox : errorBox;
        other.hidden = true;
        target.textContent = text;
        target.hidden = false;
    }

    function clearMessages() {
        messageBox.hidden = true;
        errorBox.hidden = true;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#039;',
            '"': '&quot;'
        }[char]));
    }

    function formatValue(value) {
        return value === null || value === undefined || value === '' ? 'Sin dato' : escapeHtml(value);
    }

    function formatDecimal(value) {
        const number = Number(value || 0);
        return Number.isFinite(number) ? number.toFixed(2) : '0.00';
    }

    function statusClass(status) {
        if (status === 'abierto') {
            return 'is-active';
        }
        if (status === 'pendiente') {
            return 'is-pending';
        }
        return 'is-closed';
    }

    function apiGet(action, params = {}) {
        const url = new URL(apiUrl, window.location.href);
        url.searchParams.set('accion', action);
        Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));

        return fetch(url.toString()).then((response) => response.json().then((data) => {
            if (!response.ok || data.success === false) {
                throw new Error(data.mensaje || 'No se pudo completar la solicitud.');
            }
            return data;
        }));
    }

    function apiPost(action, formData) {
        formData.set('accion', action);
        if (['actualizarCabecera', 'eliminarCabecera'].includes(action)) {
            formData.set('csrf_token', csrfToken);
        }

        return fetch(apiUrl, {
            method: 'POST',
            body: formData
        }).then((response) => response.json().then((data) => {
            if (!response.ok || data.success === false) {
                throw new Error(data.mensaje || 'No se pudo completar la solicitud.');
            }
            return data;
        }));
    }

    function renderPredespachos() {
        const term = searchInput.value.trim().toLowerCase();
        const status = statusFilter.value;
        const filtered = predespachos.filter((item) => {
            const text = `${item.codigoInterno ?? ''} ${item.nombreCliente ?? ''} ${item.codigoNotaEntregaSAP ?? ''}`.toLowerCase();
            const matchesSearch = !term || text.includes(term);
            const matchesStatus = !status || item.statusGeneralPredespacho === status;
            return matchesSearch && matchesStatus;
        });

        if (filtered.length === 0) {
            rowsBody.innerHTML = '<tr><td colspan="7">No hay predespachos para mostrar.</td></tr>';
            return;
        }

        rowsBody.innerHTML = filtered.map((item) => `
            <tr data-predespacho-id="${escapeHtml(item.idCabeceraPredespacho)}">
                <td data-label="Codigo Interno"><strong>${formatValue(item.codigoInterno)}</strong></td>
                <td data-label="Cliente">${formatValue(item.nombreCliente)}</td>
                <td data-label="Fecha Retiro">${formatValue(item.fechaRetiro)}</td>
                <td data-label="Codigo SAP">${formatValue(item.codigoNotaEntregaSAP)}</td>
                <td data-label="Status"><span class="status-pill ${statusClass(item.statusGeneralPredespacho)}">${formatValue(item.statusGeneralPredespacho)}</span></td>
                <td data-label="Fecha Creacion">${formatValue(item.fechaCreacion)}</td>
                <td class="table-actions predespacho-header-actions" data-label="Acciones">
                    <button class="predespacho-header-action predespacho-header-action--edit" type="button" data-editar-cabecera title="Editar cabecera" aria-label="Editar cabecera">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v5"/><path d="M14 3v4h4"/><path d="M6 3v18h7"/><path d="m13 17 5.5-5.5a2.1 2.1 0 0 1 3 3L16 20l-4 1 1-4Z"/><path d="m17.5 12.5 3 3"/></svg>
                    </button>
                    <button class="predespacho-header-action predespacho-header-action--view" type="button" data-ver-detalle title="Ver detalles" aria-label="Ver detalles">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/></svg>
                    </button>
                    <button class="predespacho-header-action predespacho-header-action--sap" type="button" data-editar-sap title="Editar código SAP" aria-label="Editar código SAP">Cod-SAP</button>
                    <button type="button" data-eliminar-cabecera
                        class="predespacho-header-action predespacho-header-action--delete ${Number(item.cantidadItems || 0) > 0 ? 'is-disabled-action' : ''}"
                        ${Number(item.cantidadItems || 0) > 0 ? 'disabled' : ''}
                        aria-label="${Number(item.cantidadItems || 0) > 0 ? 'No se puede eliminar porque tiene items' : 'Eliminar cabecera'}"
                        title="${Number(item.cantidadItems || 0) > 0 ? 'No se puede eliminar porque tiene items' : 'Eliminar cabecera'}">&#128465;</button>
                </td>
            </tr>
        `).join('');
    }

    function loadPredespachos() {
        clearMessages();
        rowsBody.innerHTML = '<tr><td colspan="7">Cargando predespachos...</td></tr>';

        apiGet('listarPredespachos')
            .then((data) => {
                predespachos = data.data || [];
                renderPredespachos();
            })
            .catch((error) => {
                rowsBody.innerHTML = '<tr><td colspan="7">No se pudo cargar la lista.</td></tr>';
                showMessage(error.message, true);
            });
    }

    function renderClientes(selectedId = '') {
        clientesSelect.innerHTML = '<option value="">Seleccione un cliente</option>' + clientes.map((cliente) => `
            <option value="${escapeHtml(cliente.idCliente)}" ${String(cliente.idCliente) === String(selectedId) ? 'selected' : ''}>${formatValue(cliente.nombre)} - ${formatValue(cliente.rif)}</option>
        `).join('');
    }

    function loadClientes(selectedId = '') {
        return apiGet('listarClientes')
            .then((data) => {
                clientes = data.data || [];
                renderClientes(selectedId);
            })
            .catch((error) => {
                clientesSelect.innerHTML = '<option value="">No se pudieron cargar clientes</option>';
                showMessage(error.message, true);
            });
    }

    function getPredespachoFromRow(row) {
        const id = row.dataset.predespachoId;
        return predespachos.find((item) => String(item.idCabeceraPredespacho) === String(id));
    }

    function openSapModal(predespacho) {
        sapForm.reset();
        sapForm.elements.idCabeceraPredespacho.value = predespacho.idCabeceraPredespacho;
        sapForm.elements.codigoNotaEntregaSAP.value = predespacho.codigoNotaEntregaSAP || '';
        sapModal.querySelector('[data-sap-target]').textContent = predespacho.codigoInterno || `Predespacho #${predespacho.idCabeceraPredespacho}`;
        openModal(sapModal);
    }

    function openHeaderModal(predespacho = null) {
        predespachoForm.reset();
        const isEdit = Boolean(predespacho);
        predespachoForm.elements.idCabeceraPredespacho.value = isEdit
            ? predespacho.idCabeceraPredespacho
            : '';
        predespachoModal.querySelector('[data-predespacho-modal-title]').textContent = isEdit
            ? 'Editar cabecera'
            : 'Nuevo Predespacho';

        loadClientes(isEdit ? predespacho.idCliente : '').finally(() => {
            if (isEdit) {
                predespachoForm.elements.fechaRetiro.value = predespacho.fechaRetiro || '';
                predespachoForm.elements.codigoNotaEntregaSAP.value = predespacho.codigoNotaEntregaSAP || '';
                predespachoForm.elements.observaciones.value = predespacho.observaciones || '';
            }
            openModal(predespachoModal);
        });
    }

    function openDeleteModal(predespacho) {
        predespachoAEliminar = predespacho;
        deleteModal.querySelector('[data-predespacho-delete-message]').textContent =
            `Se eliminará la cabecera ${predespacho.codigoInterno}. Esta acción no se puede deshacer.`;
        openModal(deleteModal);
    }

    function openDetalleModal(predespacho) {
        detalleItems.innerHTML = '<tr><td colspan="7">Cargando items...</td></tr>';
        detalleSummary.textContent = `${predespacho.codigoInterno || 'Sin codigo'} - ${predespacho.nombreCliente || 'Sin cliente'}`;
        openModal(detalleModal);

        apiGet('itemsPredespacho', { id: predespacho.idCabeceraPredespacho })
            .then((data) => {
                const items = data.data || [];
                if (items.length === 0) {
                    detalleItems.innerHTML = '<tr><td colspan="7">Este predespacho no tiene items.</td></tr>';
                    return;
                }
                detalleItems.innerHTML = items.map((item) => `
                    <tr>
                        <td data-label="Lote">${formatValue(item.NumLote)}</td>
                        <td data-label="Producto">${formatValue(item.idProducto)}</td>
                        <td data-label="Presentacion">${formatValue(item.idPresentacion)}</td>
                        <td data-label="Sector">${formatValue(item.sector)}</td>
                        <td data-label="Solicitada">${formatDecimal(item.cantidadSolicitada)}</td>
                        <td data-label="Despachada">${formatDecimal(item.cantidadDespachada)}</td>
                        <td data-label="Status"><span class="status-pill ${statusClass(item.estatusItemPredespacho)}">${formatValue(item.estatusItemPredespacho)}</span></td>
                    </tr>
                `).join('');
            })
            .catch((error) => {
                detalleItems.innerHTML = '<tr><td colspan="7">No se pudieron cargar los items.</td></tr>';
                showMessage(error.message, true);
            });
    }

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('.correction-modal')));
    });

    page.querySelector('[data-open-predespacho-modal]').addEventListener('click', () => {
        openHeaderModal();
    });

    predespachoModal.querySelector('[data-open-cliente-modal]').addEventListener('click', () => {
        clienteForm.reset();
        openModal(clienteModal);
    });

    page.querySelector('[data-refresh-predespachos]').addEventListener('click', loadPredespachos);
    searchInput.addEventListener('input', renderPredespachos);
    statusFilter.addEventListener('change', renderPredespachos);

    rowsBody.addEventListener('click', (event) => {
        const row = event.target.closest('[data-predespacho-id]');
        if (!row) {
            return;
        }

        const predespacho = getPredespachoFromRow(row);
        if (!predespacho) {
            return;
        }

        if (event.target.closest('[data-ver-detalle]')) {
            const appBase = apiUrl.replace(/\/public\/predespacho_api\.php$/, '');
            window.location.href = `${appBase}/predespacho/detalle?id=${encodeURIComponent(predespacho.idCabeceraPredespacho)}`;
        }

        if (event.target.closest('[data-editar-sap]')) {
            openSapModal(predespacho);
        }

        if (event.target.closest('[data-editar-cabecera]')) {
            openHeaderModal(predespacho);
        }

        if (event.target.closest('[data-eliminar-cabecera]')) {
            openDeleteModal(predespacho);
        }
    });

    predespachoForm.addEventListener('submit', (event) => {
        event.preventDefault();
        clearMessages();

        const isEdit = Boolean(predespachoForm.elements.idCabeceraPredespacho.value);
        apiPost(isEdit ? 'actualizarCabecera' : 'crearPredespacho', new FormData(predespachoForm))
            .then((data) => {
                closeModal(predespachoModal);
                showMessage(data.mensaje || (isEdit ? 'Cabecera actualizada correctamente.' : 'Predespacho creado correctamente.'));
                loadPredespachos();
            })
            .catch((error) => showMessage(error.message, true));
    });

    deleteModal.querySelector('[data-confirm-delete-header]').addEventListener('click', () => {
        if (!predespachoAEliminar) {
            return;
        }

        const formData = new FormData();
        formData.set('idCabeceraPredespacho', predespachoAEliminar.idCabeceraPredespacho);
        apiPost('eliminarCabecera', formData)
            .then((data) => {
                closeModal(deleteModal);
                predespachoAEliminar = null;
                showMessage(data.mensaje || 'Predespacho eliminado correctamente.');
                loadPredespachos();
            })
            .catch((error) => showMessage(error.message, true));
    });

    clienteForm.addEventListener('submit', (event) => {
        event.preventDefault();
        clearMessages();

        apiPost('crearCliente', new FormData(clienteForm))
            .then((data) => {
                closeModal(clienteModal);
                showMessage(data.mensaje || 'Cliente creado correctamente.');
                return loadClientes(data.idCliente);
            })
            .catch((error) => showMessage(error.message, true));
    });

    sapForm.addEventListener('submit', (event) => {
        event.preventDefault();
        clearMessages();

        apiPost('actualizarCodigoSAP', new FormData(sapForm))
            .then((data) => {
                closeModal(sapModal);
                showMessage(data.mensaje || 'Codigo SAP actualizado correctamente.');
                loadPredespachos();
            })
            .catch((error) => showMessage(error.message, true));
    });

    loadClientes();
    loadPredespachos();
});