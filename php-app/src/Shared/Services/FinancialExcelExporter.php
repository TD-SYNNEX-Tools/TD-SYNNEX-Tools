<?php

declare(strict_types=1);

namespace App\Shared\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Exportador de relatório Excel para Análise Financeira Azure
 * Inclui verificações de match e formatação condicional para facilitar validação
 */
class FinancialExcelExporter
{
    private string $reportsDir;

    // Cores TD SYNNEX
    private const TEAL = '005758';
    private const TEAL_DARK = '003031';
    private const BLUE = '08BED5';
    private const GREEN = '82C341';
    private const GREEN_BG = 'D4EDDA';
    private const GREEN_TEXT = '155724';
    private const YELLOW = 'FFD100';
    private const YELLOW_BG = 'FFF3CD';
    private const YELLOW_TEXT = '856404';
    private const RED = 'D9272E';
    private const RED_BG = 'F8D7DA';
    private const RED_TEXT = '721C24';
    private const GRAY_BG = 'F8F9FA';

    public function __construct()
    {
        $this->reportsDir = dirname(__DIR__, 2) . '/reports';
        if (!is_dir($this->reportsDir)) {
            mkdir($this->reportsDir, 0755, true);
        }
    }

    /**
     * Gera o relatório Excel completo
     */
    public function generate(array $results): array
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        try {
            $spreadsheet = new Spreadsheet();
            
            // Configurar propriedades do documento
            $spreadsheet->getProperties()
                ->setCreator('TD SYNNEX - Azure Financial Analyzer')
                ->setTitle('Análise Financeira Azure - MOSP vs CSP')
                ->setSubject('Relatório de Comparação de Preços')
                ->setDescription('Análise detalhada de custos Azure com verificações de match')
                ->setCompany('TD SYNNEX');

            // Aba 1: Resumo Executivo
            $this->createSummarySheet($spreadsheet, $results);
            
            // Aba 2: Dados Detalhados com Match
            $this->createDetailsSheet($spreadsheet, $results);
            
            // Aba 3: Comparação CSV vs API com Score
            $this->createCsvVsApiSheet($spreadsheet, $results);
            
            // Aba 4: Verificações de Match
            $this->createMatchValidationSheet($spreadsheet, $results);
            
            // Aba 5: Por Serviço
            $this->createByServiceSheet($spreadsheet, $results);

            // Definir primeira aba como ativa
            $spreadsheet->setActiveSheetIndex(0);

            // Salvar arquivo
            $filename = 'analise_financeira_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filepath = $this->reportsDir . DIRECTORY_SEPARATOR . $filename;
            
            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);

