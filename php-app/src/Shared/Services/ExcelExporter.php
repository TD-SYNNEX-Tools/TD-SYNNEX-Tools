<?php
declare(strict_types=1);

namespace App\Shared\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Centraliza a construcao das planilhas .xlsx (financeira e tecnica).
 * Reaproveitado pelo Excel individual e pelo Pack Completo.
 */
final class ExcelExporter
{
    public static function buildFinancialSpreadsheet(array $results, float $taxPct, float $markupPct, float $exchRate): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Analise Financeira');

        $headers = [
            'Recurso', 'Resource Group', 'Assinatura', 'Subscription ID', 'Data',
            'Quantidade',
            'MeterID', 'MeterName', 'MeterCategory', 'MeterSubcategory', 'ServiceFamily', 'ConsumedService',
            'Location', 'UnitOfMeasure', 'ProductName', 'ProductId', 'ResourceId',
            'Total FOB (BRL)', 'Total c/ Impostos (BRL)', 'Total c/ Markup (BRL)',
            'API MeterID', 'API MeterName', 'API ServiceName', 'API ServiceFamily',
            'API Location', 'API UnitOfMeasure', 'API ProductName', 'API ProductId', 'API PriceType',
            'MeterID ✓', 'Location ✓', 'UoM ✓',
            'Status API', 'Match Level', 'Match Score',
            'URL Pricing API',
        ];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '005758']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A1:AJ1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->getStyle('AD1:AF1')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']]]);
        $sheet->getStyle('U1:AC1')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']]]);
        $sheet->getStyle('R1:T1')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F59E0B']]]);

        $row = 2;
        foreach ($results['results'] as $r) {
            $sheet->setCellValue('A' . $row, $r['resourceName'] ?? '');
            $sheet->setCellValue('B' . $row, $r['resourceGroup'] ?? '');
            $sheet->setCellValue('C' . $row, $r['subscriptionName'] ?? '');
            $sheet->setCellValue('D' . $row, $r['subscriptionId'] ?? '');
            $sheet->setCellValue('E' . $row, $r['date'] ?? '');
            $sheet->setCellValue('F' . $row, $r['quantity']);

            $csvMeterId = trim($r['meterId'] ?? '');
            $csvLocation = trim($r['resourceLocation'] ?? '');
            $csvUom = trim($r['unitOfMeasure'] ?? '');
            $sheet->setCellValueExplicit('G' . $row, $csvMeterId, DataType::TYPE_STRING);
            $sheet->setCellValue('H' . $row, $r['meterName'] ?? '');
            $sheet->setCellValue('I' . $row, $r['meterCategory'] ?? '');
            $sheet->setCellValue('J' . $row, $r['meterSubcategory'] ?? '');
            $sheet->setCellValue('K' . $row, $r['serviceFamily'] ?? '');
            $sheet->setCellValue('L' . $row, $r['consumedService'] ?? '');
            $sheet->setCellValue('M' . $row, $csvLocation);
            $sheet->setCellValue('N' . $row, $csvUom);
            $sheet->setCellValue('O' . $row, $r['productName'] ?? '');
            $sheet->setCellValue('P' . $row, $r['productId'] ?? '');
            $sheet->setCellValue('Q' . $row, $r['resourceId'] ?? '');

            $costCsp = $r['costCsp'] ?? 0;
            $totalFob = $costCsp * $exchRate;
            $totalWithTax = $totalFob * (1 + $taxPct / 100);
            $totalWithMarkup = $totalWithTax * (1 + $markupPct / 100);
            $sheet->setCellValue('R' . $row, $costCsp > 0 ? $totalFob : null);
            $sheet->setCellValue('S' . $row, $costCsp > 0 ? $totalWithTax : null);
            $sheet->setCellValue('T' . $row, $costCsp > 0 ? $totalWithMarkup : null);

            $apiMeterId = trim($r['apiMeterId'] ?? '');
            $apiLocation = trim($r['apiLocation'] ?? '');
            $apiUom = trim($r['apiUnitOfMeasure'] ?? '');
            $sheet->setCellValueExplicit('U' . $row, $apiMeterId, DataType::TYPE_STRING);
            $sheet->setCellValue('V' . $row, $r['cspMeterName'] ?? '');
            $sheet->setCellValue('W' . $row, $r['cspServiceName'] ?? '');
            $sheet->setCellValue('X' . $row, $r['cspServiceFamily'] ?? '');
            $sheet->setCellValue('Y' . $row, $apiLocation);
            $sheet->setCellValue('Z' . $row, $apiUom);
            $sheet->setCellValue('AA' . $row, $r['cspProductName'] ?? '');
            $sheet->setCellValue('AB' . $row, $r['apiProductId'] ?? '');
            $sheet->setCellValue('AC' . $row, $r['cspPriceType'] ?? '');

            $meterMatch = ($csvMeterId !== '' && $apiMeterId !== '' && strtolower($csvMeterId) === strtolower($apiMeterId));
            $sheet->setCellValue('AD' . $row, $r['priceFound'] ? ($meterMatch ? '✓' : '✗') : '✗');
            $sheet->getStyle('AD' . $row)->getFont()->setColor(new Color($meterMatch && $r['priceFound'] ? '00059669' : '00DC2626'));
            $sheet->getStyle('AD' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $locationMatch = ($csvLocation !== '' && $apiLocation !== '' &&
                (strtolower($csvLocation) === strtolower($apiLocation) ||
                 str_contains(strtolower($apiLocation), strtolower($csvLocation)) ||
                 str_contains(strtolower($csvLocation), strtolower($apiLocation))));
            $sheet->setCellValue('AE' . $row, ($csvLocation === '' || $apiLocation === '') ? '—' : ($locationMatch ? '✓' : '✗'));
            $sheet->getStyle('AE' . $row)->getFont()->setColor(new Color(($csvLocation === '' || $apiLocation === '') ? '0094A3B8' : ($locationMatch ? '00059669' : '00DC2626')));
            $sheet->getStyle('AE' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $uomMatch = ($csvUom !== '' && $apiUom !== '' && strtolower($csvUom) === strtolower($apiUom));
            $sheet->setCellValue('AF' . $row, ($csvUom === '' || $apiUom === '') ? '—' : ($uomMatch ? '✓' : '✗'));
            $sheet->getStyle('AF' . $row)->getFont()->setColor(new Color(($csvUom === '' || $apiUom === '') ? '0094A3B8' : ($uomMatch ? '00059669' : '00DC2626')));
            $sheet->getStyle('AF' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue('AG' . $row, $r['priceFound'] ? '✓' : '✗');
            $sheet->getStyle('AG' . $row)->getFont()->setColor(new Color($r['priceFound'] ? '00059669' : '00DC2626'));
            $sheet->getStyle('AG' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue('AH' . $row, $r['apiMatchLevel'] ?? '');
            $sheet->setCellValue('AI' . $row, ($r['apiMatchScore'] ?? '') . '/' . ($r['apiMatchMaxScore'] ?? ''));

            $apiFilter = $r['apiFilterUsed'] ?? '';
            if (!empty($apiFilter)) {
                $pricingUrl = 'https://prices.azure.com/api/retail/prices?api-version=2023-01-01-preview&$filter=' . rawurlencode($apiFilter);
                $sheet->setCellValue('AJ' . $row, $pricingUrl);
                $sheet->getCell('AJ' . $row)->getHyperlink()->setUrl($pricingUrl);
                $sheet->getStyle('AJ' . $row)->getFont()->setColor(new Color('000066CC'))->setUnderline(true);
            }

            $row++;
        }

        $lastRow = $row - 1;
        if ($lastRow >= 2) {
            $sheet->getStyle('F2:F' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('R2:T' . $lastRow)->getNumberFormat()->setFormatCode('R$ #,##0.00');
            $sheet->getStyle('A1:AJ' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            for ($i = 2; $i <= $lastRow; $i++) {
                if ($i % 2 == 0) {
                    $sheet->getStyle('A' . $i . ':F' . $i)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F8FAFC');
                }
            }
        }

        foreach (range('A', 'Z') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        foreach (['AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ'] as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        foreach (['AD', 'AE', 'AF', 'AG'] as $c) {
            $sheet->getColumnDimension($c)->setWidth(8);
        }
        $sheet->freezePane('A2');

        // Fator combinado (impostos + markup) para sheets de resumo
        $taxMarkupFactor = (1 + $taxPct / 100) * (1 + $markupPct / 100);

        // Sheet: Resumo por Servico
        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Resumo por Servico');
        $summarySheet->setCellValue('A1', 'Servico');
        $summarySheet->setCellValue('B1', 'Custo FOB USD - Sem impostos');
        $summarySheet->setCellValue('C1', 'Custo CSP (USD)');
        $summarySheet->setCellValue('D1', 'Qtd Itens');
        $summarySheet->getStyle('A1:D1')->applyFromArray($headerStyle);
        $svcRow = 2;
        foreach ($results['summary']['byService'] ?? [] as $svc => $data) {
            $summarySheet->setCellValue('A' . $svcRow, $svc);
            $summarySheet->setCellValue('B' . $svcRow, $data['costMosp'] ?? 0);
            $summarySheet->setCellValue('C' . $svcRow, ((float)($data['costCsp'] ?? 0)) * $taxMarkupFactor);
            $summarySheet->setCellValue('D' . $svcRow, $data['count'] ?? 0);
            $svcRow++;
        }
        if ($svcRow > 2) {
            $summarySheet->getStyle('B2:C' . ($svcRow-1))->getNumberFormat()->setFormatCode('$#,##0.00');
        }
        foreach (range('A', 'D') as $c) { $summarySheet->getColumnDimension($c)->setAutoSize(true); }

        // Sheet: Por Resource Group
        $rgSheet = $spreadsheet->createSheet();
        $rgSheet->setTitle('Por Resource Group');
        $rgSheet->setCellValue('A1', 'Resource Group');
        $rgSheet->setCellValue('B1', 'Custo FOB USD - Sem impostos');
        $rgSheet->setCellValue('C1', 'Custo CSP (USD)');
        $rgSheet->setCellValue('D1', 'Qtd Itens');
        $rgSheet->getStyle('A1:D1')->applyFromArray($headerStyle);
        $rgRow = 2;
        foreach ($results['summary']['byResourceGroup'] ?? [] as $rg => $data) {
            $rgSheet->setCellValue('A' . $rgRow, $rg);
            $rgSheet->setCellValue('B' . $rgRow, $data['costMosp'] ?? 0);
            $rgSheet->setCellValue('C' . $rgRow, ((float)($data['costCsp'] ?? 0)) * $taxMarkupFactor);
            $rgSheet->setCellValue('D' . $rgRow, $data['count'] ?? 0);
            $rgRow++;
        }
        if ($rgRow > 2) {
            $rgSheet->getStyle('B2:C' . ($rgRow-1))->getNumberFormat()->setFormatCode('$#,##0.00');
        }
        foreach (range('A', 'D') as $c) { $rgSheet->getColumnDimension($c)->setAutoSize(true); }

        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
    }

    public static function buildTechnicalSpreadsheet(array $results): Spreadsheet
    {
        $tech = $results['technicalAnalysis'] ?? null;
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Analise Tecnica');

        $headers = [
            'Tipo do Recurso', 'Provider', 'Status', 'Resource Group Move',
            'Subscription Move', 'Region Move', 'Subscription', 'Subscription ID',
            'Resource Group', 'Location', 'Observacoes',
        ];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $col++;
        }

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '005758']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $boolFmt = function ($v) {
            if ($v === true) return '✓ Sim';
            if ($v === false) return '✗ Nao';
            return '—';
        };

        $row = 2;
        if ($tech) {
            foreach ($tech['results'] ?? [] as $tr) {
                $sheet->setCellValue('A' . $row, $tr['resourceType'] ?? '');
                $sheet->setCellValue('B' . $row, $tr['provider'] ?? '');
                $sheet->setCellValue('C' . $row, $tr['statusLabel'] ?? ($tr['status'] ?? ''));
                $sheet->setCellValue('D' . $row, $boolFmt($tr['resourceGroupMove'] ?? null));
                $sheet->setCellValue('E' . $row, $boolFmt($tr['subscriptionMove'] ?? null));
                $sheet->setCellValue('F' . $row, $boolFmt($tr['regionMove'] ?? null));
                $sheet->setCellValue('G' . $row, $tr['subscription'] ?? '');
                $sheet->setCellValue('H' . $row, $tr['subscriptionId'] ?? '');
                $sheet->setCellValue('I' . $row, $tr['resourceGroup'] ?? '');
                $sheet->setCellValue('J' . $row, $tr['location'] ?? '');
                $sheet->setCellValue('K' . $row, $tr['notes'] ?? '');

                // Colorir coluna Status conforme statusColor
                $color = $tr['statusColor'] ?? null;
                if ($color && preg_match('/^#?([0-9A-Fa-f]{6})$/', $color, $m)) {
                    $sheet->getStyle('C' . $row)->getFont()->setColor(new Color('00' . strtoupper($m[1])))->setBold(true);
                }

                $row++;
            }
        }
        $lastRow = $row - 1;

        if ($lastRow >= 2) {
            $sheet->getStyle('A1:K' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            for ($i = 2; $i <= $lastRow; $i++) {
                if ($i % 2 === 0) {
                    $sheet->getStyle('A' . $i . ':K' . $i)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F8FAFC');
                }
            }
            $sheet->setAutoFilter('A1:K' . $lastRow);
        }
        foreach (range('A', 'K') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }
        $sheet->freezePane('A2');

        // Sheet: Resumo
        if ($tech && !empty($tech['summary'])) {
            $sum = $tech['summary'];
            $sumSheet = $spreadsheet->createSheet();
            $sumSheet->setTitle('Resumo');
            $sumSheet->setCellValue('A1', 'Categoria');
            $sumSheet->setCellValue('B1', 'Quantidade');
            $sumSheet->setCellValue('C1', 'Percentual');
            $sumSheet->getStyle('A1:C1')->applyFromArray($headerStyle);
            $rows = [
                ['Migraveis',         $sum['movable']                  ?? 0, ($sum['movablePercent']                  ?? 0) . '%'],
                ['Com Restricoes',    $sum['movableWithRestrictions']  ?? 0, ($sum['movableWithRestrictionsPercent']  ?? 0) . '%'],
                ['Nao Migraveis',     $sum['notMovable']               ?? 0, ($sum['notMovablePercent']               ?? 0) . '%'],
                ['Desconhecidos',     $sum['unknown']                  ?? 0, ($sum['unknownPercent']                  ?? 0) . '%'],
                ['Total Tipos',       $sum['total']                    ?? 0, '100%'],
            ];
            $r = 2;
            foreach ($rows as $line) {
                $sumSheet->setCellValue('A' . $r, $line[0]);
                $sumSheet->setCellValue('B' . $r, $line[1]);
                $sumSheet->setCellValue('C' . $r, $line[2]);
                $r++;
            }
            foreach (range('A', 'C') as $c) { $sumSheet->getColumnDimension($c)->setAutoSize(true); }
        }

        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
    }

    public static function buildNotFoundSpreadsheet(array $details): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('MeterIDs nao encontrados');

        $headers = [
            'MeterID', 'MeterName', 'ServiceName', 'ProductName',
            'Region', 'UnitOfMeasure', 'Linhas Afetadas', 'Custo MOSP (USD)',
        ];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $col++;
        }
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '92400E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $row = 2;
        foreach ($details as $nf) {
            $sheet->setCellValueExplicit('A' . $row, (string)($nf['meterId'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $nf['meterName']        ?? '');
            $sheet->setCellValue('C' . $row, $nf['serviceName']      ?? '');
            $sheet->setCellValue('D' . $row, $nf['productName']      ?? '');
            $sheet->setCellValue('E' . $row, $nf['resourceLocation'] ?? '');
            $sheet->setCellValue('F' . $row, $nf['unitOfMeasure']    ?? '');
            $sheet->setCellValue('G' . $row, (int)($nf['count']      ?? 0));
            $sheet->setCellValue('H' . $row, (float)($nf['totalCost'] ?? 0));
            $row++;
        }
        $lastRow = $row - 1;

        if ($lastRow >= 2) {
            $sheet->getStyle('G2:G' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('H2:H' . $lastRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $sheet->getStyle('A1:H' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            for ($i = 2; $i <= $lastRow; $i++) {
                if ($i % 2 == 0) {
                    $sheet->getStyle('A' . $i . ':H' . $i)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FEF3C7');
                }
            }
            $sheet->setAutoFilter('A1:H' . $lastRow);
        }
        foreach (range('A', 'H') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }
        $sheet->freezePane('A2');

        return $spreadsheet;
    }
}
