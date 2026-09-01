<?php

use PHPMailer\PHPMailer\PHPMailer;

class EntradaNotificador
{
    public function enviar(array $entrada, array $destinatarios, array $documentos, string $storageRoot, string $evento): void
    {
        if (empty($destinatarios)) {
            throw new RuntimeException('No hay contactos configurados para el proceso Entrada.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'tls'
            ? PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        foreach ($destinatarios as $destinatario) {
            $mail->addAddress($destinatario['email'], $destinatario['nombre']);
        }

        $producto = (string) ($entrada['producto'] ?? 'Materia prima');
        $eventoConfig = match ($evento) {
            'edicion' => ['asunto' => '[ACTUALIZACIÓN] ', 'label' => 'ENTRADA ACTUALIZADA', 'color' => '#9a5b00', 'fondo' => '#fff4d6'],
            'reenvio' => ['asunto' => '[REENVÍO] ', 'label' => 'CORREO REENVIADO', 'color' => '#245b78', 'fondo' => '#e8f3f8'],
            default => ['asunto' => '[CREACIÓN] ', 'label' => 'NUEVA ENTRADA', 'color' => '#17643a', 'fondo' => '#e8f6ee'],
        };
        $mail->Subject = $eventoConfig['asunto'] . 'RECEPCION DE MATERIA PRIMA: ' . $producto;
        $mail->isHTML(true);
        $mail->Body = $this->crearHtml($entrada, $eventoConfig);
        $mail->AltBody = $this->crearTexto($entrada, $eventoConfig['label']);

        $storageReal = realpath($storageRoot);
        foreach ($documentos as $documento) {
            $ruta = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $documento['rutaRelativa']);
            $rutaReal = realpath($ruta);
            if ($storageReal === false || $rutaReal === false || !str_starts_with($rutaReal, $storageReal . DIRECTORY_SEPARATOR) || !is_file($rutaReal)) {
                continue;
            }

            $mail->addAttachment($rutaReal, $documento['nombreOriginal'], PHPMailer::ENCODING_BASE64, $documento['mimeType']);
        }

        $mail->send();
    }

    private function crearHtml(array $entrada, array $eventoConfig): string
    {
        $campos = [
            'Fecha de creación' => $this->formatearFecha($entrada['fecha'] ?? ''),
            'Tipo de compra' => $entrada['tipoCompra'] ?? '',
            'Producto' => $entrada['producto'] ?? '',
            'Proveedor' => $entrada['proveedor'] ?? '',
            'Fabricante' => $entrada['fabricante'] ?? '',
            'País de origen' => $entrada['pais'] ?? '',
            'Lote' => $entrada['NumLote'] ?? '',
            'Cantidad' => number_format((float) ($entrada['CantidadEntrante'] ?? 0), 2, ',', '.'),
            'Fecha de factura' => $this->formatearFecha($entrada['fecha_factura'] ?? ''),
            'Peso de romana' => isset($entrada['peso_romana']) ? number_format((float) $entrada['peso_romana'], 2, ',', '.') : '',
            'Número de factura' => $entrada['nro_factura'] ?? '',
            'Presentación' => $entrada['presentacion'] ?? '',
        ];

        $filas = '';
        foreach ($campos as $etiqueta => $valor) {
            $filas .= '<tr>'
                . '<th style="padding:12px 14px;text-align:left;color:#5d2732;background:#fff7f8;border-bottom:1px solid #ead8dc;width:34%;font-size:13px;">' . $this->escapar($etiqueta) . '</th>'
                . '<td style="padding:12px 14px;color:#292326;border-bottom:1px solid #ead8dc;font-size:14px;">' . $this->escapar($valor ?: 'No indicado') . '</td>'
                . '</tr>';
        }

        return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#f5f2f3;font-family:Arial,Helvetica,sans-serif;color:#292326;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f2f3;padding:28px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px;background:#ffffff;border:1px solid #ead8dc;border-radius:8px;overflow:hidden;">'
            . '<tr><td style="padding:24px 28px;background:#7a1c2e;color:#ffffff;"><div style="font-size:12px;font-weight:bold;text-transform:uppercase;">Inventario físico</div><div style="margin-top:5px;font-size:23px;font-weight:bold;">Recepción de materia prima</div><div style="margin-top:14px;"><span style="display:inline-block;padding:6px 10px;border-radius:4px;background:' . $eventoConfig['fondo'] . ';color:' . $eventoConfig['color'] . ';font-size:11px;font-weight:bold;letter-spacing:.4px;">' . $eventoConfig['label'] . '</span></div></td></tr>'
            . '<tr><td style="padding:26px 28px 12px;font-size:15px;line-height:1.6;"><p style="margin:0 0 14px;">Saludos,</p><p style="margin:0;">Por medio de la presente se formaliza la recepción de las siguientes materias primas:</p></td></tr>'
            . '<tr><td style="padding:14px 28px 28px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #ead8dc;border-radius:6px;overflow:hidden;border-collapse:separate;border-spacing:0;">' . $filas . '</table></td></tr>'
            . '<tr><td style="padding:18px 28px;background:#faf7f8;color:#766a6d;font-size:12px;line-height:1.5;">Los documentos asociados a esta recepción se encuentran adjuntos a este correo.<br>Mensaje generado automáticamente por ' . $this->escapar(APP_NAME) . '.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private function crearTexto(array $entrada, string $eventoLabel): string
    {
        return $eventoLabel . "\n\n"
            . "Saludos\n\n"
            . "Por medio de la presente se formaliza la recepción de las siguientes materias primas:\n\n"
            . 'Fecha de creación: ' . $this->formatearFecha($entrada['fecha'] ?? '') . "\n"
            . 'Tipo de compra: ' . ($entrada['tipoCompra'] ?? 'No indicado') . "\n"
            . 'Producto: ' . ($entrada['producto'] ?? 'No indicado') . "\n"
            . 'Proveedor: ' . ($entrada['proveedor'] ?? 'No indicado') . "\n"
            . 'Fabricante: ' . ($entrada['fabricante'] ?? 'No indicado') . "\n"
            . 'País de origen: ' . ($entrada['pais'] ?? 'No indicado') . "\n"
            . 'Lote: ' . ($entrada['NumLote'] ?? 'No indicado') . "\n"
            . 'Cantidad: ' . number_format((float) ($entrada['CantidadEntrante'] ?? 0), 2, ',', '.') . "\n"
            . 'Fecha de factura: ' . ($this->formatearFecha($entrada['fecha_factura'] ?? '') ?: 'No indicada') . "\n"
            . 'Peso de romana: ' . (isset($entrada['peso_romana']) ? number_format((float) $entrada['peso_romana'], 2, ',', '.') : 'No indicado') . "\n"
            . 'Número de factura: ' . ($entrada['nro_factura'] ?? 'No indicado') . "\n"
            . 'Presentación: ' . ($entrada['presentacion'] ?? 'No indicado') . "\n\n"
            . "Los documentos asociados se encuentran adjuntos a este correo.";
    }

    private function formatearFecha(mixed $fecha): string
    {
        $timestamp = strtotime((string) $fecha);

        return $timestamp === false ? (string) $fecha : date('d/m/Y', $timestamp);
    }

    private function escapar(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}
