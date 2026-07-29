<?php

if (!function_exists('enviarAlertaTelegram')) {
    function enviarAlertaTelegram(string $mensaje): bool
    {
        try {
            if (!function_exists('curl_init')) {
                return false;
            }

            $token = '7990403555:AAFl-N7RrsjBrh8X1YT6rdFE-1uKzU_jJSY';
            $chatId = '-5499264065';
            $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';

            $curl = curl_init($url);
            if ($curl === false) {
                return false;
            }

            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'chat_id' => $chatId,
                    'text' => $mensaje,
                    'parse_mode' => 'Markdown',
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);

            $response = curl_exec($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_errno($curl);

            curl_close($curl);

            if ($response === false || $curlError !== 0 || $httpCode < 200 || $httpCode >= 300) {
                return false;
            }

            $payload = json_decode((string) $response, true);

            return is_array($payload) && !empty($payload['ok']);
        } catch (Throwable $exception) {
            return false;
        }
    }
}