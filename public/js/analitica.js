function formatDateForDisplay(value) {
    if (!value) {
        return '';
    }

    const [year, month, day] = value.split('-');

    return `${day}/${month}/${year}`;
}

function formatDateForNative(value) {
    const match = value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);

    if (!match) {
        return '';
    }

    const [, day, month, year] = match;

    return `${year}-${month}-${day}`;
}

function openNativeCalendar(calendar) {
    if (!calendar) {
        return;
    }

    if (typeof calendar.showPicker === 'function') {
        calendar.showPicker();
        return;
    }

    calendar.focus();
    calendar.click();
}

document.querySelectorAll('[data-date-button]').forEach((button) => {
    button.addEventListener('click', () => {
        const calendar = document.getElementById(button.dataset.dateButton);

        openNativeCalendar(calendar);
    });
});

document.querySelectorAll('.native-date-input').forEach((calendar) => {
    calendar.addEventListener('change', () => {
        const target = document.getElementById(calendar.dataset.dateTarget);

        if (target) {
            target.value = formatDateForDisplay(calendar.value);
        }
    });
});

document.querySelectorAll('[data-date-display]').forEach((input) => {
    input.addEventListener('click', () => {
        openNativeCalendar(document.getElementById(input.dataset.dateDisplay));
    });

    input.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        openNativeCalendar(document.getElementById(input.dataset.dateDisplay));
    });

    input.addEventListener('input', () => {
        const calendar = document.getElementById(input.dataset.dateDisplay);
        const nativeValue = formatDateForNative(input.value);

        if (calendar && nativeValue) {
            calendar.value = nativeValue;
        }
    });
});
