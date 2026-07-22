document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select[data-product-search]').forEach(createSearchableSelect);
});

function createSearchableSelect(select) {
    if (select.dataset.searchReady === 'true') {
        return;
    }

    select.dataset.searchReady = 'true';
    const placeholder = select.dataset.searchPlaceholder || select.options[0]?.textContent || 'Seleccione una opcion';
    const wrapper = document.createElement('div');
    const input = document.createElement('input');
    const list = document.createElement('div');
    const status = document.createElement('span');
    let activeIndex = -1;

    wrapper.className = 'searchable-select';
    input.className = 'searchable-select-input';
    input.type = 'text';
    input.autocomplete = 'off';
    input.placeholder = placeholder;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-label', select.getAttribute('aria-label') || placeholder);
    list.className = 'searchable-select-list';
    list.setAttribute('role', 'listbox');
    status.className = 'searchable-select-status';
    status.setAttribute('aria-live', 'polite');

    select.classList.add('searchable-select-native');
    select.parentNode.insertBefore(wrapper, select.nextSibling);
    wrapper.appendChild(input);
    wrapper.appendChild(list);
    wrapper.appendChild(status);

    function normalize(value) {
        return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function getOptions() {
        return Array.from(select.options).filter((option) => option.value !== '');
    }

    function getSelectedText() {
        const selected = select.options[select.selectedIndex];
        return selected && selected.value ? selected.textContent.trim() : '';
    }

    function syncFromSelect() {
        input.value = getSelectedText();
        input.disabled = select.disabled;
        wrapper.classList.toggle('is-disabled', select.disabled);
    }

    function openList() {
        if (select.disabled) {
            return;
        }

        wrapper.classList.add('is-open');
        input.setAttribute('aria-expanded', 'true');
        renderOptions(input.value);
    }

    function closeList() {
        wrapper.classList.remove('is-open');
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
    }

    function renderOptions(query) {
        const normalizedQuery = normalize(query);
        const matches = getOptions().filter((option) => normalize(option.textContent).includes(normalizedQuery));
        list.innerHTML = '';
        activeIndex = matches.length ? 0 : -1;

        if (!matches.length) {
            const empty = document.createElement('div');
            empty.className = 'searchable-select-empty';
            empty.textContent = 'Sin resultados';
            list.appendChild(empty);
            status.textContent = 'Sin resultados';
            return;
        }

        matches.slice(0, 80).forEach((option, index) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'searchable-select-option';
            item.textContent = option.textContent.trim();
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', option.value === select.value ? 'true' : 'false');
            item.dataset.value = option.value;

            if (index === activeIndex) {
                item.classList.add('is-active');
            }

            item.addEventListener('mousedown', (event) => event.preventDefault());
            item.addEventListener('click', () => selectValue(option.value));
            list.appendChild(item);
        });

        status.textContent = `${matches.length} producto${matches.length === 1 ? '' : 's'} encontrado${matches.length === 1 ? '' : 's'}`;
    }

    function selectValue(value) {
        select.value = value;
        syncFromSelect();
        closeList();
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function moveActive(direction) {
        const options = Array.from(list.querySelectorAll('.searchable-select-option'));
        if (!options.length) {
            return;
        }

        activeIndex = (activeIndex + direction + options.length) % options.length;
        options.forEach((option, index) => {
            option.classList.toggle('is-active', index === activeIndex);
        });
        options[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    input.addEventListener('focus', () => {
        input.select();
        openList();
    });

    input.addEventListener('input', () => {
        openList();
        renderOptions(input.value);
        if (input.value.trim() === '') {
            select.value = '';
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            openList();
            moveActive(1);
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            openList();
            moveActive(-1);
        }

        if (event.key === 'Enter') {
            const active = list.querySelectorAll('.searchable-select-option')[activeIndex];
            if (active) {
                event.preventDefault();
                selectValue(active.dataset.value);
            }
        }

        if (event.key === 'Escape') {
            closeList();
            syncFromSelect();
        }
    });

    input.addEventListener('blur', () => {
        setTimeout(() => {
            closeList();
            syncFromSelect();
        }, 140);
    });

    select.addEventListener('change', syncFromSelect);

    const observer = new MutationObserver(syncFromSelect);
    observer.observe(select, { attributes: true, attributeFilter: ['disabled'] });
    syncFromSelect();
}