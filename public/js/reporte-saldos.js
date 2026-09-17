document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-lot-balance-filter]');
    const dataElement = document.querySelector('#lot-balance-products');
    if (!form || !dataElement) return;

    const products = JSON.parse(dataElement.textContent || '[]');
    const search = form.querySelector('[data-product-filter]');
    const results = form.querySelector('[data-product-results]');
    const selectedContainer = form.querySelector('[data-selected-products]');
    const summary = form.querySelector('[data-selection-summary]');
    const clearButton = form.querySelector('[data-clear-products]');
    const normalize = (value) => String(value || '').toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const selectedIds = () => new Set(Array.from(selectedContainer.querySelectorAll('[data-selected-id]'), (chip) => Number(chip.dataset.selectedId)));

    function updateSummary() {
        const count = selectedIds().size;
        summary.textContent = count ? `${count} ${count === 1 ? 'seleccionado' : 'seleccionados'}` : 'Todos los productos';
    }

    function closeResults() {
        results.hidden = true;
        search.setAttribute('aria-expanded', 'false');
    }

    function addProduct(product) {
        if (selectedIds().has(product.id)) return;
        const chip = document.createElement('span');
        chip.className = 'lot-product-chip';
        chip.dataset.selectedId = String(product.id);

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'idProducto[]';
        hidden.value = String(product.id);

        const name = document.createElement('span');
        name.textContent = product.nombre;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.dataset.removeProduct = '';
        remove.setAttribute('aria-label', `Quitar ${product.nombre}`);
        remove.innerHTML = '&times;';

        chip.append(hidden, name, remove);
        selectedContainer.appendChild(chip);
        search.value = '';
        updateSummary();
        renderResults('');
        search.focus();
    }

    function renderResults(term) {
        const query = normalize(term.trim());
        const selected = selectedIds();
        const matches = products
            .filter((product) => !selected.has(product.id) && (!query || normalize(product.nombre).includes(query)))
            .slice(0, 10);

        results.replaceChildren();
        if (matches.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'lot-product-empty';
            empty.textContent = query ? 'No hay productos con ese criterio.' : 'Todos los productos coincidentes están seleccionados.';
            results.appendChild(empty);
        } else {
            matches.forEach((product) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'lot-product-result';
                option.setAttribute('role', 'option');
                option.textContent = product.nombre;
                option.addEventListener('click', () => addProduct(product));
                results.appendChild(option);
            });
        }
        results.hidden = false;
        search.setAttribute('aria-expanded', 'true');
    }

    search.addEventListener('focus', () => renderResults(search.value));
    search.addEventListener('input', () => renderResults(search.value));
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeResults();
        if (event.key === 'Enter' && !results.hidden) {
            const firstResult = results.querySelector('.lot-product-result');
            if (firstResult) {
                event.preventDefault();
                firstResult.click();
            }
        }
    });
    selectedContainer.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-product]');
        if (!remove) return;
        remove.closest('[data-selected-id]').remove();
        updateSummary();
    });
    clearButton.addEventListener('click', () => {
        selectedContainer.replaceChildren();
        updateSummary();
        search.focus();
        renderResults('');
    });
    document.addEventListener('click', (event) => {
        if (!form.querySelector('.lot-product-combobox').contains(event.target)) closeResults();
    });
});