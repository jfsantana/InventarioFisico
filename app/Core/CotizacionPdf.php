<?php

use Dompdf\Dompdf;
use Dompdf\Options;

class CotizacionPdf
{
    public function generar(array $cotizacion): string
    {
        if (empty($cotizacion['detalles'])) {
            throw new InvalidArgumentException('La cotizacion no contiene detalles.');
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->crearHtml($cotizacion), 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function crearHtml(array $cotizacion): string
    {
        $fechaEmision = new DateTimeImmutable((string) $cotizacion['fechaEmision']);
        $fechaVencimiento = $fechaEmision->modify('+' . (int) $cotizacion['diasVigencia'] . ' days');
        $fechaCreacion = new DateTimeImmutable((string) ($cotizacion['fechaCreacion'] ?? $cotizacion['fechaEmision']));
        $numeroCotizacion = $fechaCreacion->format('YmdH');
        $subtotal = (float) $cotizacion['subtotal'];
        $iva = round($subtotal * 0.16, 2);
        $total = round($subtotal + $iva, 2);
        $logo = $this->logoDataUri();
        $filas = '';

        foreach ($cotizacion['detalles'] as $detalle) {
            $filas .= '<tr>'
                . '<td class="numero cantidad">' . $this->cantidad($detalle['cantidad']) . '</td>'
                . '<td class="unidad">' . $this->escapar($this->extraerUnidad((string) $detalle['presentacion'])) . '</td>'
                . '<td class="descripcion">' . $this->escapar(mb_strtoupper((string) $detalle['producto'], 'UTF-8')) . '</td>'
                . '<td>' . $this->escapar(mb_strtoupper((string) $detalle['presentacion'], 'UTF-8')) . '</td>'
                . '<td class="numero">' . $this->numero($detalle['precioUnitario']) . '</td>'
                . '<td class="numero total-linea">' . $this->numero($detalle['subtotal']) . '</td>'
                . '</tr>';
        }

        $observacion = trim((string) ($cotizacion['observacion'] ?? ''));
        $cliente = mb_strtoupper((string) $cotizacion['nombreCliente'], 'UTF-8');
        $direccion = mb_strtoupper(trim((string) ($cotizacion['direccionCliente'] ?? '')), 'UTF-8');

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>'
            . '@page{margin:30px 34px 32px}*{box-sizing:border-box}body{margin:0;font-family:"DejaVu Sans",sans-serif;color:#2d333b;font-size:9px}'
            . '.brand-table,.quote-table,.client-table,.summary-table{width:100%;border-collapse:collapse}'
            . '.brand-table td{vertical-align:middle}.logo-cell{width:46%}.logo{width:205px;height:auto}.company-cell{text-align:right}'
            . '.company-name{color:#801d35;font-size:18px;font-weight:bold;letter-spacing:.4px}.company-rif{margin-top:5px;color:#555;font-size:9px}'
            . '.top-rule{height:4px;margin:15px 0 18px;background:#801d35}'
            . '.quote-table td{vertical-align:top}.quote-spacer{width:58%}.quote-box{width:42%;padding:10px 13px;border-left:4px solid #e52b20;background:#f7f7f7}'
            . '.quote-label{color:#e52b20;font-size:11px;font-weight:bold}.quote-number{color:#2d333b;font-size:13px;font-weight:bold}.quote-date{margin-top:4px;color:#555;font-size:8px}'
            . '.client-block{margin:24px 0 18px;padding:0 2px}.client-name{color:#2d333b;font-size:12px;font-weight:bold}.client-line{margin-top:4px;color:#4f555b;line-height:1.35}'
            . '.document-box{min-height:430px;border:1.5px solid #2d333b;border-radius:2px;overflow:hidden}'
            . 'table.detalle{width:100%;border-collapse:collapse;table-layout:fixed}.detalle th{padding:9px 5px;border-right:1px solid #fff;background:#858585;color:#fff;font-size:7.5px;text-align:center}'
            . '.detalle th:last-child{border-right:0}.detalle td{padding:7px 5px;border-right:1px solid #b7b7b7;border-bottom:1px solid #b7b7b7;vertical-align:top;font-size:7.5px}'
            . '.detalle td:last-child{border-right:0}.detalle .cantidad{width:9%}.detalle .unidad{width:9%;text-align:center}.detalle .descripcion{width:31%}.detalle th:nth-child(4){width:19%}.detalle th:nth-child(5){width:14%}.detalle th:nth-child(6){width:18%}'
            . '.numero{text-align:right}.total-linea{font-weight:bold}.commercial{padding:20px 12px 12px}.commercial-table{width:100%;border-collapse:collapse}.conditions{width:58%;vertical-align:top;padding-right:24px}'
            . '.conditions-title{display:inline-block;padding-bottom:2px;border-bottom:2px solid #801d35;color:#801d35;font-size:8px;font-weight:bold}.conditions-value{margin-top:5px;font-weight:bold}'
            . '.note{margin-top:22px}.summary{width:42%;vertical-align:bottom}.summary-table td{padding:4px 0;border-bottom:1px solid #d5d5d5}.summary-table td:last-child{text-align:right;font-weight:bold}'
            . '.summary-table .grand-total td{padding-top:7px;border-top:2px solid #801d35;border-bottom:0;color:#801d35;font-size:10px;font-weight:bold}'
            . '.footer-line{margin-top:18px;height:3px;background:#801d35}.footer-text{margin-top:6px;color:#777;font-size:7px;text-align:center}'
            . '</style></head><body>'
            . '<table class="brand-table"><tr><td class="logo-cell"><img class="logo" src="' . $logo . '" alt="ADYAR"></td>'
            . '<td class="company-cell"><div class="company-name">ADYARCA INDUSTRIES C.A.</div><div class="company-rif">RIF: J-29967374-9</div></td></tr></table>'
            . '<div class="top-rule"></div>'
            . '<table class="quote-table"><tr><td class="quote-spacer"></td><td class="quote-box"><div><span class="quote-label">COTIZACION</span> <span class="quote-number">N° ' . $numeroCotizacion . '</span></div>'
            . '<div class="quote-date"><strong>FECHA DE CREACION:</strong> ' . $fechaCreacion->format('d/m/Y') . '</div>'
            . '<div class="quote-date"><strong>FECHA DE EXPIRACION:</strong> ' . $fechaVencimiento->format('d/m/Y') . '</div></td></tr></table>'
            . '<div class="client-block"><div class="client-name">' . $this->escapar($cliente) . '</div>'
            . '<div class="client-line"><strong>RIF:</strong> ' . $this->escapar($cotizacion['rifCliente']) . '</div>'
            . ($direccion !== '' ? '<div class="client-line">' . $this->escapar($direccion) . '</div>' : '') . '</div>'
            . '<div class="document-box"><table class="detalle"><thead><tr><th>CANTIDAD</th><th>UNIDAD</th><th>DESCRIPCION</th><th>PRESENTACION</th><th>PRECIO</th><th>TOTAL</th></tr></thead><tbody>' . $filas . '</tbody></table>'
            . '<div class="commercial"><table class="commercial-table"><tr><td class="conditions">'
            . '<div class="conditions-title">CONDICIONES DE PAGO</div><div class="conditions-value">' . $this->escapar(mb_strtoupper((string) $cotizacion['condicionPago'], 'UTF-8')) . '</div>'
            . '<div class="note"><div class="conditions-title">NOTA</div><div class="conditions-value">' . ($observacion !== '' ? nl2br($this->escapar(mb_strtoupper($observacion, 'UTF-8'))) : 'SIN OBSERVACIONES') . '</div></div>'
            . '</td><td class="summary"><table class="summary-table"><tr><td>SUBTOTAL</td><td>' . $this->numero($subtotal) . '</td></tr>'
            . '<tr><td>IVA 16%</td><td>' . $this->numero($iva) . '</td></tr><tr class="grand-total"><td>TOTAL</td><td>' . $this->numero($total) . '</td></tr></table></td></tr></table></div></div>'
            . '<div class="footer-line"></div><div class="footer-text">ADYARCA INDUSTRIES C.A. · RIF J-29967374-9</div>'
            . '</body></html>';
    }

    private function logoDataUri(): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'logoAdyarcaCotizacion.png';
        if (!is_readable($path)) {
            throw new RuntimeException('No se encontro el logo para la cotizacion.');
        }

        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
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

    private function escapar(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
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