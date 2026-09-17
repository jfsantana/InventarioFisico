<?php

use PHPMailer\PHPMailer\PHPMailer;

class CotizacionNotificador
{
    public function enviar(array $cotizacion, array $contactosInternos = [], bool $esReenvio = false): void
    {
        $emailCliente = (string) ($cotizacion['emailCliente'] ?? '');
        if (filter_var($emailCliente, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('El cliente no tiene un email valido.');
        }

        if (empty($cotizacion['detalles'])) {
            throw new InvalidArgumentException('La cotizacion no contiene detalles.');
        }

        $smtpLocalFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'smtp.local.php';
        $smtpLocal = is_file($smtpLocalFile) ? require $smtpLocalFile : [];
        $smtpHost = defined('SMTP_HOST') ? SMTP_HOST : (string) ($smtpLocal['host'] ?? '');
        $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : (int) ($smtpLocal['port'] ?? 465);
        $smtpEncryption = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : (string) ($smtpLocal['encryption'] ?? 'ssl');
        $smtpUsername = defined('SMTP_USERNAME') ? SMTP_USERNAME : (string) ($smtpLocal['username'] ?? '');
        $smtpPassword = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : (string) ($smtpLocal['password'] ?? '');
        $smtpFromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : (string) ($smtpLocal['from_email'] ?? $smtpUsername);
        $smtpFromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (string) ($smtpLocal['from_name'] ?? 'Inventario Fisico');

        if ($smtpHost === '' || $smtpUsername === '' || $smtpPassword === '') {
            throw new RuntimeException('La configuracion SMTP esta incompleta.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = $smtpPort;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = $smtpEncryption === 'tls'
            ? PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($smtpFromEmail, $smtpFromName);
        $mail->addAddress($emailCliente, (string) ($cotizacion['nombreCliente'] ?? 'Cliente'));

        $emailsAgregados = [strtolower($emailCliente) => true];
        foreach ($contactosInternos as $contacto) {
            $email = trim((string) ($contacto['email'] ?? ''));
            $emailNormalizado = strtolower($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || isset($emailsAgregados[$emailNormalizado])) {
                continue;
            }

            $mail->addBCC($email, (string) ($contacto['nombre'] ?? ''));
            $emailsAgregados[$emailNormalizado] = true;
        }

        $prefijo = $esReenvio ? '[REENVIO] ' : '';
        $fechaNumero = new DateTimeImmutable((string) ($cotizacion['fechaCreacion'] ?? $cotizacion['fechaEmision']));
        $mail->Subject = $prefijo . 'Cotizacion N° ' . $fechaNumero->format('YmdH');
        $mail->isHTML(true);
        $mail->Body = $this->crearHtml($cotizacion, $esReenvio);
        $mail->AltBody = $this->crearTexto($cotizacion, $esReenvio);
        $mail->send();
    }

    private function crearHtml(array $cotizacion, bool $esReenvio): string
    {
        $subtotal = (float) $cotizacion['subtotal'];
        $iva = round($subtotal * 0.16, 2);
        $filas = '';
        foreach ($cotizacion['detalles'] as $detalle) {
            $filas .= '<tr>'
                . '<td style="padding:8px;border:1px solid #ddd">' . $this->escapar($detalle['producto']) . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd">' . $this->escapar($detalle['presentacion']) . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:right">' . $this->numero($detalle['cantidad']) . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:right">' . $this->numero($detalle['precioUnitario']) . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:right">' . $this->numero($detalle['subtotal']) . '</td>'
                . '</tr>';
        }

        $avisoReenvio = $esReenvio ? '<p><strong>Este correo es un reenvio de la cotizacion.</strong></p>' : '';

        return '<div style="font-family:Arial,sans-serif;color:#222;max-width:760px;margin:auto">'
            . $avisoReenvio
            . '<h1 style="font-size:24px">Cotizacion #' . (int) $cotizacion['idCotizacion'] . '</h1>'
            . '<p><strong>Cliente:</strong> ' . $this->escapar($cotizacion['nombreCliente']) . '</p>'
            . '<p><strong>Vigencia:</strong> ' . (int) $cotizacion['diasVigencia'] . ' dia(s)</p>'
            . '<table style="width:100%;border-collapse:collapse">'
            . '<thead><tr><th style="padding:8px;border:1px solid #ddd">Producto</th><th style="padding:8px;border:1px solid #ddd">Presentacion</th><th style="padding:8px;border:1px solid #ddd">Cantidad</th><th style="padding:8px;border:1px solid #ddd">Precio</th><th style="padding:8px;border:1px solid #ddd">Subtotal</th></tr></thead>'
            . '<tbody>' . $filas . '</tbody></table>'
            . '<p style="text-align:right;margin-bottom:4px"><strong>Subtotal: ' . $this->numero($subtotal) . '</strong></p>'
            . '<p style="text-align:right;margin:4px 0"><strong>IVA 16%: ' . $this->numero($iva) . '</strong></p>'
            . '<p style="text-align:right"><strong>Total: ' . $this->numero($cotizacion['total']) . '</strong></p>'
            . '<hr style="border:0;border-top:1px solid #ddd;margin:22px 0">'
            . '<p><strong>Condicion de pago:</strong> ' . $this->escapar($cotizacion['condicionPago']) . '</p>'
            . (!empty($cotizacion['observacion']) ? '<p><strong>Observacion:</strong> ' . nl2br($this->escapar($cotizacion['observacion'])) . '</p>' : '')
            . '</div>';
    }

    private function crearTexto(array $cotizacion, bool $esReenvio): string
    {
        $subtotal = (float) $cotizacion['subtotal'];
        $iva = round($subtotal * 0.16, 2);
        $lineas = [
            ($esReenvio ? 'REENVIO - ' : '') . 'Cotizacion #' . (int) $cotizacion['idCotizacion'],
            'Cliente: ' . ($cotizacion['nombreCliente'] ?? ''),
            '',
        ];

        foreach ($cotizacion['detalles'] as $detalle) {
            $lineas[] = ($detalle['producto'] ?? '') . ' | ' . ($detalle['presentacion'] ?? '')
                . ' | ' . $this->numero($detalle['cantidad']) . ' x ' . $this->numero($detalle['precioUnitario'])
                . ' = ' . $this->numero($detalle['subtotal']);
        }

        $lineas[] = '';
        $lineas[] = 'Subtotal: ' . $this->numero($subtotal);
        $lineas[] = 'IVA 16%: ' . $this->numero($iva);
        $lineas[] = 'Total: ' . $this->numero($cotizacion['total']);
        $lineas[] = 'Condicion de pago: ' . ($cotizacion['condicionPago'] ?? '');

        if (!empty($cotizacion['observacion'])) {
            $lineas[] = 'Observacion: ' . $cotizacion['observacion'];
        }

        return implode(PHP_EOL, $lineas);
    }

    private function escapar(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    private function numero(mixed $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }
}