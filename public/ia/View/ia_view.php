<?php

$esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocolo = $esHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$rutaScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/public/ia/View/ia_view.php');
$rutaProyecto = preg_replace('#/(?:public/)?ia/View/ia_view\.php$#', '', $rutaScript) ?: '';
putenv('APP_URL=' . $protocolo . '://' . $host . rtrim($rutaProyecto, '/'));

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../app/Core/Database.php';
require_once __DIR__ . '/../../../app/Core/Auth.php';

Auth::boot();
Auth::requireLogin();

$title = 'Asistente IA de Inventario';
$apiUrl = rtrim($rutaProyecto, '/') . '/ia/ia_api.php';

require_once __DIR__ . '/../../../app/Views/layouts/header.php';
?>

<style>
.ia-assistant {
    max-width: 1050px;
    margin: 0 auto;
}

.ia-assistant .ia-heading {
    margin-bottom: 28px;
}

.ia-assistant .ia-heading h1 {
    margin-bottom: 8px;
}

.ia-quick-questions {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin: 18px 0 30px;
}

.ia-quick-button {
    min-height: 58px;
    padding: 12px 14px;
    border: 1px solid #b9c7c0;
    border-radius: 7px;
    background: #f7faf8;
    color: #18362b;
    font: inherit;
    font-weight: 700;
    text-align: left;
    cursor: pointer;
}

.ia-quick-button:hover,
.ia-quick-button:focus-visible {
    border-color: #267253;
    background: #edf6f1;
    outline: none;
}

.ia-form-card,
.ia-response-card {
    padding: 22px;
    border: 1px solid #d3ddd8;
    border-radius: 8px;
    background: #fff;
}

.ia-form-card label {
    display: block;
    margin-bottom: 8px;
    color: #18362b;
    font-weight: 700;
}

.ia-question-input {
    width: 100%;
    min-height: 130px;
    padding: 14px;
    resize: vertical;
    border: 1px solid #aebdb6;
    border-radius: 6px;
    color: #17221e;
    font: inherit;
    line-height: 1.5;
}

.ia-question-input:focus {
    border-color: #267253;
    outline: 3px solid rgba(38, 114, 83, 0.14);
}

.ia-form-meta,
.ia-response-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.ia-form-meta {
    margin: 8px 0 18px;
    color: #5d6d66;
    font-size: 0.92rem;
}

.ia-form-actions {
    display: flex;
    align-items: center;
    gap: 14px;
}

.ia-loading {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    color: #40544b;
    font-weight: 700;
}

.ia-loading[hidden] {
    display: none;
}

.ia-spinner {
    width: 20px;
    height: 20px;
    border: 3px solid #c8d7d0;
    border-top-color: #267253;
    border-radius: 50%;
    animation: ia-spin 0.8s linear infinite;
}

.ia-response-card {
    margin-top: 22px;
}

.ia-response-heading h2 {
    margin: 0;
}

.ia-response-text {
    min-height: 100px;
    margin: 20px 0 12px;
    padding: 18px;
    border-left: 4px solid #267253;
    background: #f5f8f6;
    color: #17221e;
    line-height: 1.65;
    white-space: pre-wrap;
}

.ia-response-text.is-error {
    border-left-color: #b53838;
    background: #fff4f4;
    color: #7f2020;
}

.ia-last-query {
    margin: 0;
    color: #66766f;
    font-size: 0.9rem;
}

@keyframes ia-spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 760px) {
    .ia-quick-questions {
        grid-template-columns: 1fr;
    }

    .ia-form-meta,
    .ia-response-heading,
    .ia-form-actions {
        align-items: stretch;
        flex-direction: column;
    }
}
</style>

