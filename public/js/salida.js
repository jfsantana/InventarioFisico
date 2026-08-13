function cargarProducto(id, nombre, lote, disponible) {
    const producto = document.getElementById('txt_producto');
    const loteInput = document.getElementById('txt_lote');
    const disponibleInput = document.getElementById('txt_disponible');
    const cantidadInput = document.getElementById('txt_cantidad');
    const inventarioInput = document.getElementById('hid_inventarioId');
    const submitButton = document.querySelector('#form-entrega button[type="submit"]');
    const seleccionMensaje = document.getElementById('mensaje-seleccion');

    if (!producto || !loteInput || !disponibleInput || !cantidadInput || !inventarioInput || !submitButton) {
        return;
    }

    producto.value = nombre;
    loteInput.value = lote;
    disponibleInput.value = Number(disponible || 0).toFixed(2);
    cantidadInput.value = '';
    cantidadInput.max = disponible;
    cantidadInput.disabled = false;
    inventarioInput.value = id;
    submitButton.disabled = Number(disponible) <= 0;

    if (seleccionMensaje) {
        seleccionMensaje.hidden = true;
    }

    document.querySelectorAll('.fila-producto').forEach((fila) => fila.classList.remove('activa', 'is-selected-row'));
    const activeRow = document.getElementById(`fila-${id}`);
    if (activeRow) {
        activeRow.classList.add('activa', 'is-selected-row');
    }

    cantidadInput.focus();
}

window.cargarProducto = cargarProducto;

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-entrega');
    if (!form) {
        return;
    }

    const messageBox = document.querySelector('[data-salida-message]');
    const errorBox = document.querySelector('[data-salida-error]');

    function showMessage(text, isError = false) {
        const target = isError ? errorBox : messageBox;
        const other = isError ? messageBox : errorBox;
        if (!target || !other) {
            if (isError) {
                window.alert(text);
            }
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

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearMessages();

        const cantidadInput = document.getElementById('txt_cantidad');
        const disponibleInput = document.getElementById('txt_disponible');
        const inventarioInput = document.getElementById('hid_inventarioId');
        const cantidad = Number(cantidadInput.value || 0);
        const disponible = Number(disponibleInput.value || 0);

        if (!inventarioInput.value) {
            showMessage('Haz clic en un producto de la lista.', true);
            return;
        }

        if (!cantidad || cantidad <= 0) {
            showMessage('Ingresa una cantidad válida.', true);
            cantidadInput.focus();
            return;
        }

        if (cantidad > disponible) {
            showMessage(`La cantidad supera lo disponible (${disponible.toFixed(2)}).`, true);
            cantidadInput.focus();
            return;
        }

        const button = form.querySelector('button[type="submit"]');
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Guardando...';

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form)
        })
            .then((response) => response.json().then((data) => {
                if (!response.ok || data.success === false) {
                    throw new Error(data.error || data.mensaje || 'No se pudo registrar la entrega.');
                }
                return data;
            }))
            .then((data) => {
                if (data.predespacho_embarcado) {
                    showMessage(data.mensaje || 'Predespacho embarcado correctamente.');

                    const deliverySection = document.querySelector('[data-delivery-section]');
                    if (deliverySection) {
                        deliverySection.remove();
                    }

                    const url = new URL(window.location.href);
                    url.searchParams.delete('predespacho');
                    url.searchParams.set('embarcado', '1');
                    window.location.href = url.href;
                    return;
                }

                const url = new URL(window.location.href);
                window.location.href = url.href;
            })
            .catch((error) => {
                showMessage(error.message, true);
                button.disabled = false;
                button.textContent = originalText;
            });
    });
});
