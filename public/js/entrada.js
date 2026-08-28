const entradaForm = document.querySelector('[data-entrada-form]');

if (entradaForm) {
    const maxDocumentBytes = 10 * 1024 * 1024;
    const fileFields = [
        entradaForm.elements.ticketRomana,
        entradaForm.elements.facturaProveedor,
        entradaForm.elements.documentoSeniat,
    ];
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

    function documentsWithinLimit() {
        const totalBytes = fileFields.reduce((total, field) => total + (field.files[0]?.size || 0), 0);
        return totalBytes <= maxDocumentBytes;
    }

    function updateSubmitState() {
        const formComplete = isFormComplete() && documentsWithinLimit();

        submitButton.disabled = !formComplete;
        message.textContent = !documentsWithinLimit()
            ? 'Los tres documentos no pueden superar 10 MB en total.'
            : (formComplete ? '' : 'Complete todos los campos obligatorios para guardar la entrada.');
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

    updateSubmitState();
}