<?php

require_once __DIR__ . '/../config_ia.php';
require_once __DIR__ . '/../Model/IaModel.php';

class IaController
{
    private function llamarIA(string $contexto, string $pregunta): string
    {
        if (IA_API_KEY === '') {
            return 'Error: la clave de OpenRouter no está configurada en ia/config_ia.php.';
        }

        if (!function_exists('curl_init')) {
            return 'Error: la extensión cURL de PHP no está disponible en el servidor.';
        }

        $caBundle = __DIR__ . '/../certs/cacert.pem';
        if (!is_readable($caBundle)) {
            return 'Error: no se encontró el certificado de autoridades confiables para conectar con OpenRouter.';
        }

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . IA_API_KEY,
            'HTTP-Referer: http://localhost',
            'X-Title: Inventario IA',
        ];

        $messages = [
            [
                'role' => 'system',
                'content' => 'Eres un asistente experto en gestión de inventario. '
                    . 'Responde ÚNICAMENTE basándote en los datos reales que te proporciono. '
                    . 'Si la pregunta no puede responderse con estos datos, indícalo claramente. '
                    . 'Responde siempre en español, de forma clara y concisa.',
            ],
            [
                'role' => 'user',
                'content' => "DATOS ACTUALES DEL INVENTARIO:\n\n"
                    . $contexto
                    . "\n\nPREGUNTA:\n"
                    . $pregunta,
            ],
        ];

        try {
            $payload = json_encode([
                'model' => IA_MODEL,
                'messages' => $messages,
                'temperature' => 0.2,
                'max_tokens' => 1024,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return 'Error al preparar la solicitud para OpenRouter: ' . $exception->getMessage();
        }

        $curl = curl_init(IA_API_URL);

        if ($curl === false) {
            return 'Error: no se pudo inicializar la conexión con OpenRouter.';
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => IA_TIMEOUT_SEGUNDOS,
            CURLOPT_CAINFO => $caBundle,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $respuestaHttp = curl_exec($curl);
        $errorCurl = curl_error($curl);
        $codigoHttp = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($respuestaHttp === false) {
            return 'Error de conexión: ' . ($errorCurl !== '' ? $errorCurl : 'respuesta vacía.');
        }

        try {
            $resultado = json_decode($respuestaHttp, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return 'Error: OpenRouter devolvió una respuesta JSON inválida.';
        }

        if ($codigoHttp < 200 || $codigoHttp >= 300) {
            $mensajeApi = $resultado['error']['message'] ?? 'OpenRouter rechazó la solicitud.';
            return 'Error de IA (' . $codigoHttp . '): ' . $mensajeApi;
        }

        $texto = $resultado['choices'][0]['message']['content'] ?? null;
        if (!is_string($texto) || trim($texto) === '') {
            return 'Error: no se pudo obtener una respuesta válida de OpenRouter. Intenta de nuevo.';
        }

        return trim($texto);
    }

    public function procesarPregunta(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->responder(false, '', 'Método no permitido. Debe enviar una solicitud POST.', 405);
        }

        if (!isset($_POST['pregunta'])) {
            $this->responder(false, '', 'Debe indicar una pregunta.', 422);
        }

        $pregunta = trim(htmlspecialchars(
            (string) $_POST['pregunta'],
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ));

        if ($pregunta === '') {
            $this->responder(false, '', 'La pregunta no puede estar vacía.', 422);
        }

        if (mb_strlen($pregunta, 'UTF-8') > IA_MAX_CHARS_PREGUNTA) {
            $this->responder(
                false,
                '',
                'La pregunta no puede superar los ' . IA_MAX_CHARS_PREGUNTA . ' caracteres.',
                422
            );
        }

        try {
            $model = new IaModel();
            $contexto = $model->generarContexto();
            $respuesta = $this->llamarIA($contexto, $pregunta);

            if (str_starts_with($respuesta, 'Error:') || str_starts_with($respuesta, 'Error de')) {
                $this->responder(false, '', $respuesta, 502);
            }

            $this->responder(true, $respuesta, '');
        } catch (Throwable $exception) {
            $this->responder(false, '', 'No se pudo procesar la consulta: ' . $exception->getMessage(), 500);
        }
    }

    private function responder(bool $success, string $respuesta, string $error, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        echo json_encode([
            'success' => $success,
            'respuesta' => $respuesta,
            'error' => $error,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
