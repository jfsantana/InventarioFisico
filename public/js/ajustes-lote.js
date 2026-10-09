document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('ajusteDialog');
    const form = document.getElementById('ajusteForm');
    if (!dialog || !form) return;

    const entrada = document.getElementById('ajusteEntrada');
    const tipos = Array.from(form.querySelectorAll('input[name="tipo"]'));
    const monto = document.getElementById('ajusteMonto');
    const silo = document.getElementById('ajusteSilo');
    let disponible = 0;

    const actualizarLimite = () => {
        const tipo = tipos.find(input => input.checked);
        monto.max = tipo && tipo.value === 'negativo' ? String(Math.max(0, disponible)) : '99999999999.999';
    };
    const abrir = (button, conservar = false) => {
        if (!conservar) {
            form.reset();
            tipos.forEach(input => { input.checked = false; });
            monto.value = '';
            silo.value = '';
            document.getElementById('ajusteObservacion').value = '';
        }
        entrada.value = button.dataset.id;
        document.getElementById('ajusteLote').value = `${button.dataset.lote} / ${button.dataset.producto}`;
        disponible = Number(button.dataset.disponible);
        document.getElementById('ajusteDisponible').textContent = `Disponible libre de reservas: ${disponible.toFixed(3)}`;
        const sector3 = button.dataset.sector.replace(/\s/g, '').toLowerCase() === 'sector3';
        document.getElementById('ajusteSiloCampo').hidden = !sector3;
        silo.required = sector3;
        silo.disabled = !sector3;
        if (!sector3) silo.value = '';
        document.getElementById('ajusteGuardar').disabled = false;
        actualizarLimite();
        dialog.showModal();
    };

    const buttons = Array.from(document.querySelectorAll('[data-ajustar-lote]'));
    buttons.forEach(button => button.addEventListener('click', () => abrir(button)));
    tipos.forEach(input => input.addEventListener('change', actualizarLimite));
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
    form.addEventListener('submit', () => {
        document.getElementById('ajusteGuardar').disabled = true;
    });
    if (entrada.value) {
        const button = buttons.find(item => item.dataset.id === entrada.value);
        if (button) abrir(button, true);
    }
});
