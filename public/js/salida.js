const form = document.querySelector('.entry-form');
const sectorSelect = document.querySelector('#sector');
const productSelect = document.querySelector('#idProducto');
const lotSelect = document.querySelector('#idInventarioEntrante');
const neInput = document.querySelector('#NE');
const quantityInput = document.querySelector('#cantidadSaliente');
const saveButton = document.querySelector('#guardarSalida');
const lotMessage = document.querySelector('#lotMessage');
const quantityMessage = document.querySelector('#quantityMessage');

function getSelectedAvailable() {
    const selectedOption = lotSelect.options[lotSelect.selectedIndex];

    if (!selectedOption || !selectedOption.dataset.disponible) {
        return 0;
    }

    return Number(selectedOption.dataset.disponible);
}

function hasValidQuantity() {
    const quantity = Number(quantityInput.value);
    const available = getSelectedAvailable();

    return quantity > 0 && quantity <= available;
}

function updateSaveButton() {
    const quantity = Number(quantityInput.value);
    const available = getSelectedAvailable();

    if (quantityInput.value && quantity > available) {
        quantityMessage.textContent = `La cantidad no puede exceder el disponible del lote: ${available}.`;
    } else {
        quantityMessage.textContent = '';
    }

    saveButton.disabled = !(sectorSelect.value && lotSelect.value && neInput.value.trim() && hasValidQuantity());
}

function lockDeliveryFields(clearValues = false) {
    if (clearValues) {
        neInput.value = '';
        quantityInput.value = '';
        quantityMessage.textContent = '';
        quantityInput.removeAttribute('max');
    }

    neInput.disabled = true;
    quantityInput.disabled = true;
    saveButton.disabled = true;
}

function unlockDeliveryFields() {
    neInput.disabled = false;
    quantityInput.disabled = false;
    quantityInput.max = getSelectedAvailable();
    updateSaveButton();
}

function setLotMessage(text = '') {
    lotMessage.textContent = text;
}

function setLotPlaceholder(text, disabled = true) {
    lotSelect.innerHTML = '';
    const option = document.createElement('option');
    option.value = '';
    option.textContent = text;
    lotSelect.appendChild(option);
    lotSelect.disabled = disabled;
    lockDeliveryFields(true);
}

function formatLotOption(lote) {
    return `Lote ${lote.NumLote} - Disponible: ${lote.Disponible}`;
}

async function loadLots(productId) {
    setLotMessage();

    if (!productId) {
        setLotPlaceholder('Seleccione primero un producto');
        return;
    }

    setLotPlaceholder('Cargando lotes...', true);

    try {
        const response = await fetch(`${form.dataset.lotesUrl}?idProducto=${encodeURIComponent(productId)}`);
        const lots = await response.json();

        if (!response.ok || lots.error) {
            setLotPlaceholder('No se pudieron cargar los lotes');
            return;
        }

        if (lots.length === 0) {
            setLotPlaceholder('Sin lotes disponibles');
            setLotMessage('Este articulo no posee inventario fisico.');
            return;
        }

        lotSelect.innerHTML = '<option value="">Seleccione un lote</option>';
        lots.forEach((lote) => {
            const option = document.createElement('option');
            option.value = lote.idInventarioEntrante;
            option.dataset.disponible = lote.Disponible;
            option.textContent = formatLotOption(lote);
            lotSelect.appendChild(option);
        });
        lotSelect.disabled = false;
        updateSaveButton();
    } catch (error) {
        setLotPlaceholder('Error al cargar los lotes');
    }
}

if (form && sectorSelect && productSelect && lotSelect && neInput && quantityInput && saveButton && lotMessage && quantityMessage) {
    if (!lotSelect.value) {
        lockDeliveryFields();
    } else {
        unlockDeliveryFields();
    }

    sectorSelect.addEventListener('change', updateSaveButton);
    productSelect.addEventListener('change', () => loadLots(productSelect.value));
    lotSelect.addEventListener('change', () => {
        setLotMessage();

        if (lotSelect.value) {
            unlockDeliveryFields();
            neInput.focus();
            return;
        }

        lockDeliveryFields();
    });
    neInput.addEventListener('input', updateSaveButton);
    quantityInput.addEventListener('input', updateSaveButton);
}
