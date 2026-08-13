document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-predespacho-detalle]');
    if (!page) {
        return;
    }

    const apiUrl = page.dataset.apiUrl;
    const idCabeceraPredespacho = page.dataset.id;
    const messageBox = page.querySelector('[data-detalle-message]');
    const errorBox = page.querySelector('[data-detalle-error]');
    const itemsRows = page.querySelector('[data-items-rows]');
    const sapForm = page.querySelector('[data-inline-sap-form]');
    const closeUrl = page.dataset.closeUrl;
    const qrPanel = page.querySelector('[data-qr-panel]');
    const printButton = page.querySelector('[data-print-dispatch]');
    const closeQrCanvas = page.querySelector('[data-close-qr]');
    const printQrCanvas = document.querySelector('[data-print-qr]');
    const printItemsRows = document.querySelector('[data-print-items]');
    const addItemModal = document.querySelector('[data-add-item-modal]');
    const productSearchInput = addItemModal.querySelector('[data-product-search-input]');
    const productResults = addItemModal.querySelector('[data-product-results]');
    const lotesWrap = addItemModal.querySelector('[data-lotes-wrap]');
    const lotesRows = addItemModal.querySelector('[data-lotes-rows]');
    const addItemForm = addItemModal.querySelector('[data-add-item-form]');
    const selectedDisponible = addItemModal.querySelector('[data-selected-disponible]');
    const addItemError = addItemModal.querySelector('[data-add-item-error]');
    const cantidadInput = addItemForm.elements.cantidadSolicitada;
    const addItemButton = addItemModal.querySelector('[data-add-item-button]');
    let detalle = null;
    let selectedLote = null;
    let searchTimer = 0;
    let prefilledCantidad = null;

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

    function showAddItemError(text) {
        addItemError.textContent = text;
        addItemError.hidden = false;
    }

    function clearAddItemError() {
        addItemError.hidden = true;
        addItemError.textContent = '';
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

    function renderDetalle(data) {
        detalle = data;
        const qrDisponible = ['embarcado', 'cerrado'].includes(data?.statusGeneralPredespacho);
        page.querySelector('[data-summary-codigo]').textContent = data?.codigoInterno || 'Sin codigo';
        page.querySelector('[data-summary-cliente]').textContent = data?.nombreCliente || 'Sin cliente';
        page.querySelector('[data-summary-fecha]').textContent = data?.fechaRetiro || 'Sin dato';
        page.querySelector('[data-summary-sap]').textContent = data?.codigoNotaEntregaSAP || 'Sin dato';
        page.querySelector('[data-summary-observaciones]').textContent = data?.observaciones || 'Sin observaciones';
        page.querySelector('[data-summary-status]').innerHTML = `<span class="status-pill ${statusClass(data?.statusGeneralPredespacho)}">${formatValue(data?.statusGeneralPredespacho)}</span>`;
        page.querySelector('[data-summary-usuario-cierre]').textContent = data?.usuarioCierre || 'Sin cerrar';
        page.querySelector('[data-summary-fecha-cierre]').textContent = data?.fechaCierre || 'Sin cerrar';
        sapForm.elements.codigoNotaEntregaSAP.value = data?.codigoNotaEntregaSAP || '';
        document.querySelector('[data-print-codigo]').textContent = data?.codigoInterno || 'Sin código';
        document.querySelector('[data-print-cliente]').textContent = data?.nombreCliente || 'Sin cliente';
        document.querySelector('[data-print-fecha]').textContent = data?.fechaRetiro || 'Sin dato';
        document.querySelector('[data-print-sap]').textContent = data?.codigoNotaEntregaSAP || 'Sin dato';
        document.querySelector('[data-print-status]').textContent = data?.statusGeneralPredespacho || 'Sin dato';
        document.querySelector('[data-print-observaciones]').textContent = data?.observaciones || 'Sin observaciones';
        if (qrPanel) {
            qrPanel.hidden = !qrDisponible;
        }
        if (printButton) {
            printButton.hidden = !qrDisponible;
        }
        if (qrDisponible) {
            renderQr(closeQrCanvas);
            renderQr(printQrCanvas);
        }
    }

    function loadDetalle() {
        if (!Number(idCabeceraPredespacho)) {
            showMessage('No se recibio un id de predespacho valido.', true);
            return;
        }

        apiGet('detallePredespacho', { id: idCabeceraPredespacho })
            .then((response) => {
                if (!response.data) {
                    throw new Error('Predespacho no encontrado.');
                }
                renderDetalle(response.data);
            })
            .catch((error) => showMessage(error.message, true));
    }

    function renderItems(items) {
        if (printItemsRows) {
            printItemsRows.innerHTML = items.length === 0
                ? '<tr><td colspan="2">Este predespacho no tiene productos.</td></tr>'
                : items.map((item) => `
                    <tr>
                        <td>${formatValue(item.nombreProducto)}</td>
                        <td>${formatDecimal(item.cantidadDespachada)}</td>
                    </tr>
                `).join('');
        }

        if (items.length === 0) {
            itemsRows.innerHTML = '<tr><td colspan="8">Este predespacho no tiene items.</td></tr>';
            return;
        }

        itemsRows.innerHTML = items.map((item, index) => {
            const cantidadDespachada = Number(item.cantidadDespachada || 0);
            const tieneDespacho = cantidadDespachada > 0;
            return `
                <tr data-item-id="${escapeHtml(item.idItem)}">
                    <td data-label="#">${index + 1}</td>
                    <td data-label="NumLote">${formatValue(item.NumLote)}</td>
                    <td data-label="Producto">${formatValue(item.nombreProducto)}</td>
                    <td data-label="Sector">${formatValue(item.sector)}</td>
                    <td data-label="Cant. Solicitada">${formatDecimal(item.cantidadSolicitada)}</td>
                    <td data-label="Cant. Despachada">${formatDecimal(item.cantidadDespachada)}</td>
                    <td data-label="Status"><span class="status-pill ${statusClass(item.estatusItemPredespacho)}">${formatValue(item.estatusItemPredespacho)}</span></td>
                    <td class="table-actions" data-label="Acciones">
                        <button type="button" data-refrescar-item>Refrescar</button>
                        ${tieneDespacho && item.estatusItemPredespacho === 'pendiente' ? `
                            <button type="button" data-cerrar-merma>Cerrar con merma</button>
                        ` : ''}
                        ${!tieneDespacho ? `
                            <button type="button" data-cerrar-item ${item.estatusItemPredespacho === 'cerrado' ? 'disabled' : ''}>Cerrar item</button>
                            <button type="button" data-eliminar-item>Eliminar</button>
                        ` : ''}
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderQr(container) {
        if (!container || !closeUrl || !window.QRCode) {
            return;
        }

        container.replaceChildren();
        new window.QRCode(container, {
            text: closeUrl,
            width: 180,
            height: 180,
            margin: 1,
            colorDark: '#171717',
            colorLight: '#ffffff',
            correctLevel: window.QRCode.CorrectLevel.M
        });
    }

    function loadItems() {
        itemsRows.innerHTML = '<tr><td colspan="8">Cargando items...</td></tr>';

        apiGet('itemsPredespacho', { id: idCabeceraPredespacho })
            .then((response) => renderItems(response.data || []))
            .catch((error) => {
                itemsRows.innerHTML = '<tr><td colspan="8">No se pudieron cargar los items.</td></tr>';
                showMessage(error.message, true);
            });
    }

    function clearAddPanel() {
        prefilledCantidad = null;
        selectedLote = null;
        clearAddItemError();
        productSearchInput.value = '';
        productResults.hidden = true;
        productResults.innerHTML = '';
        lotesWrap.hidden = true;
        lotesRows.innerHTML = '';
        addItemForm.reset();
        addItemForm.elements.idCabeceraPredespacho.value = idCabeceraPredespacho;
        selectedDisponible.value = '';
        cantidadInput.disabled = true;
        cantidadInput.removeAttribute('max');
        addItemButton.disabled = true;
    }

    function renderProductResults(productos) {
        if (productos.length === 0) {
            productResults.innerHTML = '<div class="predespacho-result-empty">Sin resultados</div>';
            productResults.hidden = false;
            return;
        }

        productResults.innerHTML = productos.map((producto) => `
            <button type="button" class="searchable-select-option" data-select-product data-id-producto="${escapeHtml(producto.idProducto)}" data-sector="${escapeHtml(producto.sector)}">
                ${formatValue(producto.nombreProducto)} | Producto ${formatValue(producto.idProducto)} | ${formatValue(producto.codigoInterno)} | Presentacion ${formatValue(producto.idPresentacion)} | Sector ${formatValue(producto.sector)}
            </button>
        `).join('');
        productResults.hidden = false;
    }

    function searchProducts() {
        const term = productSearchInput.value.trim();
        clearAddItemError();
        if (term.length === 0) {
            productResults.hidden = true;
            return;
        }

        apiGet('buscarProductos', { termino: term })
            .then((response) => renderProductResults(response.data || []))
            .catch((error) => showMessage(error.message, true));
    }

    function renderLotes(lotes) {
        if (lotes.length === 0) {
            lotesRows.innerHTML = '<tr><td colspan="5">No hay lotes disponibles para este producto.</td></tr>';
            lotesWrap.hidden = false;
            return;
        }

        lotesRows.innerHTML = lotes.map((lote) => `
            <tr data-lote-id="${escapeHtml(lote.idInventarioEntrante)}" data-disponible="${escapeHtml(lote.cantidad_disponible)}" data-num-lote="${escapeHtml(lote.NumLote)}">
                <td data-label="NumLote">${formatValue(lote.NumLote)}</td>
                <td data-label="Sector">${formatValue(lote.sector)}</td>
                <td data-label="Stock Total">${formatDecimal(lote.stock_total)}</td>
                <td data-label="Disponible"><span class="stock-pill">${formatDecimal(lote.cantidad_disponible)}</span></td>
                <td class="table-actions" data-label="Accion"><button type="button" data-select-lote>Seleccionar</button></td>
            </tr>
        `).join('');
        lotesWrap.hidden = false;
    }

    function loadLotes(idProducto) {
        lotesRows.innerHTML = '<tr><td colspan="5">Cargando lotes...</td></tr>';
        lotesWrap.hidden = false;

        apiGet('lotesPorProducto', { idProducto })
            .then((response) => renderLotes(response.data || []))
            .catch((error) => showMessage(error.message, true));
    }

    productSearchInput.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(searchProducts, 400);
    });

    productResults.addEventListener('click', (event) => {
        const button = event.target.closest('[data-select-product]');
        if (!button) {
            return;
        }

        productSearchInput.value = button.textContent.trim();
        productResults.hidden = true;
        clearAddItemError();
        loadLotes(button.dataset.idProducto);
    });

    lotesRows.addEventListener('click', (event) => {
        const row = event.target.closest('[data-lote-id]');
        if (!row || !event.target.closest('[data-select-lote]')) {
            return;
        }

        selectedLote = {
            idInventarioEntrante: row.dataset.loteId,
            cantidadDisponible: Number(row.dataset.disponible || 0),
            numLote: row.dataset.numLote
        };
        addItemForm.elements.idInventarioEntrante.value = selectedLote.idInventarioEntrante;
        selectedDisponible.value = `Lote ${selectedLote.numLote} | Disponible: ${formatDecimal(selectedLote.cantidadDisponible)}`;
        clearAddItemError();
        cantidadInput.disabled = false;
        cantidadInput.max = String(selectedLote.cantidadDisponible);
        addItemButton.disabled = false;
        if (prefilledCantidad !== null) {
            cantidadInput.value = formatDecimal(Math.min(prefilledCantidad, selectedLote.cantidadDisponible));
            cantidadInput.focus();
        }
    });

    sapForm.addEventListener('submit', (event) => {
        event.preventDefault();
        clearMessages();

        apiPost('actualizarCodigoSAP', new FormData(sapForm))
            .then((response) => {
                showMessage(response.mensaje || 'Codigo SAP actualizado correctamente.');
                loadDetalle();
            })
            .catch((error) => showMessage(error.message, true));
    });

    itemsRows.addEventListener('click', (event) => {
        const row = event.target.closest('[data-item-id]');
        if (!row) {
            return;
        }

        if (event.target.closest('[data-refrescar-item]')) {
            loadItems();
            loadDetalle();
            showMessage('Items actualizados correctamente.');
        }

        if (event.target.closest('[data-cerrar-item]')) {
            const formData = new FormData();
            formData.set('idItem', row.dataset.itemId);

            apiPost('cerrarItem', formData)
                .then((response) => {
                    showMessage(response.mensaje || 'Item cerrado correctamente.');
                    loadItems();
                    loadDetalle();
                })
                .catch((error) => showMessage(error.message, true));
        }

        if (event.target.closest('[data-cerrar-merma]')) {
            if (!window.confirm(
                'Este item tiene una entrega parcial.\n\n' +
                'Al cerrarlo con merma, la cantidad solicitada se ajustará a lo ya despachado ' +
                'y el item quedará marcado como cerrado.\n\n' +
                '¿Desea continuar?'
            )) {
                return;
            }

            const formData = new FormData();
            formData.set('idItem', row.dataset.itemId);
            formData.set('idCabeceraPredespacho', idCabeceraPredespacho);

            apiPost('cerrarItemConMerma', formData)
                .then((response) => {
                    const diferencia = Number(response.diferencia || 0);
                    const nombreProducto = response.nombreProducto || 'el producto';
                    const agregarOtro = diferencia > 0 && window.confirm(
                        `✓ Item cerrado correctamente.\n\n` +
                        `Quedaron ${formatDecimal(diferencia)} unidades de "${nombreProducto}" sin entregar.\n\n` +
                        `¿Desea registrar esas ${formatDecimal(diferencia)} unidades desde un lote diferente?`
                    );

                    if (agregarOtro) {
                        clearAddPanel();
                        prefilledCantidad = diferencia;
                        productSearchInput.value = response.nombreProducto || '';
                        openModal(addItemModal);
                        searchProducts();
                    } else {
                        showMessage('Item cerrado. La diferencia fue registrada como merma.');
                        const verifyData = new FormData();
                        verifyData.set('idCabeceraPredespacho', idCabeceraPredespacho);
                        apiPost('verificarCierrePredespacho', verifyData)
                            .finally(() => { loadItems(); loadDetalle(); });
                    }
                })
                .catch((error) => showMessage(error.message, true));
        }

        if (event.target.closest('[data-eliminar-item]')) {
            if (!window.confirm('Eliminar este item del predespacho?')) {
                return;
            }

            const formData = new FormData();
            formData.set('idItem', row.dataset.itemId);

            apiPost('eliminarItem', formData)
                .then((response) => {
                    showMessage(response.mensaje || 'Item eliminado correctamente.');
                    loadItems();
                    loadDetalle();
                })
                .catch((error) => showMessage(error.message, true));
        }
    });

    addItemForm.addEventListener('submit', (event) => {
        event.preventDefault();
        clearMessages();
        clearAddItemError();

        const cantidad = Number(cantidadInput.value || 0);
        if (!selectedLote || cantidad <= 0) {
            showAddItemError('Seleccione un lote y escriba una cantidad mayor que cero.');
            return;
        }

        if (cantidad > selectedLote.cantidadDisponible) {
            showAddItemError(`La cantidad no puede exceder el disponible: ${formatDecimal(selectedLote.cantidadDisponible)}`);
            cantidadInput.focus();
            return;
        }

        apiPost('agregarItem', new FormData(addItemForm))
            .then((response) => {
                showMessage(response.mensaje || 'Item agregado correctamente.');
                clearAddPanel();
                closeModal(addItemModal);
                loadItems();
            })
            .catch((error) => showAddItemError(error.message));
    });

    if (printButton) {
        printButton.addEventListener('click', () => {
            if (!['embarcado', 'cerrado'].includes(detalle?.statusGeneralPredespacho)) {
                return;
            }

            window.print();
        });
    }

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('.correction-modal')));
    });

    page.querySelector('[data-open-add-item-modal]').addEventListener('click', () => {
        clearAddPanel();
        openModal(addItemModal);
    });

    page.querySelector('[data-refresh-items]').addEventListener('click', loadItems);
    addItemModal.querySelector('[data-clear-add-panel]').addEventListener('click', clearAddPanel);

    loadDetalle();
    loadItems();
});