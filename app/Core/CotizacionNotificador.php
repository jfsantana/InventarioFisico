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
        $smtpFromName = 'Cotizaciones Adyar CA';

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
        $logoPath = $this->logoPath();
        if ($logoPath !== '') {
            $mail->addEmbeddedImage($logoPath, 'cotizacion-logo', 'logoAdyarcaCotizacion.png', PHPMailer::ENCODING_BASE64, 'image/png');
        }

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
        $mail->Body = $this->crearHtml($cotizacion, $esReenvio, $logoPath !== '' ? 'cid:cotizacion-logo' : '');
        $mail->AltBody = $this->crearTexto($cotizacion, $esReenvio);
        $mail->send();
    }

    private function crearHtml(array $cotizacion, bool $esReenvio, string $logoSrc = ''): string
    {
        $fechaEmision = new DateTimeImmutable((string) $cotizacion['fechaEmision']);
        $fechaVencimiento = $fechaEmision->modify('+' . (int) $cotizacion['diasVigencia'] . ' days');
        $fechaCreacion = new DateTimeImmutable((string) ($cotizacion['fechaCreacion'] ?? $cotizacion['fechaEmision']));
        $numeroCotizacion = $fechaCreacion->format('YmdH');
        $subtotal = (float) $cotizacion['subtotal'];
        $iva = round($subtotal * 0.16, 2);
        $total = round($subtotal + $iva, 2);
        $filas = '';

        foreach ($cotizacion['detalles'] as $detalle) {
            $filas .= '<tr>'
                . '<td style="padding:9px 7px;border-right:1px solid #b7b7b7;border-bottom:1px solid #b7b7b7;text-align:right;vertical-align:top;font-size:12px">' . $this->cantidad($detalle['cantidad']) . '</td>'
                . '<td style="padding:9px 7px;border-right:1px solid #b7b7b7;border-bottom:1px solid #b7b7b7;text-align:center;vertical-align:top;font-size:12px">' . $this->escapar($this->extraerUnidad((string) $detalle['presentacion'])) . '</td>'
                . '<td style="padding:9px 7px;border-right:1px solid #b7b7b7;border-bottom:1px solid #b7b7b7;vertical-align:top;font-size:12px">' . $this->escapar(mb_strtoupper((string) $detalle['producto'], 'UTF-8')) . '</td>'
                . '<td style="padding:9px 7px;border-right:1px solid #b7b7b7;border-bottom:1px solid #b7b7b7;vertical-align:top;font-size:12px">' . $this->escapar(mb_strtoupper((string) $detalle['presentacion'], 'UTF-8')) . '</td>'
                . '<td style="padding:9px 7px;border-right:1px solid #b7b7b7;border-bottom:1px solid #b7b7b7;text-align:right;vertical-align:top;font-size:12px">' . $this->numero($detalle['precioUnitario']) . '</td>'
                . '<td style="padding:9px 7px;border-bottom:1px solid #b7b7b7;text-align:right;vertical-align:top;font-size:12px;font-weight:bold">' . $this->numero($detalle['subtotal']) . '</td>'
                . '</tr>';
        }

        $observacion = trim((string) ($cotizacion['observacion'] ?? ''));
        $avisoReenvio = $esReenvio
            ? '<tr><td style="padding:0 0 18px"><div style="padding:10px 12px;background:#fff4d6;border-left:4px solid #e52b20;color:#7a1c2e;font-size:12px;font-weight:bold">Este correo es un reenvio de la cotizacion.</div></td></tr>'
            : '';

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"></head>'
            . '<body style="margin:0;padding:24px 12px;background:#f2f2f2;font-family:Arial,Helvetica,sans-serif;color:#2d333b">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:820px;background:#fff;padding:28px 34px">'
            . $avisoReenvio
            . '<tr><td><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>'
            . '<td style="width:46%;vertical-align:middle">' . ($logoSrc !== '' ? '<img src="' . $this->escapar($logoSrc) . '" alt="ADYAR" style="width:205px;height:auto;max-width:100%">' : '') . '</td>'
            . '<td style="text-align:right;vertical-align:middle"><div style="color:#801d35;font-size:22px;font-weight:bold;letter-spacing:.4px">ADYARCA INDUSTRIES C.A.</div><div style="margin-top:5px;color:#555;font-size:12px">RIF: J-29967374-9</div></td>'
            . '</tr></table><div style="height:4px;margin:18px 0;background:#801d35"></div></td></tr>'
            . '<tr><td><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td style="width:58%"></td>'
            . '<td style="width:42%;padding:12px 14px;border-left:4px solid #e52b20;background:#f7f7f7"><div><span style="color:#e52b20;font-size:14px;font-weight:bold">COTIZACION</span> <span style="font-size:16px;font-weight:bold">N° ' . $numeroCotizacion . '</span></div>'
            . '<div style="margin-top:5px;color:#555;font-size:11px"><strong>FECHA DE CREACION:</strong> ' . $fechaCreacion->format('d/m/Y') . '</div>'
            . '<div style="margin-top:4px;color:#555;font-size:11px"><strong>FECHA DE EXPIRACION:</strong> ' . $fechaVencimiento->format('d/m/Y') . '</div></td></tr></table></td></tr>'
            . '<tr><td style="padding:26px 2px 20px"><div style="font-size:16px;font-weight:bold">' . $this->escapar(mb_strtoupper((string) ($cotizacion['nombreCliente'] ?? ''), 'UTF-8')) . '</div>'
            . '<div style="margin-top:5px;color:#4f555b;font-size:12px"><strong>RIF:</strong> ' . $this->escapar($cotizacion['rifCliente'] ?? '') . '</div>'
            . (!empty($cotizacion['direccionCliente']) ? '<div style="margin-top:4px;color:#4f555b;font-size:12px">' . $this->escapar(mb_strtoupper((string) $cotizacion['direccionCliente'], 'UTF-8')) . '</div>' : '') . '</td></tr>'
            . '<tr><td><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1.5px solid #2d333b;border-collapse:collapse">'
            . '<thead><tr style="background:#858585;color:#fff"><th style="padding:10px 6px;font-size:10px">CANTIDAD</th><th style="padding:10px 6px;font-size:10px">UNIDAD</th><th style="padding:10px 6px;font-size:10px">DESCRIPCION</th><th style="padding:10px 6px;font-size:10px">PRESENTACION</th><th style="padding:10px 6px;font-size:10px">PRECIO</th><th style="padding:10px 6px;font-size:10px">TOTAL</th></tr></thead>'
            . '<tbody>' . $filas . '</tbody></table></td></tr>'
            . '<tr><td style="padding:24px 12px 12px"><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td style="width:58%;vertical-align:top;padding-right:24px">'
            . '<div style="display:inline-block;padding-bottom:3px;border-bottom:2px solid #801d35;color:#801d35;font-size:11px;font-weight:bold">CONDICIONES DE PAGO</div><div style="margin-top:6px;font-size:12px;font-weight:bold">' . $this->escapar(mb_strtoupper((string) ($cotizacion['condicionPago'] ?? ''), 'UTF-8')) . '</div>'
            . '<div style="margin-top:24px;display:inline-block;padding-bottom:3px;border-bottom:2px solid #801d35;color:#801d35;font-size:11px;font-weight:bold">NOTA</div><div style="margin-top:6px;font-size:12px;font-weight:bold">' . ($observacion !== '' ? nl2br($this->escapar(mb_strtoupper($observacion, 'UTF-8'))) : 'SIN OBSERVACIONES') . '</div>'
            . '</td><td style="width:42%;vertical-align:bottom"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:12px"><tr><td style="padding:5px 0;border-bottom:1px solid #d5d5d5">SUBTOTAL</td><td style="padding:5px 0;border-bottom:1px solid #d5d5d5;text-align:right;font-weight:bold">' . $this->numero($subtotal) . '</td></tr>'
            . '<tr><td style="padding:5px 0;border-bottom:1px solid #d5d5d5">IVA 16%</td><td style="padding:5px 0;border-bottom:1px solid #d5d5d5;text-align:right;font-weight:bold">' . $this->numero($iva) . '</td></tr>'
            . '<tr><td style="padding:9px 0 5px;border-top:2px solid #801d35;color:#801d35;font-size:14px;font-weight:bold">TOTAL</td><td style="padding:9px 0 5px;border-top:2px solid #801d35;color:#801d35;text-align:right;font-size:14px;font-weight:bold">' . $this->numero($total) . '</td></tr></table></td></tr></table></td></tr>'
            . '<tr><td><div style="height:3px;background:#801d35"></div><div style="margin-top:7px;color:#777;font-size:10px;text-align:center">ADYARCA INDUSTRIES C.A. · RIF J-29967374-9</div></td></tr>'
            . '</table></td></tr></table></body></html>';
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

    private function logoPath(): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'logoAdyarcaCotizacion.png';
        if (!is_readable($path) || !is_file($path)) {
            return '';
        }

        return $path;
    }

    private function extraerUnidad(string $presentacion): string
    {
        if (preg_match('/\((?:[0-9]+(?:[\.,][0-9]+)?\s*)?([^\d()]+)\)/u', $presentacion, $matches)) {
            $unidad = trim($matches[1]);
            if ($unidad !== '') {
                return mb_strtoupper($unidad, 'UTF-8');
            }
        }

        return 'UND';
    }

    private function numero(mixed $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }

    private function cantidad(mixed $valor): string
    {
        $cantidad = (float) $valor;

        return fmod($cantidad, 1.0) === 0.0
            ? number_format($cantidad, 0, ',', '.')
            : number_format($cantidad, 2, ',', '.');
    }
}