<section class="panel report-panel ia-assistant" data-ia-assistant data-api-url="<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?>">
    <header class="ia-heading">
        <p class="eyebrow">Consulta inteligente</p>
        <h1>Asistente IA de Inventario</h1>
        <p class="intro">Consulta el estado del inventario en lenguaje natural</p>
    </header>

    <section aria-labelledby="ia-quick-title">
        <h2 id="ia-quick-title">Preguntas rápidas</h2>
        <div class="ia-quick-questions">
            <?php
            $preguntasRapidas = [
                '¿Qué productos están agotados?',
                '¿Qué lotes llevan más tiempo sin movimiento?',
                '¿Hay predespachos vencidos?',
                '¿Cuál es el resumen general del inventario?',
                '¿Qué productos tienen stock crítico?',
                '¿Qué alertas son más urgentes hoy?',
            ];
            ?>
            <?php foreach ($preguntasRapidas as $preguntaRapida) : ?>
                <button class="ia-quick-button" type="button" data-quick-question="<?= htmlspecialchars($preguntaRapida, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($preguntaRapida, ENT_QUOTES, 'UTF-8') ?>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <form class="ia-form-card" data-ia-form novalidate>
        <label for="iaPregunta">Pregunta sobre el inventario</label>
        <textarea
            class="ia-question-input"
            id="iaPregunta"
            name="pregunta"
            maxlength="500"
            placeholder="Escribe tu consulta sobre el inventario..."
            required
            data-question-input
        ></textarea>
        <div class="ia-form-meta">
            <span>La respuesta se genera únicamente con los datos disponibles.</span>
            <span><strong data-characters-left>500</strong> caracteres restantes</span>
        </div>
        <div class="ia-form-actions">
            <button class="button-link button-link--submit" type="submit" data-submit-button>Consultar IA</button>
            <div class="ia-loading" role="status" data-loading hidden>
                <span class="ia-spinner" aria-hidden="true"></span>
                <span>Consultando inventario...</span>
            </div>
        </div>
    </form>

    <section class="ia-response-card" aria-labelledby="ia-response-title">
        <div class="ia-response-heading">
            <h2 id="ia-response-title">Respuesta</h2>
            <button class="button-link button-link--secondary" type="button" data-clear-button>Limpiar</button>
        </div>
        <div class="ia-response-text" role="status" aria-live="polite" data-response-text>La respuesta aparecerá aquí.</div>
        <p class="ia-last-query" data-last-query>Sin consultas realizadas.</p>
    </section>
</section>

<script>
(function () {
    'use strict';

    const assistant = document.querySelector('[data-ia-assistant]');
    if (!assistant) {
        return;
    }

    const form = assistant.querySelector('[data-ia-form]');
    const questionInput = assistant.querySelector('[data-question-input]');
    const charactersLeft = assistant.querySelector('[data-characters-left]');
    const loading = assistant.querySelector('[data-loading]');
    const submitButton = assistant.querySelector('[data-submit-button]');
    const responseText = assistant.querySelector('[data-response-text]');
    const lastQuery = assistant.querySelector('[data-last-query]');
    const clearButton = assistant.querySelector('[data-clear-button]');
    const maxCharacters = Number(questionInput.maxLength);

    function updateCounter() {
        charactersLeft.textContent = String(Math.max(0, maxCharacters - questionInput.value.length));
    }

    function setLoading(isLoading) {
        loading.hidden = !isLoading;
        submitButton.disabled = isLoading;
        questionInput.disabled = isLoading;
    }

    function showResponse(message, isError) {
        responseText.textContent = message;
        responseText.classList.toggle('is-error', isError);
        lastQuery.textContent = 'Última consulta: ' + new Intl.DateTimeFormat('es', {
            dateStyle: 'short',
            timeStyle: 'medium'
        }).format(new Date());
    }

    async function submitQuestion(event) {
        event.preventDefault();
        const question = questionInput.value.trim();

        if (question === '') {
            showResponse('Escribe una pregunta antes de consultar.', true);
            questionInput.focus();
            return;
        }

        setLoading(true);
        responseText.classList.remove('is-error');
        responseText.textContent = 'Procesando la consulta...';

        try {
            const body = new URLSearchParams({ pregunta: question });
            const response = await fetch(assistant.dataset.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'No se pudo obtener una respuesta del asistente.');
            }

            showResponse(data.respuesta, false);
        } catch (error) {
            const message = error instanceof Error
                ? error.message
                : 'Ocurrió un error de red al consultar el asistente.';
            showResponse(message, true);
        } finally {
            setLoading(false);
        }
    }

    form.addEventListener('submit', submitQuestion);
    questionInput.addEventListener('input', updateCounter);

    assistant.querySelectorAll('[data-quick-question]').forEach(function (button) {
        button.addEventListener('click', function () {
            questionInput.value = button.dataset.quickQuestion || '';
            updateCounter();
            form.requestSubmit();
        });
    });

    clearButton.addEventListener('click', function () {
        form.reset();
        updateCounter();
        responseText.classList.remove('is-error');
        responseText.textContent = 'La respuesta aparecerá aquí.';
        lastQuery.textContent = 'Sin consultas realizadas.';
        questionInput.focus();
    });

    updateCounter();
}());
</script>

<?php require_once __DIR__ . '/../../../app/Views/layouts/footer.php'; ?>
