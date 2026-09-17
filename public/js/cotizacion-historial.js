document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-cotizacion-history]');
    if (!page) {
        return;
    }

    const list = page.querySelector('[data-history-list]');
    const empty = page.querySelector('[data-history-empty]');
    const search = page.querySelector('[data-history-search]');
    const message = page.querySelector('[data-history-message]');
    const csrfToken = page.dataset.csrfToken;

    function normalize(value) {
        return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function showMessage(text, type) {
        message.textContent = text;
        message.className = `message message--${type}`;
        message.hidden = false;
    }

    async function sendJson(url, idCotizacion) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ idCotizacion }),
        });
        const data = await response.json().catch(() => ({ error: 'Respuesta inválida del servidor.' }));

        if (!response.ok || !data.success) {
            throw new Error(data.error || 'No se pudo completar la solicitud.');
        }

        return data;
    }

    function applySearch() {
        const query = normalize(search.value);
        let visible = 0;

        list.querySelectorAll('[data-history-item]').forEach((item) => {
            const matches = !query || normalize(item.dataset.search).includes(query);
            item.hidden = !matches;
            visible += matches ? 1 : 0;
        });

        empty.textContent = query ? 'No se encontraron cotizaciones.' : 'No hay cotizaciones activas registradas.';
        empty.hidden = visible > 0;
    }

    search.addEventListener('input', applySearch);

    list.addEventListener('click', async (event) => {
        const item = event.target.closest('[data-history-item]');
        const resendButton = event.target.closest('[data-resend-quotation]');
        const deleteButton = event.target.closest('[data-delete-quotation]');
        if (!item || (!resendButton && !deleteButton)) {
            return;
        }

        const idCotizacion = Number(item.dataset.id);
        const button = resendButton || deleteButton;

        if (deleteButton && !confirm('¿Deseas eliminar esta cotización del historial?')) {
            return;
        }

        button.disabled = true;
        try {
            const data = await sendJson(
                resendButton ? page.dataset.resendEndpoint : page.dataset.deleteEndpoint,
                idCotizacion
            );
            showMessage(data.mensaje, 'success');
            if (deleteButton) {
                item.remove();
                applySearch();
            }
        } catch (error) {
            showMessage(error.message, 'error');
        } finally {
            button.disabled = false;
        }
    });

    applySearch();
});