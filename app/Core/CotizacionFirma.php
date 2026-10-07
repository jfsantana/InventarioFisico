<?php

use PHPMailer\PHPMailer\PHPMailer;

class CotizacionFirma
{
    private const IMAGENES = [
        'firma' => 'fimaAdrianTransparente.png',
        'sello' => 'sellohumedoAdyarTransparente.png',
    ];

    public static function imagenesPdf(): array
    {
        $imagenes = [];
        foreach (self::IMAGENES as $tipo => $archivo) {
            $contenido = file_get_contents(self::ruta($archivo));
            if ($contenido === false) {
                throw new RuntimeException('No se pudo leer la imagen de ' . $tipo . ' de la cotizacion.');
            }
            $imagenes[$tipo] = 'data:image/png;base64,' . base64_encode($contenido);
        }
        return $imagenes;
    }

    public static function adjuntarImagenes(PHPMailer $mail): array
    {
        $imagenes = [];
        foreach (self::IMAGENES as $tipo => $archivo) {
            $cid = 'cotizacion-' . $tipo;
            if (!$mail->addEmbeddedImage(self::ruta($archivo), $cid, $archivo, PHPMailer::ENCODING_BASE64, 'image/png')) {
                throw new RuntimeException('No se pudo incorporar la imagen de ' . $tipo . ' al correo.');
            }
            $imagenes[$tipo] = 'cid:' . $cid;
        }
        return $imagenes;
    }

    public static function crearHtml(array $imagenes, bool $email = false): string
    {
        $firma = htmlspecialchars($imagenes['firma'], ENT_QUOTES, 'UTF-8');
        $sello = htmlspecialchars($imagenes['sello'], ENT_QUOTES, 'UTF-8');
        $titulo = $email ? '11px' : '8px';
        $texto = $email ? '12px' : '9px';

        return '<div style="margin-top:22px;page-break-inside:avoid;border-top:1px solid #e1d6da;padding-top:12px">'
            . '<div style="color:#801d35;font-size:' . $titulo . ';font-weight:bold;letter-spacing:1px"> </div>'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:8px">'
            . '<tr><td width="55%" align="center" style="width:55%;vertical-align:bottom;padding:8px 12px">'
            . '<img src="' . $firma . '" width="190" alt="Firma de Adrián" style="display:block;width:190px;max-width:100%;height:auto;margin:0 auto">'
            . '</td><td width="45%" align="center" style="width:45%;vertical-align:bottom;padding:8px 12px">'
            . '<img src="' . $sello . '" width="150" alt="Sello de ADYAR Industries" style="display:block;width:150px;max-width:100%;height:auto;margin:0 auto">'
            . '</td></tr><tr><td align="center" style="padding:6px 12px;border-top:1px solid #b9a7ae;color:#2d333b;font-size:' . $texto . '">'
            . '<strong>Firma autorizada</strong><div style="margin-top:3px;color:#777;font-size:' . $titulo . '">Adrian Niño</div>'
            . '</td><td align="center" style="padding:6px 12px;border-top:1px solid #b9a7ae;color:#2d333b;font-size:' . $texto . '">'
            . '<strong>Sello de la empresa</strong><div style="margin-top:3px;color:#777;font-size:' . $titulo . '">RIF J-29967374-9</div>'
            . '</td></tr></table></div>';
    }

    private static function ruta(string $archivo): string
    {
        $ruta = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $archivo;
        if (!is_file($ruta) || !is_readable($ruta)) {
            throw new RuntimeException('No se encontro la imagen requerida para la cotizacion: ' . $archivo);
        }
        $informacion = getimagesize($ruta);
        if ($informacion === false || $informacion[2] !== IMAGETYPE_PNG) {
            throw new RuntimeException('La imagen de la cotizacion no es un PNG valido: ' . $archivo);
        }
        return $ruta;
    }
}
