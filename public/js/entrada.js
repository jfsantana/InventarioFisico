const entradaForm = document.querySelector('[data-entrada-form]');

if (entradaForm) {
    const requiredFields = [
        entradaForm.elements.idProducto,
        entradaForm.elements.NumLote,
        entradaForm.elements.idPresentacion,
        entradaForm.elements.idUbicacion,
        entradaForm.elements.Sector,
        entradaForm.elements.CantidadEntrante,
        entradaForm.elements.idTipoCompra,
        entradaForm.elements.CardCode,
        entradaForm.elements.FabricanteCode,
        entradaForm.elements.PaisCode,
    ];
    const submitButton = document.getElementById('guardarEntrada');
    const message = document.getElementById('entradaFormMessage');

    function isPositiveInteger(value) {
        return /^\d+$/.test(value) && Number(value) > 0;
    }

    function isFormComplete() {
        return requiredFields.every((field) => {
            if (!field) {
                return false;
            }

            if (field.name === 'CantidadEntrante') {
                return isPositiveInteger(field.value.trim());
            }

            return field.value.trim() !== '';
        });
    }

    function updateSubmitState() {
        const formComplete = isFormComplete();

        submitButton.disabled = !formComplete;
        message.textContent = formComplete ? '' : 'Complete todos los campos obligatorios para guardar la entrada.';
    }

    requiredFields.forEach((field) => {
        if (!field) {
            return;
        }

        field.addEventListener('input', updateSubmitState);
        field.addEventListener('change', updateSubmitState);
    });

    entradaForm.addEventListener('submit', (event) => {
        updateSubmitState();

        if (!isFormComplete()) {
            event.preventDefault();
        }
    });

    updateSubmitState();
}