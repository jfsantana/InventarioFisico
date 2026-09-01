document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-correction-page]').forEach(initCorrectionPage);
});

function initCorrectionPage(page) {
    const tableBody = page.querySelector('[data-correction-body]');
    const searchInput = page.querySelector('[data-filter-search]');
    const presentationFilter = page.querySelector('[data-filter-presentation]');
    const locationFilter = page.querySelector('[data-filter-location]');
    const dateFrom = page.querySelector('[data-filter-from]');
    const dateTo = page.querySelector('[data-filter-to]');
    const clearButton = page.querySelector('[data-clear-filters]');
    const countLabel = page.querySelector('[data-visible-count]');
    const perPageSelect = page.querySelector('[data-per-page]');
    const paginationButtons = page.querySelector('[data-pagination-buttons]');
    const paginationInfo = page.querySelector('[data-pagination-info]');
    const mobileFilterToggle = page.querySelector('[data-filter-toggle]');
    const filters = page.querySelector('[data-filter-panel]');
    const editModal = page.querySelector('[data-edit-modal]');
    const deleteModal = page.querySelector('[data-delete-modal]');
    const editForm = editModal.querySelector('[data-correction-modal-form]');
    const toastHost = page.querySelector('[data-toast-host]');
    const pageType = page.dataset.pageType;
    const deleteEndpoint = page.dataset.deleteEndpoint;
    const emailResendEndpoint = page.dataset.emailResendEndpoint;
    const documentDownloadEndpoint = page.dataset.documentDownloadEndpoint;
    const csrfToken = page.dataset.csrfToken;

    document.body.appendChild(editModal);
    document.body.appendChild(deleteModal);
    document.body.appendChild(toastHost);

    if (!tableBody) {
        countLabel.textContent = 'Mostrando 0 de 0 registros';
        paginationInfo.textContent = 'Página 1 de 1 | Total: 0 registros';
        paginationButtons.innerHTML = '';
        paginationButtons.appendChild(createPageButton('<< Anterior', false, () => {}));
        paginationButtons.appendChild(createPageButton('1', true, () => {}, true));
        paginationButtons.appendChild(createPageButton('Siguiente >>', false, () => {}));
        mobileFilterToggle.addEventListener('click', () => {
            const isOpen = filters.classList.toggle('is-open');
            mobileFilterToggle.setAttribute('aria-expanded', String(isOpen));
            mobileFilterToggle.textContent = isOpen ? 'Ocultar filtros 🔼' : 'Mostrar filtros 🔽';
        });
        clearButton.addEventListener('click', () => {
            searchInput.value = '';
            presentationFilter.value = '';
            locationFilter.value = '';
            dateFrom.value = '';
            dateTo.value = '';
        });
        return;
    }

    const rows = Array.from(tableBody.querySelectorAll('tr'));

    let filteredRows = [...rows];
    let currentPage = 1;
    let sortKey = 'fecha';
    let sortDirection = 'desc';
    let lastFocusedElement = null;

    const normalize = (value) => String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

    function applyFilters() {
        const query = normalize(searchInput.value);
        const presentation = presentationFilter.value;
        const location = locationFilter.value;
        const from = dateFrom.value;
        const to = dateTo.value;

        filteredRows = rows.filter((row) => {
            const matchesSearch = !query || normalize(row.dataset.search).includes(query);
            const matchesPresentation = !presentation || row.dataset.presentation === presentation;
            const matchesLocation = !location || row.dataset.location === location;
            const rowDate = row.dataset.fecha || '';
            const matchesFrom = !from || rowDate >= from;
            const matchesTo = !to || rowDate <= to;

            return matchesSearch && matchesPresentation && matchesLocation && matchesFrom && matchesTo;
        });

        currentPage = 1;
        renderTable();
    }

    function compareRows(rowA, rowB) {
        const type = page.querySelector(`[data-sort="${sortKey}"]`)?.dataset.type || 'text';
        let valueA = rowA.dataset[sortKey] || '';
        let valueB = rowB.dataset[sortKey] || '';

        if (type === 'number') {
            valueA = Number(valueA);
            valueB = Number(valueB);
        } else {
            valueA = normalize(valueA);
            valueB = normalize(valueB);
        }

        if (valueA < valueB) {
            return sortDirection === 'asc' ? -1 : 1;
        }

        if (valueA > valueB) {
            return sortDirection === 'asc' ? 1 : -1;
        }

        return 0;
    }

    function renderTable() {
        const perPage = Number(perPageSelect.value);
        const totalRows = filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / perPage));
        currentPage = Math.min(currentPage, totalPages);
        const start = (currentPage - 1) * perPage;
        const visibleRows = filteredRows.sort(compareRows).slice(start, start + perPage);

        rows.forEach((row) => {
            row.hidden = true;
        });

        visibleRows.forEach((row) => {
            row.hidden = false;
            tableBody.appendChild(row);
        });

        updateSortIndicators();
        renderPagination(totalPages, totalRows);
        countLabel.textContent = `Mostrando ${visibleRows.length} de ${rows.length} registros`;
    }

    function updateSortIndicators() {
        page.querySelectorAll('[data-sort-indicator]').forEach((indicator) => {
            indicator.textContent = '↕';
        });

        const activeIndicator = page.querySelector(`[data-sort="${sortKey}"] [data-sort-indicator]`);
        if (activeIndicator) {
            activeIndicator.textContent = sortDirection === 'asc' ? '↑' : '↓';
        }
    }

    function renderPagination(totalPages, totalRows) {
        paginationButtons.innerHTML = '';
        paginationButtons.appendChild(createPageButton('<< Anterior', currentPage > 1, () => {
            currentPage -= 1;
            renderTable();
        }));

        const pages = getPageNumbers(totalPages);
        pages.forEach((pageNumber) => {
            if (pageNumber === '...') {
                const dots = document.createElement('span');
                dots.className = 'pagination-dots';
                dots.textContent = '...';
                paginationButtons.appendChild(dots);
                return;
            }

            paginationButtons.appendChild(createPageButton(String(pageNumber), true, () => {
                currentPage = pageNumber;
                renderTable();
            }, pageNumber === currentPage));
        });

        paginationButtons.appendChild(createPageButton('Siguiente >>', currentPage < totalPages, () => {
            currentPage += 1;
            renderTable();
        }));
        paginationInfo.textContent = `Página ${currentPage} de ${totalPages} | Total: ${totalRows} registros`;
    }

    function getPageNumbers(totalPages) {
        if (totalPages <= 7) {
            return Array.from({ length: totalPages }, (_, index) => index + 1);
        }

        const pages = [1];
        if (currentPage > 4) {
            pages.push('...');
        }

        const start = Math.max(2, currentPage - 1);
        const end = Math.min(totalPages - 1, currentPage + 1);
        for (let pageNumber = start; pageNumber <= end; pageNumber += 1) {
            pages.push(pageNumber);
        }

        if (currentPage < totalPages - 3) {
            pages.push('...');
        }
        pages.push(totalPages);

        return pages;
    }

    function createPageButton(label, enabled, onClick, active = false) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.disabled = !enabled;
        button.className = active ? 'pagination-button is-active' : 'pagination-button';
        button.addEventListener('click', onClick);
        return button;
    }

    function openEditModal(row) {
        lastFocusedElement = document.activeElement;
        editForm.querySelectorAll('input[type="file"]').forEach((field) => {
            field.value = '';
        });
        editModal.removeAttribute('hidden');
        document.body.classList.add('modal-is-open');
        editModal.querySelector('[data-modal-title]').textContent = `Editar ${pageType === 'entrada' ? 'Entrada' : 'Salida'} #${row.dataset.id}`;
        editModal.querySelector('[data-modal-subtitle]').textContent = row.dataset.producto;

        if (pageType === 'entrada') {
            setFormValue(editForm, 'idInventarioEntrante', row.dataset.id);
            setFormValue(editForm, 'idProducto', row.dataset.productId);
            setFormValue(editForm, 'NumLote', row.dataset.lote);
            setFormValue(editForm, 'idPresentacion', row.dataset.presentation);
            setFormValue(editForm, 'idUbicacion', row.dataset.location);
            setFormValue(editForm, 'Sector', row.dataset.sector);
            setFormValue(editForm, 'CantidadEntrante', row.dataset.cantidad);
            setFormValue(editForm, 'idTipoCompra', row.dataset.tipoCompraId);
            setFormValue(editForm, 'CardCode', row.dataset.cardCode);
            setFormValue(editForm, 'FabricanteCode', row.dataset.fabricanteCode);
            setFormValue(editForm, 'PaisCode', row.dataset.paisCode);
            setFormValue(editForm, 'fecha_factura', row.dataset.fechaFactura);
            setFormValue(editForm, 'peso_romana', row.dataset.pesoRomana);
            setFormValue(editForm, 'nro_factura', row.dataset.nroFactura);
            editForm.querySelectorAll('[data-document-field]').forEach((container) => {
                const key = container.dataset.documentField;
                const documentId = row.dataset[`${key}Id`];
                const documentName = row.dataset[`${key}Name`];
                const link = container.querySelector('[data-document-link]');
                const empty = container.querySelector('[data-document-empty]');
                const name = container.querySelector('[data-document-name]');
                const fileInput = container.querySelector('input[type="file"]');

                link.hidden = !documentId || documentId === '0';
                empty.hidden = !link.hidden;
                fileInput.required = false;
                if (!link.hidden) {
                    link.href = `${documentDownloadEndpoint}/${documentId}`;
                    name.textContent = documentName;
                }
            });
            editForm.querySelector('[data-summary-salidas]').textContent = formatNumber(row.dataset.salidas);
            editForm.querySelector('[data-summary-salidas]').dataset.raw = row.dataset.salidas;
            editForm.querySelector('[data-summary-disponible]').textContent = formatNumber(row.dataset.disponible);
        } else {
            setFormValue(editForm, 'idInventarioSaliente', row.dataset.id);
            setFormValue(editForm, 'idInventarioEntrante', row.dataset.entradaId);
            setFormValue(editForm, 'NE', row.dataset.ne);
            setFormValue(editForm, 'cantidadSaliente', row.dataset.cantidad);
            setFormValue(editForm, 'productoVista', row.dataset.productId);
            setFormValue(editForm, 'NumLoteVista', row.dataset.lote);
            setFormValue(editForm, 'presentacionVista', row.dataset.presentation);
            setFormValue(editForm, 'ubicacionVista', row.dataset.location);
            editForm.querySelector('[data-summary-salidas]').textContent = formatNumber(row.dataset.cantidad);
            editForm.querySelector('[data-summary-salidas]').dataset.raw = row.dataset.cantidad;
            editForm.querySelector('[data-summary-disponible]').textContent = formatNumber(row.dataset.disponible);
            editForm.querySelector('[data-summary-disponible]').dataset.base = row.dataset.disponible;
        }

        updateCalculatedAvailability();
        editModal.classList.add('is-open');
        setTimeout(() => focusFirstField(editModal), 0);
    }

    function closeEditModal() {
        editModal.classList.remove('is-open');
        setTimeout(() => editModal.setAttribute('hidden', 'hidden'), 180);
        document.body.classList.remove('modal-is-open');
        editForm.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
        lastFocusedElement?.focus();
    }

    function openDeleteModal(row) {
        lastFocusedElement = document.activeElement;
        deleteModal.removeAttribute('hidden');
        document.body.classList.add('modal-is-open');
        deleteModal.querySelector('[data-delete-title]').textContent = '¿Eliminar este registro?';
        deleteModal.querySelector('[data-delete-message]').textContent = `Estás a punto de eliminar la ${pageType === 'entrada' ? 'Entrada' : 'Salida'} #${row.dataset.id} de ${row.dataset.producto}. Esta acción no se puede deshacer.`;
        deleteModal.querySelector('[data-delete-confirm]').dataset.deleteId = row.dataset.id;
        deleteModal.classList.add('is-open');
        setTimeout(() => deleteModal.querySelector('[data-delete-cancel]').focus(), 0);
    }

    function closeDeleteModal() {
        deleteModal.classList.remove('is-open');
        setTimeout(() => deleteModal.setAttribute('hidden', 'hidden'), 180);
        document.body.classList.remove('modal-is-open');
        lastFocusedElement?.focus();
    }

    function setFormValue(form, name, value) {
        const field = form.elements[name];
        if (field) {
            field.value = value || '';
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function focusFirstField(modal) {
        const focusable = modal.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), button:not([disabled])');
        focusable?.focus();
    }

    function updateCalculatedAvailability() {
        const quantityField = editForm.querySelector('[data-modal-quantity]');
        const availableField = editForm.querySelector('[data-summary-disponible]');
        if (!quantityField || !availableField) {
            return;
        }

        const quantity = Number(quantityField.value) || 0;
        if (pageType === 'entrada') {
            const exits = Number(editForm.querySelector('[data-summary-salidas]').dataset.raw) || 0;
            availableField.textContent = formatNumber(quantity - exits);
            return;
        }

        const baseAvailable = Number(availableField.dataset.base) || 0;
        availableField.textContent = formatNumber(baseAvailable - quantity);
    }

    function validateModalForm() {
        let isValid = true;
        editForm.querySelectorAll('[required]').forEach((field) => {
            const valid = field.value.trim() !== ''
                && (!field.matches('[type="number"]') || Number(field.value) > 0)
                && field.checkValidity();
            field.classList.toggle('is-invalid', !valid);
            if (!valid) {
                isValid = false;
            }
        });

        return isValid;
    }

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `correction-toast correction-toast--${type}`;
        toast.textContent = message;
        toastHost.appendChild(toast);
        setTimeout(() => toast.classList.add('is-visible'), 10);
        setTimeout(() => {
            toast.classList.remove('is-visible');
            setTimeout(() => toast.remove(), 220);
        }, 3000);
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    page.querySelectorAll('[data-sort]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextKey = button.dataset.sort;
            if (sortKey === nextKey) {
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                sortKey = nextKey;
                sortDirection = 'asc';
            }
            renderTable();
        });
    });

    [searchInput, presentationFilter, locationFilter, dateFrom, dateTo].forEach((control) => {
        control.addEventListener('input', applyFilters);
        control.addEventListener('change', applyFilters);
    });

    clearButton.addEventListener('click', () => {
        searchInput.value = '';
        presentationFilter.value = '';
        locationFilter.value = '';
        dateFrom.value = '';
        dateTo.value = '';
        applyFilters();
    });

    perPageSelect.addEventListener('change', () => {
        currentPage = 1;
        renderTable();
    });

    mobileFilterToggle.addEventListener('click', () => {
        const isOpen = filters.classList.toggle('is-open');
        mobileFilterToggle.setAttribute('aria-expanded', String(isOpen));
        mobileFilterToggle.textContent = isOpen ? 'Ocultar filtros 🔼' : 'Mostrar filtros 🔽';
    });

    tableBody.addEventListener('click', (event) => {
        const editButton = event.target.closest('[data-edit-row]');
        const emailButton = event.target.closest('[data-email-row]');
        const deleteButton = event.target.closest('[data-delete-row]');
        if (editButton) {
            openEditModal(editButton.closest('tr'));
        }
        if (emailButton) {
            const row = emailButton.closest('tr');
            if (!emailResendEndpoint || !row?.dataset.id) {
                showToast('No se pudo identificar la entrada para reenviar el correo.', 'error');
                return;
            }

            if (!window.confirm(`¿Reenviar el correo de la entrada #${row.dataset.id}?`)) {
                return;
            }

            emailButton.classList.add('is-loading');
            emailButton.disabled = true;

            const form = document.createElement('form');
            form.method = 'post';
            form.action = emailResendEndpoint;
            form.innerHTML = `<input type="hidden" name="idInventarioEntrante" value="${row.dataset.id}"><input type="hidden" name="csrf_token" value="${csrfToken}">`;
            document.body.appendChild(form);
            form.submit();
        }
        if (deleteButton) {
            openDeleteModal(deleteButton.closest('tr'));
        }
    });

    editModal.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', closeEditModal);
    });

    editForm.querySelector('[data-modal-quantity]')?.addEventListener('input', updateCalculatedAvailability);

    editForm.elements.idInventarioEntrante?.addEventListener('change', (event) => {
        if (pageType !== 'salida') {
            return;
        }

        const selected = event.currentTarget.selectedOptions[0];
        if (!selected) {
            return;
        }

        setFormValue(editForm, 'productoVista', selected.dataset.productId);
        setFormValue(editForm, 'presentacionVista', selected.dataset.presentationId);
        setFormValue(editForm, 'ubicacionVista', selected.dataset.locationId);
        editForm.querySelector('[data-summary-disponible]').dataset.base = selected.dataset.disponible || '0';
        updateCalculatedAvailability();
    });

    editModal.addEventListener('click', (event) => {
        if (event.target === editModal) {
            closeEditModal();
        }
    });

    deleteModal.querySelector('[data-delete-cancel]').addEventListener('click', closeDeleteModal);
    deleteModal.querySelector('[data-delete-confirm]').addEventListener('click', (event) => {
        const button = event.currentTarget;
        const id = button.dataset.deleteId;

        if (!deleteEndpoint || !id) {
            showToast('No se pudo identificar el registro a eliminar.', 'error');
            return;
        }

        button.classList.add('is-loading');
        button.disabled = true;

        const form = document.createElement('form');
        form.method = 'post';
        form.action = deleteEndpoint;

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = pageType === 'entrada' ? 'idInventarioEntrante' : 'idInventarioSaliente';
        input.value = id;
        form.appendChild(input);

        if (csrfToken) {
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = 'csrf_token';
            csrf.value = csrfToken;
            form.appendChild(csrf);
        }

        document.body.appendChild(form);
        form.submit();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }
        if (!editModal.hasAttribute('hidden')) {
            closeEditModal();
        }
    });

    page.querySelectorAll('[data-select-search]').forEach((input) => {
        input.addEventListener('input', () => {
            const select = editForm.querySelector(`#${input.dataset.selectSearch}`);
            const query = normalize(input.value);
            Array.from(select.options).forEach((option) => {
                option.hidden = query && !normalize(option.textContent).includes(query);
            });
        });
    });

    editForm.addEventListener('submit', (event) => {
        if (!validateModalForm()) {
            event.preventDefault();
            showToast('Complete los campos requeridos antes de guardar.', 'error');
            editForm.querySelector('.is-invalid')?.focus();
            return;
        }

        const submitButton = editForm.querySelector('[type="submit"]');
        submitButton.classList.add('is-loading');
        submitButton.disabled = true;
    });

    const serverMessage = page.querySelector('[data-server-message]');
    if (serverMessage) {
        showToast(serverMessage.textContent.trim(), serverMessage.dataset.messageType || 'success');
    }

    renderTable();
}
