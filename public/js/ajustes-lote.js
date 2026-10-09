document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('ajusteDialog');
    const form = document.getElementById('ajusteForm');
    if (!dialog || !form) return;

    const entrada = document.getElementById('ajusteEntrada');
    const tipos = Array.from(form.querySelectorAll('input[name="tipo"]'));
    const monto = document.getElementById('ajusteMonto');
    const observacion = document.getElementById('ajusteObservacion');
    const siloSection = document.getElementById('ajusteSiloCampo');
    const siloRows = document.getElementById('ajusteSiloFilas');
    const siloError = document.getElementById('ajusteSiloError');
    const addSilo = document.getElementById('ajusteAgregarSilo');
    const initialAssignments = JSON.parse(document.getElementById('ajusteSilosIniciales').textContent);
    let silos = [];
    let sector3 = false;
    let requestVersion = 0;
    let loading = false;
    let disponible = 0;

    const tipoSeleccionado = () => tipos.find(input => input.checked)?.value;
    const validarObservacion = () => {
        observacion.setCustomValidity(observacion.value.trim() === '' ? 'Indique la observacion del ajuste.' : '');
    };
    const montoValido = () => monto.value !== '' && Number(monto.value) > 0
        && !monto.validity.badInput && !monto.validity.rangeUnderflow
        && !monto.validity.rangeOverflow && !monto.validity.stepMismatch;
    const actualizarDistribucion = () => {
        const rows = Array.from(siloRows.children);
        const ids = rows.map(row => row.querySelector('select').value);
        const total = rows.reduce((sum, row) => sum + Math.round((Number(row.querySelector('input').value) || 0) * 1000), 0);
        const requerido = Math.round((Number(monto.value) || 0) * 1000);
        const habilitado = sector3 && Boolean(tipoSeleccionado()) && montoValido()
            && !loading && siloError.hidden && silos.length > 0;
        rows.forEach(row => {
            const select = row.querySelector('select');
            const quantity = row.querySelector('input');
            const selected = silos.find(silo => String(silo.idSilo) === select.value);
            const cantidadActual = Math.round((Number(quantity.value) || 0) * 1000);
            const restante = Math.max(0, requerido - total + cantidadActual) / 1000;
            quantity.max = selected ? String(Math.min(Number(selected.cantidadDisponible), restante)) : '0';
            select.disabled = !habilitado;
            quantity.disabled = !habilitado;
            row.querySelector('button').disabled = !habilitado;
            select.setCustomValidity(select.value && ids.filter(id => id === select.value).length > 1 ? 'No repita un silo.' : '');
            Array.from(select.options).forEach(option => {
                option.disabled = option.value !== '' && option.value !== select.value && ids.includes(option.value);
            });
        });
        const pendiente = (requerido - total) / 1000;
        const filasValidas = rows.every(row =>
            row.querySelector('select').checkValidity() && row.querySelector('input').checkValidity());
        addSilo.disabled = !habilitado || pendiente <= 0 || rows.length >= silos.length
            || !silos.some(silo => !ids.includes(String(silo.idSilo)));
        document.getElementById('ajusteSiloEstado').textContent =
            !tipoSeleccionado() || !montoValido() ? 'Seleccione el estado e indique un monto valido para distribuir.'
                : loading ? 'Consultando silos disponibles...'
                : `Distribuido: ${(total / 1000).toFixed(3)} / ${(Number(monto.value) || 0).toFixed(3)}. `
                + (Math.abs(pendiente) < 0.0005 && total > 0 && filasValidas ? 'Distribucion completa.'
                    : Math.abs(pendiente) < 0.0005 && !filasValidas ? 'Complete el silo y la cantidad de cada fila.'
                        : `${pendiente >= 0 ? 'Faltan' : 'Exceden'} ${Math.abs(pendiente).toFixed(3)}.`);
        monto.setCustomValidity(sector3 && tipoSeleccionado()
            && (!montoValido() || loading || !siloError.hidden || !rows.length || !filasValidas || Math.abs(pendiente) > 0.0005)
            ? 'Distribuya exactamente el monto entre los silos disponibles.' : '');
    };
    const agregarFila = (values = {}) => {
        if (siloRows.children.length >= silos.length) return;
        const row = document.createElement('div');
        row.className = 'silo-assignment-row';
        const select = document.createElement('select');
        select.name = 'idSilo[]';
        select.required = true;
        select.setAttribute('aria-label', 'Silo del ajuste');
        select.append(new Option('Seleccione un silo', ''));
        silos.forEach(silo => select.append(new Option(
            `${silo.codigo} - ${silo.nombre} (${Number(silo.cantidadDisponible).toFixed(3)} disponibles)`, silo.idSilo
        )));
        select.value = String(values.idSilo || '');
        const quantity = document.createElement('input');
        Object.assign(quantity, { name: 'cantidadSilo[]', type: 'number', min: '0.001', step: '0.001',
            inputMode: 'decimal', required: true, value: values.cantidad || '', placeholder: 'Cantidad' });
        quantity.setAttribute('aria-label', 'Cantidad del silo');
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = '×';
        remove.setAttribute('aria-label', 'Quitar silo');
        remove.addEventListener('click', () => { row.remove(); actualizarDistribucion(); });
        row.append(select, quantity, remove);
        siloRows.append(row);
        [select, quantity].forEach(field => field.addEventListener('input', actualizarDistribucion));
        actualizarDistribucion();
    };
    const cargarSilos = async (assignments = []) => {
        const version = ++requestVersion;
        siloRows.replaceChildren();
        silos = [];
        siloError.hidden = true;
        const tipo = tipoSeleccionado();
        siloSection.hidden = !sector3 || !tipo;
        loading = false;
        if (!sector3 || !tipo) {
            monto.setCustomValidity('');
            addSilo.disabled = true;
            return;
        }
        document.getElementById('ajusteSiloAyuda').textContent = tipo === 'positivo'
            ? 'Solo silos vacios o con el mismo producto y espacio libre.'
            : 'Solo silos con saldo de este lote. Puede distribuir la disminucion entre varios.';
        loading = true;
        addSilo.disabled = true;
        actualizarDistribucion();
        try {
            const params = new URLSearchParams({ idInventarioEntrante: entrada.value, tipo });
            const response = await fetch(`${form.dataset.siloEndpoint}?${params}`);
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'No se pudieron consultar los silos.');
            if (!Array.isArray(data)) throw new Error('Respuesta de silos no valida.');
            if (version !== requestVersion) return;
            silos = data;
            if (!silos.length) throw new Error('No hay silos disponibles para este ajuste.');
            loading = false;
            if (assignments.length) assignments.forEach(agregarFila);
            else agregarFila();
        } catch (error) {
            if (version !== requestVersion) return;
            loading = false;
            siloError.textContent = error.message;
            siloError.hidden = false;
            actualizarDistribucion();
        }
    };
    const actualizarLimite = () => {
        const tipo = tipos.find(input => input.checked);
        monto.max = tipo && tipo.value === 'negativo' ? String(Math.max(0, disponible)) : '99999999999.999';
    };
    const abrir = (button, conservar = false) => {
        if (!conservar) {
            form.reset();
            tipos.forEach(input => { input.checked = false; });
            monto.value = '';
            observacion.value = '';
        }
        entrada.value = button.dataset.id;
        document.getElementById('ajusteLote').value = `${button.dataset.lote} / ${button.dataset.producto}`;
        disponible = Number(button.dataset.disponible);
        document.getElementById('ajusteDisponible').textContent = `Disponible libre de reservas: ${disponible.toFixed(3)}`;
        sector3 = button.dataset.sector.replace(/\s/g, '').toLowerCase() === 'sector3';
        document.getElementById('ajusteGuardar').disabled = false;
        actualizarLimite();
        validarObservacion();
        cargarSilos(conservar ? initialAssignments : []);
        dialog.showModal();
    };

    const buttons = Array.from(document.querySelectorAll('[data-ajustar-lote]'));
    buttons.forEach(button => button.addEventListener('click', () => abrir(button)));
    tipos.forEach(input => input.addEventListener('change', () => { actualizarLimite(); cargarSilos(); }));
    monto.addEventListener('input', actualizarDistribucion);
    observacion.addEventListener('input', validarObservacion);
    addSilo.addEventListener('click', () => {
        if (!addSilo.disabled) agregarFila();
    });
    document.getElementById('ajusteCancelar').addEventListener('click', () => dialog.close());
    document.getElementById('ajusteCerrar').addEventListener('click', () => dialog.close());
    const fueraDelDialog = event => {
        const rect = dialog.getBoundingClientRect();
        return event.clientX < rect.left || event.clientX > rect.right
            || event.clientY < rect.top || event.clientY > rect.bottom;
    };
    let inicioFuera = false;
    dialog.addEventListener('pointerdown', event => {
        inicioFuera = event.target === dialog && fueraDelDialog(event);
    });
    dialog.addEventListener('click', event => {
        if (inicioFuera && event.target === dialog && fueraDelDialog(event)) dialog.close();
        inicioFuera = false;
    });
    form.addEventListener('submit', event => {
        validarObservacion();
        actualizarDistribucion();
        if (!form.reportValidity()) {
            event.preventDefault();
            return;
        }
        document.getElementById('ajusteGuardar').disabled = true;
    });
    if (entrada.value) {
        const button = buttons.find(item => item.dataset.id === entrada.value);
        if (button) abrir(button, true);
    }
});