            return [
                'success' => true,
                'path' => $filepath,
                'filename' => $filename,
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'path' => null,
                'filename' => null,
                'error' => 'Erro ao gerar Excel: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Aba de Resumo Executivo
     */
    private function createSummarySheet(Spreadsheet $spreadsheet, array $results): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumo Executivo');
        $summary = $results['summary'];

        // Cabeçalho
        $sheet->setCellValue('A1', 'ANÁLISE FINANCEIRA AZURE');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->setName('Segoe UI');
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB(self::TEAL);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->setCellValue('A2', 'Comparativo MOSP vs CSP - ' . ($results['clientName'] ?: 'Cliente não identificado'));
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setSize(11)->setName('Segoe UI');
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('666666');

        // Linha decorativa
        $sheet->setCellValue('A3', '');
        $sheet->mergeCells('A3:H3');
        $sheet->getStyle('A3:H3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BLUE);
        $sheet->getRowDimension(3)->setRowHeight(4);

        // Informações gerais
        $row = 5;
        $this->addInfoRow($sheet, $row++, 'Data da Análise:', date('d/m/Y H:i'));
        $this->addInfoRow($sheet, $row++, 'Arquivo Origem:', $results['filename'] ?? 'N/D');
        $this->addInfoRow($sheet, $row++, 'Período de Referência:', $results['referenceMonth'] ?? 'N/D');
        $this->addInfoRow($sheet, $row++, 'Total de Linhas:', number_format($summary['totalRows'], 0, ',', '.'));
        $this->addInfoRow($sheet, $row++, 'MeterIDs Únicos:', number_format($summary['uniqueMeterIds'], 0, ',', '.'));
        
        $row += 2;

        // Cards de totais
        $sheet->setCellValue('A' . $row, 'RESUMO FINANCEIRO');
        $sheet->mergeCells('A' . $row . ':H' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A' . $row)->getFont()->getColor()->setRGB(self::TEAL);
        $sheet->getStyle('A' . $row . ':H' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::BLUE);
        $row += 2;

        // Tabela de custos
        $headers = ['Métrica', 'Valor (USD)', 'Observação'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true)->setSize(10);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TEAL);
            $sheet->getStyle($col . $row)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }
        $row++;

        // Dados de custo
        $costData = [
            ['Custo Total MOSP', $summary['totalMosp'], 'Preço de lista Microsoft'],
            ['Custo Total CSP', $summary['totalCsp'], 'Preço CSP TD SYNNEX'],
            ['Economia Potencial', $summary['totalSavings'], $summary['totalSavings'] >= 0 ? 'Economia com CSP' : 'CSP mais caro'],
            ['Economia %', $summary['savingsPercent'] . '%', $summary['savingsPercent'] >= 0 ? 'Redução de custo' : 'Aumento de custo'],
        ];

        foreach ($costData as $data) {
            $sheet->setCellValue('A' . $row, $data[0]);
            $sheet->setCellValue('B' . $row, is_numeric($data[1]) ? $data[1] : $data[1]);
            $sheet->setCellValue('C' . $row, $data[2]);
            
            if (is_numeric($data[1])) {
                $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('$#,##0.00');
            }
            
            // Colorir economia
            if (strpos($data[0], 'Economia') !== false && is_numeric($data[1])) {
                $color = $data[1] >= 0 ? self::GREEN_BG : self::RED_BG;
                $textColor = $data[1] >= 0 ? self::GREEN_TEXT : self::RED_TEXT;
                $sheet->getStyle('B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                $sheet->getStyle('B' . $row)->getFont()->getColor()->setRGB($textColor);
                $sheet->getStyle('B' . $row)->getFont()->setBold(true);
            }
            
            $row++;
        }

        $row += 2;

        // Status de Match da API
        $sheet->setCellValue('A' . $row, 'STATUS DE MATCH DA API');
        $sheet->mergeCells('A' . $row . ':H' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A' . $row)->getFont()->getColor()->setRGB(self::TEAL);
        $sheet->getStyle('A' . $row . ':H' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::BLUE);
        $row += 2;

        $matchFound = 0;
        $matchNotFound = 0;
        foreach ($results['results'] as $r) {
            if ($r['priceFound']) {
                $matchFound++;
            } else {
                $matchNotFound++;
            }
        }
        $totalItems = count($results['results']);
        $matchPercent = $totalItems > 0 ? round(($matchFound / $totalItems) * 100, 1) : 0;

        $matchData = [
            ['Total de Itens', $totalItems, ''],
            ['Match Encontrado (API)', $matchFound, $matchPercent . '% de sucesso'],
            ['Match NÃO Encontrado', $matchNotFound, 'Requer verificação manual'],
        ];

        $col = 'A';
        foreach (['Métrica', 'Quantidade', 'Observação'] as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true)->setSize(10);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TEAL);
            $sheet->getStyle($col . $row)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }
        $row++;

        foreach ($matchData as $data) {
            $sheet->setCellValue('A' . $row, $data[0]);
            $sheet->setCellValue('B' . $row, $data[1]);
            $sheet->setCellValue('C' . $row, $data[2]);
            
            if (strpos($data[0], 'Encontrado') !== false && strpos($data[0], 'NÃO') === false) {
                $sheet->getStyle('B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GREEN_BG);
                $sheet->getStyle('B' . $row)->getFont()->getColor()->setRGB(self::GREEN_TEXT);
            } elseif (strpos($data[0], 'NÃO') !== false) {
                $sheet->getStyle('B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::RED_BG);
                $sheet->getStyle('B' . $row)->getFont()->getColor()->setRGB(self::RED_TEXT);
            }
            
            $row++;
        }

        // Ajustar larguras
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(30);

        $sheet->freezePane('A4');
    }

    /**
     * Aba de Dados Detalhados
     */
    private function createDetailsSheet(Spreadsheet $spreadsheet, array $results): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Dados Detalhados');

        // Cabeçalhos
        $headers = [
            'Recurso', 'Serviço', 'Resource Group', 'MeterID', 'Quantidade', 'Unidade',
            'Preço MOSP', 'Custo MOSP', 'Preço CSP', 'Custo CSP', 
            'Diferença', 'Variação %', 'Match API', 'Validação'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true)->setSize(9);
            $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TEAL);
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Dados
        $row = 2;
        foreach ($results['results'] as $r) {
            $matchStatus = $r['priceFound'] ? 'SIM' : 'NAO';
            
            // Validação automática
            $validation = '';
            $validationColor = '';
            if (!$r['priceFound']) {
                $validation = 'VERIFICAR - Sem preço CSP';
                $validationColor = self::RED_BG;
            } elseif ($r['differencePercent'] !== null && abs($r['differencePercent']) > 20) {
                $validation = 'ATENÇÃO - Variação > 20%';
                $validationColor = self::YELLOW_BG;
            } elseif ($r['costCsp'] === null || $r['costCsp'] == 0) {
                $validation = 'VERIFICAR - Custo zerado';
                $validationColor = self::YELLOW_BG;
            } else {
                $validation = 'OK';
                $validationColor = self::GREEN_BG;
            }

            $sheet->setCellValue('A' . $row, $r['resourceName']);
            $sheet->setCellValue('B' . $row, $r['meterCategory'] ?: $r['serviceFamily']);
            $sheet->setCellValue('C' . $row, $r['resourceGroup']);
            $sheet->setCellValue('D' . $row, $r['meterId']);
            $sheet->setCellValue('E' . $row, $r['quantity']);
            $sheet->setCellValue('F' . $row, $r['unitOfMeasure']);
            $sheet->setCellValue('G' . $row, $r['unitPriceMosp']);
            $sheet->setCellValue('H' . $row, $r['costMosp']);
            $sheet->setCellValue('I' . $row, $r['unitPriceCsp']);
            $sheet->setCellValue('J' . $row, $r['costCsp']);
            $sheet->setCellValue('K' . $row, $r['difference']);
            $sheet->setCellValue('L' . $row, $r['differencePercent'] !== null ? $r['differencePercent'] . '%' : 'N/D');
            $sheet->setCellValue('M' . $row, $matchStatus);
            $sheet->setCellValue('N' . $row, $validation);

            // Formatar números
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.000000');
            $sheet->getStyle('G' . $row . ':K' . $row)->getNumberFormat()->setFormatCode('$#,##0.000000');

            // Colorir Match
            if ($matchStatus === 'SIM') {
                $sheet->getStyle('M' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GREEN_BG);
                $sheet->getStyle('M' . $row)->getFont()->getColor()->setRGB(self::GREEN_TEXT);
            } else {
                $sheet->getStyle('M' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::RED_BG);
                $sheet->getStyle('M' . $row)->getFont()->getColor()->setRGB(self::RED_TEXT);
            }

            // Colorir Validação
            if ($validationColor) {
                $sheet->getStyle('N' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($validationColor);
            }

            $row++;
        }

        $lastRow = $row - 1;

        // Estilo geral
        $sheet->getStyle("A2:N{$lastRow}")->getFont()->setSize(9);
        $sheet->getStyle("M2:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("M2:N{$lastRow}")->getFont()->setBold(true);

        // Ajustar larguras
        $widths = ['A' => 25, 'B' => 20, 'C' => 18, 'D' => 38, 'E' => 12, 'F' => 15, 'G' => 14, 'H' => 14, 'I' => 14, 'J' => 14, 'K' => 14, 'L' => 12, 'M' => 10, 'N' => 25];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Filtro e freeze
        $sheet->setAutoFilter('A1:N' . $lastRow);
        $sheet->freezePane('A2');
    }

    /**
     * Aba de Comparação CSV vs API com Score de Match
     */
    private function createCsvVsApiSheet(Spreadsheet $spreadsheet, array $results): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('CSV vs API');

        // Título
        $sheet->setCellValue('A1', 'COMPARAÇÃO: DADOS DO CSV vs RESPOSTA DA API MICROSOFT');
        $sheet->mergeCells('A1:S1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB(self::TEAL);

        $sheet->setCellValue('A2', 'Verificação lado a lado dos campos do arquivo original vs dados retornados pela API de preços CSP');
        $sheet->mergeCells('A2:S2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('666666');

        // Linha de separação de seções
        $sheet->setCellValue('A3', '');
        $sheet->mergeCells('A3:S3');
        $sheet->getStyle('A3:S3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BLUE);
        $sheet->getRowDimension(3)->setRowHeight(3);

        // Cabeçalhos - agrupados por seção
        $headerRow = 5;
        
        // Grupo 1: Identificação
        $sheet->setCellValue('A4', 'IDENTIFICAÇÃO');
        $sheet->mergeCells('A4:B4');
        $sheet->getStyle('A4:B4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TEAL_DARK);
        $sheet->getStyle('A4')->getFont()->setBold(true)->setColor(new Color('FFFFFF'));

        // Grupo 2: MeterID
        $sheet->setCellValue('C4', 'METER ID');
        $sheet->mergeCells('C4:E4');
        $sheet->getStyle('C4:E4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0078D4');
        $sheet->getStyle('C4')->getFont()->setBold(true)->setColor(new Color('FFFFFF'));

        // Grupo 3: Product/Meter Name
        $sheet->setCellValue('F4', 'NOME DO PRODUTO/METER');
        $sheet->mergeCells('F4:H4');
        $sheet->getStyle('F4:H4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('5C2D91');
        $sheet->getStyle('F4')->getFont()->setBold(true)->setColor(new Color('FFFFFF'));

        // Grupo 4: Location
        $sheet->setCellValue('I4', 'LOCALIZAÇÃO');
        $sheet->mergeCells('I4:K4');
        $sheet->getStyle('I4:K4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('107C10');
        $sheet->getStyle('I4')->getFont()->setBold(true)->setColor(new Color('FFFFFF'));

        // Grupo 5: Unit of Measure
        $sheet->setCellValue('L4', 'UNIDADE DE MEDIDA');
        $sheet->mergeCells('L4:N4');
        $sheet->getStyle('L4:N4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CA5010');
        $sheet->getStyle('L4')->getFont()->setBold(true)->setColor(new Color('FFFFFF'));

        // Grupo 6: Score
        $sheet->setCellValue('O4', 'SCORE DE MATCH');
        $sheet->mergeCells('O4:S4');
        $sheet->getStyle('O4:S4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TEAL);
        $sheet->getStyle('O4')->getFont()->setBold(true)->setColor(new Color('FFFFFF'));

        // Sub-cabeçalhos
        $subHeaders = [
            'A' => '#',
            'B' => 'Recurso',
            'C' => 'CSV: MeterID',
            'D' => 'API: MeterID',
            'E' => 'Match?',
            'F' => 'CSV: MeterName',
            'G' => 'API: MeterName',
            'H' => 'Match?',
            'I' => 'CSV: Location',
            'J' => 'API: Location',
            'K' => 'Match?',
            'L' => 'CSV: UoM',
            'M' => 'API: UoM',
            'N' => 'Match?',
            'O' => 'Score',
            'P' => 'Max',
            'Q' => '%',
            'R' => 'Nível',
            'S' => 'Filtro Usado'
        ];

        foreach ($subHeaders as $col => $header) {
            $sheet->setCellValue($col . $headerRow, $header);
            $sheet->getStyle($col . $headerRow)->getFont()->setBold(true)->setSize(9);
            $sheet->getStyle($col . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8E8E8');
            $sheet->getStyle($col . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($col . $headerRow)->getAlignment()->setWrapText(true);
        }
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        // Dados
        $row = 6;
        $count = 1;
        foreach ($results['results'] as $r) {
            // Comparações
            $csvMeterId = $r['meterId'] ?? '';
            $apiMeterId = $r['apiMeterId'] ?? '';
            $meterIdMatch = !empty($apiMeterId) && strtolower($csvMeterId) === strtolower($apiMeterId);

            $csvMeterName = $r['meterName'] ?? '';
            $apiMeterName = $r['cspMeterName'] ?? '';
            $meterNameMatch = !empty($apiMeterName) && (
                strtolower($csvMeterName) === strtolower($apiMeterName) ||
                strpos(strtolower($apiMeterName), strtolower($csvMeterName)) !== false ||
                strpos(strtolower($csvMeterName), strtolower($apiMeterName)) !== false
            );

            $csvLocation = $r['resourceLocation'] ?? '';
            $apiLocation = $r['apiLocation'] ?? '';
            $locationMatch = !empty($apiLocation) && (
                strtolower($csvLocation) === strtolower($apiLocation) ||
                strpos(strtolower($apiLocation), strtolower($csvLocation)) !== false
            );

            $csvUoM = $r['unitOfMeasure'] ?? '';
            $apiUoM = $r['apiUnitOfMeasure'] ?? '';
            $uomMatch = !empty($apiUoM) && (
                strtolower($csvUoM) === strtolower($apiUoM) ||
                $this->normalizeUoM($csvUoM) === $this->normalizeUoM($apiUoM)
            );

            // Score da API (se disponível)
            $apiScore = $r['apiMatchScore'] ?? null;
            $apiMaxScore = $r['apiMatchMaxScore'] ?? null;
            $scorePercent = ($apiMaxScore > 0 && $apiScore !== null) ? round(($apiScore / $apiMaxScore) * 100, 0) : null;

            // Calcular score próprio se API não retornou
            if ($apiScore === null && $r['priceFound']) {
                $apiScore = ($meterIdMatch ? 40 : 0) + ($meterNameMatch ? 20 : 0) + ($locationMatch ? 20 : 0) + ($uomMatch ? 20 : 0);
                $apiMaxScore = 100;
                $scorePercent = $apiScore;
            }

            $matchLevel = $r['apiMatchLevel'] ?? ($r['priceFound'] ? 'Encontrado' : 'Não encontrado');
            $filterUsed = $r['apiFilterUsed'] ?? '-';

            // Preencher células
            $sheet->setCellValue('A' . $row, $count);
            $sheet->setCellValue('B' . $row, substr($r['resourceName'] ?? '', 0, 30));
            
            $sheet->setCellValue('C' . $row, $csvMeterId);
            $sheet->setCellValue('D' . $row, $apiMeterId ?: '-');
            $sheet->setCellValue('E' . $row, $meterIdMatch ? 'SIM' : ($r['priceFound'] ? 'PARCIAL' : 'NAO'));
            
            $sheet->setCellValue('F' . $row, substr($csvMeterName, 0, 25));
            $sheet->setCellValue('G' . $row, substr($apiMeterName ?: '-', 0, 25));
            $sheet->setCellValue('H' . $row, $meterNameMatch ? 'SIM' : ($r['priceFound'] ? 'PARCIAL' : 'NAO'));
            
            $sheet->setCellValue('I' . $row, $csvLocation);
            $sheet->setCellValue('J' . $row, $apiLocation ?: '-');
            $sheet->setCellValue('K' . $row, $locationMatch ? 'SIM' : ($r['priceFound'] ? 'PARCIAL' : 'NAO'));
            
            $sheet->setCellValue('L' . $row, $csvUoM);
            $sheet->setCellValue('M' . $row, $apiUoM ?: '-');
            $sheet->setCellValue('N' . $row, $uomMatch ? 'SIM' : ($r['priceFound'] ? 'PARCIAL' : 'NAO'));
            
            $sheet->setCellValue('O' . $row, $apiScore ?? '-');
            $sheet->setCellValue('P' . $row, $apiMaxScore ?? '-');
            $sheet->setCellValue('Q' . $row, $scorePercent !== null ? $scorePercent . '%' : '-');
            $sheet->setCellValue('R' . $row, $matchLevel);
            $sheet->setCellValue('S' . $row, substr($filterUsed, 0, 30));

            // Colorir colunas de Match
            $matchCols = ['E', 'H', 'K', 'N'];
            foreach ($matchCols as $matchCol) {
                $val = $sheet->getCell($matchCol . $row)->getValue();
                if ($val === 'SIM') {
                    $sheet->getStyle($matchCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GREEN_BG);
                    $sheet->getStyle($matchCol . $row)->getFont()->getColor()->setRGB(self::GREEN_TEXT);
                } elseif ($val === 'PARCIAL') {
                    $sheet->getStyle($matchCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::YELLOW_BG);
                    $sheet->getStyle($matchCol . $row)->getFont()->getColor()->setRGB(self::YELLOW_TEXT);
                } else {
                    $sheet->getStyle($matchCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::RED_BG);
                    $sheet->getStyle($matchCol . $row)->getFont()->getColor()->setRGB(self::RED_TEXT);
                }
                $sheet->getStyle($matchCol . $row)->getFont()->setBold(true);
                $sheet->getStyle($matchCol . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Colorir Score %
            if ($scorePercent !== null) {
                if ($scorePercent >= 80) {
                    $sheet->getStyle('Q' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GREEN_BG);
                } elseif ($scorePercent >= 50) {
                    $sheet->getStyle('Q' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::YELLOW_BG);
                } else {
                    $sheet->getStyle('Q' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::RED_BG);
                }
                $sheet->getStyle('Q' . $row)->getFont()->setBold(true);
            }

            $count++;
            $row++;
        }

        $lastRow = $row - 1;

        // Estilo geral
        $sheet->getStyle("A6:S{$lastRow}")->getFont()->setSize(8);
        $sheet->getStyle("A6:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("O6:Q{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Ajustar larguras
        $widths = [
            'A' => 5, 'B' => 22, 
            'C' => 36, 'D' => 36, 'E' => 8,
            'F' => 22, 'G' => 22, 'H' => 8,
            'I' => 14, 'J' => 14, 'K' => 8,
            'L' => 14, 'M' => 14, 'N' => 8,
            'O' => 7, 'P' => 6, 'Q' => 7, 'R' => 14, 'S' => 25
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Filtro e freeze
        $sheet->setAutoFilter('A5:S' . $lastRow);
        $sheet->freezePane('C6');
    }

    /**
     * Normaliza unidade de medida para comparação
     */
    private function normalizeUoM(string $uom): string
    {
        $uom = strtolower(trim($uom));
        // Remove plurais e variações comuns
        $uom = preg_replace('/\s+/', '', $uom);
        $uom = str_replace(['hours', 'hour', 'hrs', 'hr'], 'h', $uom);
        $uom = str_replace(['gigabytes', 'gigabyte', 'gb'], 'gb', $uom);
        $uom = str_replace(['units', 'unit'], 'u', $uom);
        $uom = str_replace(['1/month', '/month', 'permonth', 'month'], 'mo', $uom);
        return $uom;
    }

    /**
     * Aba de Verificações de Match
     */
    private function createMatchValidationSheet(Spreadsheet $spreadsheet, array $results): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Verificacao Match');

        // Título
        $sheet->setCellValue('A1', 'VERIFICAÇÃO DE MATCH - ITENS QUE REQUEREM ATENÇÃO');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB(self::RED);

        $sheet->setCellValue('A2', 'Lista de recursos onde o preço CSP não foi encontrado na API Microsoft ou requer validação manual');
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('666666');

        // Cabeçalhos
        $headers = ['#', 'Recurso', 'Serviço', 'MeterID', 'Custo MOSP', 'Status', 'Motivo', 'Ação Sugerida'];
        $col = 'A';
        $headerRow = 4;
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $headerRow, $header);
            $sheet->getStyle($col . $headerRow)->getFont()->setBold(true)->setSize(10);
            $sheet->getStyle($col . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::RED);
            $sheet->getStyle($col . $headerRow)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        // Filtrar itens problemáticos
        $row = 5;
        $count = 1;
        foreach ($results['results'] as $r) {
            $needsAttention = false;
            $status = '';
            $reason = '';
            $action = '';

            if (!$r['priceFound']) {
                $needsAttention = true;
                $status = 'SEM MATCH';
                $reason = 'MeterID não encontrado na API de preços CSP';
                $action = 'Verificar SKU manualmente no Partner Center';
            } elseif ($r['differencePercent'] !== null && abs($r['differencePercent']) > 30) {
                $needsAttention = true;
                $status = 'VARIAÇÃO ALTA';
                $reason = 'Diferença de preço > 30% entre MOSP e CSP';
                $action = 'Confirmar se o SKU mapeado está correto';
            } elseif ($r['costCsp'] === null || $r['costCsp'] == 0) {
                $needsAttention = true;
                $status = 'CUSTO ZERO';
                $reason = 'Custo CSP retornou zero ou nulo';
                $action = 'Verificar disponibilidade do serviço no CSP';
            }

            if ($needsAttention) {
                $sheet->setCellValue('A' . $row, $count);
                $sheet->setCellValue('B' . $row, $r['resourceName']);
                $sheet->setCellValue('C' . $row, $r['meterCategory'] ?: $r['serviceFamily']);
                $sheet->setCellValue('D' . $row, $r['meterId']);
                $sheet->setCellValue('E' . $row, $r['costMosp']);
                $sheet->setCellValue('F' . $row, $status);
                $sheet->setCellValue('G' . $row, $reason);
                $sheet->setCellValue('H' . $row, $action);

                $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('$#,##0.00');
                
                // Colorir status
                $statusColor = match($status) {
                    'SEM MATCH' => self::RED_BG,
                    'VARIAÇÃO ALTA' => self::YELLOW_BG,
                    'CUSTO ZERO' => self::YELLOW_BG,
                    default => self::GRAY_BG
                };
                $sheet->getStyle('F' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($statusColor);
                $sheet->getStyle('F' . $row)->getFont()->setBold(true);

                $count++;
                $row++;
            }
        }

        // Resumo no final
        $row += 2;
        $totalProblems = $count - 1;
        $sheet->setCellValue('A' . $row, 'Total de itens que requerem atenção: ' . $totalProblems);
        $sheet->mergeCells('A' . $row . ':H' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A' . $row)->getFont()->getColor()->setRGB($totalProblems > 0 ? self::RED : self::GREEN_TEXT);

        // Ajustar larguras
        $widths = ['A' => 6, 'B' => 28, 'C' => 20, 'D' => 38, 'E' => 14, 'F' => 15, 'G' => 40, 'H' => 35];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->freezePane('A5');
    }

    /**
     * Aba Por Serviço
     */
    private function createByServiceSheet(Spreadsheet $spreadsheet, array $results): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Por Servico');
        $summary = $results['summary'];

        // Título
        $sheet->setCellValue('A1', 'ANÁLISE POR SERVIÇO');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB(self::TEAL);

        // Cabeçalhos
        $headers = ['Serviço', 'Qtd Linhas', 'Custo MOSP', 'Custo CSP', 'Economia', '% do Total', 'Match %'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '3', $header);
            $sheet->getStyle($col . '3')->getFont()->setBold(true)->setSize(10);
            $sheet->getStyle($col . '3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TEAL);
            $sheet->getStyle($col . '3')->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        // Dados por serviço
        $row = 4;
        $byService = $summary['byService'] ?? [];
        
        // Ordenar por custo CSP decrescente
        uasort($byService, fn($a, $b) => ($b['costCsp'] ?? 0) <=> ($a['costCsp'] ?? 0));

        foreach ($byService as $serviceName => $data) {
            $percentTotal = $summary['totalCsp'] > 0 ? ($data['costCsp'] / $summary['totalCsp']) * 100 : 0;
            $savings = ($data['costMosp'] ?? 0) - ($data['costCsp'] ?? 0);
            
            // Calcular match % para este serviço
            $matchCount = 0;
            $totalCount = 0;
            foreach ($results['results'] as $r) {
                $svc = $r['meterCategory'] ?: $r['serviceFamily'];
                if ($svc === $serviceName) {
                    $totalCount++;
                    if ($r['priceFound']) {
                        $matchCount++;
                    }
                }
            }
            $matchPercent = $totalCount > 0 ? round(($matchCount / $totalCount) * 100, 0) : 0;

            $sheet->setCellValue('A' . $row, $serviceName);
            $sheet->setCellValue('B' . $row, $data['count']);
            $sheet->setCellValue('C' . $row, $data['costMosp'] ?? 0);
            $sheet->setCellValue('D' . $row, $data['costCsp'] ?? 0);
            $sheet->setCellValue('E' . $row, $savings);
            $sheet->setCellValue('F' . $row, round($percentTotal, 1) . '%');
            $sheet->setCellValue('G' . $row, $matchPercent . '%');

            // Formatar números
            $sheet->getStyle('C' . $row . ':E' . $row)->getNumberFormat()->setFormatCode('$#,##0.00');
            
            // Colorir economia
            if ($savings > 0) {
                $sheet->getStyle('E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GREEN_BG);
                $sheet->getStyle('E' . $row)->getFont()->getColor()->setRGB(self::GREEN_TEXT);
            } elseif ($savings < 0) {
                $sheet->getStyle('E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::RED_BG);
                $sheet->getStyle('E' . $row)->getFont()->getColor()->setRGB(self::RED_TEXT);
            }

            // Colorir match %
            if ($matchPercent >= 90) {
                $sheet->getStyle('G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GREEN_BG);
            } elseif ($matchPercent >= 70) {
                $sheet->getStyle('G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::YELLOW_BG);
            } else {
                $sheet->getStyle('G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::RED_BG);
            }

            $row++;
        }

        // Linha de total
        $lastDataRow = $row - 1;
        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->setCellValue('B' . $row, '=SUM(B4:B' . $lastDataRow . ')');
        $sheet->setCellValue('C' . $row, '=SUM(C4:C' . $lastDataRow . ')');
        $sheet->setCellValue('D' . $row, '=SUM(D4:D' . $lastDataRow . ')');
        $sheet->setCellValue('E' . $row, '=SUM(E4:E' . $lastDataRow . ')');
        $sheet->setCellValue('F' . $row, '100%');
        
        $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TEAL);
        $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('C' . $row . ':E' . $row)->getNumberFormat()->setFormatCode('$#,##0.00');

        // Ajustar larguras
        $widths = ['A' => 30, 'B' => 12, 'C' => 16, 'D' => 16, 'E' => 16, 'F' => 12, 'G' => 12];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->freezePane('A4');
    }

    /**
     * Helper para adicionar linha de informação
     */
    private function addInfoRow($sheet, int $row, string $label, string $value): void
    {
        $sheet->setCellValue('A' . $row, $label);
        $sheet->setCellValue('B' . $row, $value);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle('A' . $row)->getFont()->getColor()->setRGB('666666');
        $sheet->getStyle('B' . $row)->getFont()->setSize(10);
    }
}
