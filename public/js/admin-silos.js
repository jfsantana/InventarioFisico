document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-silo-admin]');
    if (!page) return;

    const siloModal = document.querySelector('[data-silo-modal]');
    const siloForm = siloModal.querySelector('[data-silo-form]');
    const deleteModal = document.querySelector('[data-silo-delete-modal]');
    const assignmentModal = document.querySelector('[data-assignment-modal]');
    const assignmentForm = assignmentModal.querySelector('[data-assignment-form]');
    let availableSilos = [];

    function openModal(modal) {
        modal.hidden = false;
        modal.classList.add('is-open');
        document.body.classList.add('modal-is-open');
    }

    function closeModal(modal) {
        modal.classList.remove('is-open');
        modal.hidden = true;
        if (!document.querySelector('.correction-modal.is-open')) document.body.classList.remove('modal-is-open');
    }

    function siloOptions() {
        return '<option value="">Seleccione un silo</option>' + availableSilos.map((silo) =>
            `<option value="${silo.idSilo}" data-available="${silo.capacidadDisponible}">${silo.codigo} - ${silo.nombre} (${Number(silo.capacidadDisponible).toFixed(3)} kg disponibles)</option>`
        ).join('');
    }

    function addAssignmentRow() {
        const row = document.createElement('div');
        row.className = 'silo-assignment-row';
        row.innerHTML = `<select name="idSilo[]" required>${siloOptions()}</select><input name="cantidadSilo[]" type="number" min="0.001" step="0.001" placeholder="Cantidad kg" required><button type="button" title="Quitar" aria-label="Quitar silo">&times;</button>`;
        row.querySelector('button').addEventListener('click', () => { row.remove(); updateAssignmentOptions(); updateAssignmentTotal(); });
        row.querySelectorAll('select,input').forEach((field) => field.addEventListener('input', () => {
            updateAssignmentOptions();
            updateAssignmentTotal();
        }));
        assignmentForm.querySelector('[data-assignment-rows]').appendChild(row);
        updateAssignmentOptions();
    }

    function updateAssignmentOptions() {
        const selects = Array.from(assignmentForm.querySelectorAll('[name="idSilo[]"]'));
        const selected = new Set(selects.map((select) => select.value).filter(Boolean));
        selects.forEach((select) => {
            Array.from(select.options).forEach((option) => {
                option.disabled = option.value !== '' && option.value !== select.value && selected.has(option.value);
            });
        });
    }

    function updateAssignmentTotal() {
        const required = Number(assignmentForm.dataset.requiredQuantity || 0);
        const rows = Array.from(assignmentForm.querySelectorAll('.silo-assignment-row'));
        const assigned = rows.reduce((sum, row) => sum + (Number(row.querySelector('[name="cantidadSilo[]"]').value) || 0), 0);
        const pending = required - assigned;
        const validRows = rows.length > 0 && rows.every((row) => {
            const select = row.querySelector('[name="idSilo[]"]');
            const quantity = Number(row.querySelector('[name="cantidadSilo[]"]').value) || 0;
            const available = Number(select.selectedOptions[0]?.dataset.available || 0);
            return select.value !== '' && quantity > 0 && quantity <= available + 0.0005;
        });
        const message = assignmentForm.querySelector('[data-assignment-message]');
        const complete = validRows && Math.abs(pending) < 0.0005;
        message.textContent = complete ? 'Distribución completa.' : `${pending > 0 ? 'Faltan' : 'Exceden'} ${Math.abs(pending).toFixed(3)} kg.`;
        message.className = `message ${complete ? 'message--success' : 'message--error'}`;
        assignmentForm.querySelector('[type="submit"]').disabled = !complete;
    }

    page.querySelector('[data-open-silo-modal]').addEventListener('click', () => {
        siloForm.reset();
        siloForm.elements.idSilo.value = '';
        siloForm.elements.activo.checked = true;
        siloModal.querySelector('[data-silo-modal-title]').textContent = 'Nuevo silo';
        openModal(siloModal);
    });

    page.addEventListener('click', async (event) => {
        const edit = event.target.closest('[data-edit-silo]');
        if (edit) {
            const row = edit.closest('[data-silo-row]');
            siloForm.elements.idSilo.value = row.dataset.id;
            siloForm.elements.codigo.value = row.dataset.codigo;
            siloForm.elements.nombre.value = row.dataset.nombre;
            siloForm.elements.capacidad.value = row.dataset.capacidad;
            siloForm.elements.activo.checked = row.dataset.activo === '1';
            siloModal.querySelector('[data-silo-modal-title]').textContent = `Editar silo ${row.dataset.codigo}`;
            openModal(siloModal);
        }

        const remove = event.target.closest('[data-delete-silo]');
        if (remove) {
            const row = remove.closest('[data-silo-row]');
            deleteModal.querySelector('[name="idSilo"]').value = row.dataset.id;
            deleteModal.querySelector('[data-delete-silo-message]').textContent = `Se eliminará ${row.dataset.codigo} - ${row.dataset.nombre}.`;
            openModal(deleteModal);
        }

        const assign = event.target.closest('[data-assign-entry]');
        if (assign) {
            const response = await fetch(`${page.dataset.availabilityEndpoint}?idProducto=${encodeURIComponent(assign.dataset.productId)}`);
            availableSilos = await response.json();
            if (!response.ok) return;
            assignmentForm.reset();
            assignmentForm.elements.idInventarioEntrante.value = assign.dataset.entryId;
            assignmentForm.dataset.requiredQuantity = assign.dataset.quantity;
            assignmentForm.querySelector('[data-assignment-subtitle]').textContent = `${assign.dataset.product} · Lote ${assign.dataset.lot}`;
            assignmentForm.querySelector('[data-assignment-required]').textContent = `${Number(assign.dataset.quantity).toFixed(3)} kg`;
            assignmentForm.querySelector('[data-assignment-rows]').replaceChildren();
            addAssignmentRow();
            updateAssignmentTotal();
            openModal(assignmentModal);
        }
    });

    assignmentForm.querySelector('[data-add-assignment]').addEventListener('click', addAssignmentRow);
    assignmentForm.addEventListener('submit', (event) => {
        updateAssignmentTotal();
        if (assignmentForm.querySelector('[type="submit"]').disabled) event.preventDefault();
    });
    document.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', () => closeModal(button.closest('.correction-modal'))));
    document.querySelectorAll('.correction-modal').forEach((modal) => modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(modal); }));
});