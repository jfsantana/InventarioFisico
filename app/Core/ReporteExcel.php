<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReporteExcel
{
    public function descargarMovimientos(array $movimientos, array $metadata): void
    {
        $rows = array_map(static fn (array $movimiento): array => [
            $movimiento['tipo'] === 'saldo' || empty($movimiento['fecha'])
                ? ''
                : date('d/m/Y', strtotime((string) $movimiento['fecha'])),
            (string) ($movimiento['codPredespacho'] ?? ''),
            $movimiento['montoPredespacho'] === '' ? '' : (float) $movimiento['montoPredespacho'],
            $movimiento['entrada'] === '' ? '' : (float) $movimiento['entrada'],
            $movimiento['salida'] === '' ? '' : (float) $movimiento['salida'],
            (float) ($movimiento['saldo'] ?? 0),
            (string) ($movimiento['observaciones'] ?? ''),
        ], $movimientos);

        $this->descargar(
            'Movimientos por lote',
            $metadata,
            ['Fecha', 'Código de Predespacho', 'Monto Predespacho', 'Entrada', 'Salida', 'Saldo', 'Observaciones'],
            $rows,
            'movimientos-por-lote-' . date('Ymd-His') . '.xlsx',
            [3, 4, 5, 6]
        );
    }

    public function descargarSaldos(array $saldos, array $metadata): void
    {
        $rows = array_map(static fn (array $saldo): array => [
            (string) $saldo['codigoInterno'],
            (string) $saldo['producto'],
            (string) $saldo['NumLote'],
            date('d/m/Y', strtotime((string) $saldo['fechaEntrada'])),
            (string) ($saldo['presentacion'] ?? ''),
            (string) ($saldo['ubicacion'] ?? ''),
            (string) ($saldo['sector'] ?? ''),
            (float) $saldo['stock_total'],
            (float) $saldo['cantidad_saliente'],
            (float) $saldo['cantidad_reservada'],
            (float) $saldo['saldo_fisico'],
            (float) $saldo['cantidad_disponible'],
        ], $saldos);

        $this->descargar(
            'Saldo de Productos por Lote',
            $metadata,
            ['Código', 'Producto', 'Lote', 'Fecha entrada', 'Presentación', 'Ubicación', 'Sector', 'Entrada', 'Salidas', 'Reservado', 'Saldo físico', 'Disponible'],
            $rows,
            'saldo-productos-por-lote-' . date('Ymd-His') . '.xlsx',
            [8, 9, 10, 11, 12]
        );
    }

    private function descargar(
        string $title,
        array $metadata,
        array $headers,
        array $rows,
        string $filename,
        array $numericColumns
    ): void {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:' . $lastColumn . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF7A1C2E');

        $rowNumber = 3;
        foreach ($metadata as $label => $value) {
            $sheet->setCellValue('A' . $rowNumber, $label);
            $sheet->setCellValue('B' . $rowNumber, $value);
            $sheet->getStyle('A' . $rowNumber)->getFont()->setBold(true);
            $rowNumber++;
        }

        $rowNumber++;
        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . $rowNumber, $header);
        }
        $headerRow = $rowNumber;
        $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF7A1C2E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach ($rows as $row) {
            $rowNumber++;
            foreach ($row as $index => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . $rowNumber, $value);
            }
        }

        if ($rowNumber > $headerRow) {
            foreach ($numericColumns as $columnIndex) {
                $column = Coordinate::stringFromColumnIndex($columnIndex);
                $sheet->getStyle($column . ($headerRow + 1) . ':' . $column . $rowNumber)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.000');
            }
            $sheet->setAutoFilter('A' . $headerRow . ':' . $lastColumn . $rowNumber);
        }

        $sheet->freezePane('A' . ($headerRow + 1));
        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }
}