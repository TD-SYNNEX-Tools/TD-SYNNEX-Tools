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
use Mpdf\Mpdf;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Style\Color as PptColor;
use PhpOffice\PhpPresentation\Style\Alignment as PptAlignment;
use PhpOffice\PhpPresentation\Style\Fill as PptFill;
use PhpOffice\PhpPresentation\Style\Border as PptBorder;
use PhpOffice\PhpPresentation\Style\Bullet;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Pie3D;
use PhpOffice\PhpPresentation\Shape\Chart\Series;
use PhpOffice\PhpPresentation\Shape\Table;
use PhpOffice\PhpPresentation\Slide\Background\Color as BackgroundColor;

/**
 * Classe responsável por gerar relatórios em PDF, Excel e PowerPoint
 */
class ReportGenerator
{
    private string $reportsDir;
    private ?string $logoPath;
    private array $companyInfo;

    /**
     * Construtor
     * 
     * @param string $reportsDir Diretório para salvar os relatórios
     * @param string|null $logoPath Caminho para o logo da empresa
     */
    public function __construct(string $reportsDir, ?string $logoPath = null)
    {
        $this->reportsDir = $reportsDir;
        $this->logoPath = $logoPath;
        $this->companyInfo = [
            'name' => 'Análise de Migração Azure',
            'subtitle' => 'Relatório de Viabilidade'
        ];

        if (!is_dir($this->reportsDir)) {
            mkdir($this->reportsDir, 0755, true);
        }
    }

    /**
     * Define informações da empresa/cliente
     */
    public function setCompanyInfo(array $info): void
    {
        $this->companyInfo = array_merge($this->companyInfo, $info);
    }

    /**
     * Gera relatório em PDF
     * 
     * @param array $analysisResults Resultados da análise
     * @param string|null $clientName Nome do cliente
     * @param array $customNotes Notas personalizadas por recurso
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    public function generatePDF(array $analysisResults, ?string $clientName = null, array $customNotes = []): array
    {
        set_time_limit(0);
        try {
            // Aumenta limites para datasets grandes
            ini_set('pcre.backtrack_limit', '5000000');
            ini_set('pcre.recursion_limit', '500000');
            ini_set('memory_limit', '512M');

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 20,
                'margin_bottom' => 20,
                'default_font' => 'Arial',
                'shrink_tables_to_fit' => 1,
                'use_kwt' => true,
            ]);

            $mpdf->shrink_tables_to_fit = 1;
            $mpdf->simpleTables = true;
            $mpdf->packTableData = true;

            $mpdf->SetTitle('Relatório de Análise de Migração Azure');
            $mpdf->SetAuthor($this->companyInfo['name'] ?? 'Azure Migration Analyzer');

            // 1. Escreve CSS separadamente (evita PCRE no CSS)
            $mpdf->WriteHTML($this->generatePDFStyles(), \Mpdf\HTMLParserMode::HEADER_CSS);

            // 2. Escreve capa + resumo + recomendações + tabela de não migráveis
            $mpdf->WriteHTML($this->generatePDFHeader($analysisResults, $clientName, $customNotes));

            // 3. Cabeçalho da tabela detalhada
            $mpdf->WriteHTML($this->generatePDFTableHeader());

            // 4. Escreve as linhas da tabela em lotes de 500 para evitar o limite PCRE
            $results = $analysisResults['results'];
            $chunks = array_chunk($results, 500);
            foreach ($chunks as $chunk) {
                $mpdf->WriteHTML($this->generatePDFTableRows($chunk, $customNotes));
            }

            // 5. Fecha tabela + legenda + rodapé
            $mpdf->WriteHTML($this->generatePDFFooter());

            $filename = 'relatorio_migracao_' . date('Y-m-d_H-i-s') . '.pdf';
            $filepath = $this->reportsDir . DIRECTORY_SEPARATOR . $filename;

            $mpdf->Output($filepath, 'F');

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
                'error' => 'Erro ao gerar PDF: ' . $e->getMessage()
            ];
        }
    }

    /**
     * CSS separado para ser escrito via HEADER_CSS mode (não passa pelo PCRE do body)
     */
    private function generatePDFStyles(): string
    {
        return '
            @page { margin: 0; padding: 0; }
            body { font-family: "Segoe UI","Helvetica Neue",Arial,sans-serif; color: #333; margin: 0; padding: 0; }
            table { border: 0 !important; border-collapse: collapse; border-spacing: 0; }
            table td, table th { border: 0 !important; }
            tr, thead, tbody, tfoot { border: 0 !important; }
            .bg-blue { background-color: #08BED5; }
            .bg-teal { background-color: #005758; }
            .content-wrapper { padding: 40px 50px; }
            .header-container { border-bottom: 2px solid #08BED5; padding-bottom: 20px; margin-bottom: 40px; }
            .header-title { color: #005758; font-size: 20px; font-weight: bold; text-transform: uppercase; }
            .section-title { color: #005758; font-size: 16px; font-weight: 800; text-transform: uppercase;
                letter-spacing: 0.5px; border-bottom: 1px solid #eee; padding-bottom: 10px;
                margin-top: 40px; margin-bottom: 20px; }
            .summary-card { padding: 15px 10px; border-radius: 4px; text-align: center; background: #fff; border: 1px solid #e8e8e8; }
            .card-movable { border-left: 5px solid #82C341; }
            .card-restrictions { border-left: 5px solid #FFD100; }
            .card-not-movable { border-left: 5px solid #D9272E; }
            .card-total { border-left: 5px solid #08BED5; background: #f9fcfd; }
            .card-number { font-size: 28px; font-weight: 800; line-height: 1; margin-bottom: 2px; }
            .card-percent { font-size: 12px; font-weight: 600; margin-bottom: 8px; opacity: 0.9; }
            .card-label { font-size: 8px; text-transform: uppercase; color: #888; letter-spacing: 0.5px;
                border-top: 1px solid #eee; padding-top: 8px; margin-top: 5px; }
            .text-green { color: #2e7d32; }
            .text-yellow { color: #b38600; }
            .text-red { color: #c62828; }
            .rec-box { padding: 15px 20px; margin-bottom: 15px; border-left: 4px solid #ccc;
                background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); page-break-inside: avoid; }
            .rec-title { font-weight: bold; font-size: 11px; margin-bottom: 5px; display: block;
                color: #005758; text-transform: uppercase; }
            .rec-green { border-left-color: #82C341; }
            .rec-yellow { border-left-color: #FFD100; }
            .rec-red { border-left-color: #D9272E; }
            .rec-blue { border-left-color: #08BED5; }
            .data-table { width: 100%; border-collapse: collapse; font-size: 9px; border: none; }
            .data-table th { background-color: #005758; color: #fff; font-weight: 600;
                text-transform: uppercase; padding: 12px 10px; text-align: left; letter-spacing: 0.5px; }
            .data-table td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: middle;
                line-height: 1.4; color: #444; }
            .data-table tr:nth-child(even) { background-color: #f8fcfd; }
            .status-pill { display: inline-block; padding: 2px 6px; border-radius: 3px;
                font-size: 8px; font-weight: bold; text-transform: uppercase; }
            .pill-green { background: #e8f5e9; color: #2e7d32; }
            .pill-yellow { background: #fffde7; color: #f57f17; }
            .pill-red { background: #ffebee; color: #c62828; }
            .pill-gray { background: #f5f5f5; color: #616161; }
            .cell-type { font-family: monospace; color: #005758; }
            .cell-note { background: #f0f9ff; font-style: italic; }
            .footer { position: fixed; bottom: 0; left: 0; right: 0; padding: 15px 50px;
                border-top: 1px solid #eee; font-size: 8px; color: #999; text-align: right; background: white; }
        ';
    }

    /**
     * Capa + páginas de sumário + recomendações + não migráveis (sem a tabela detalhada)
     */
    private function generatePDFHeader(array $analysisResults, ?string $clientName, array $customNotes = []): string
    {
        $summary = $analysisResults['summary'];
        $results = $analysisResults['results'];
        $recommendations = $analysisResults['recommendations'] ?? [];

        // --- COVER PAGE ---
        $html = '
        <div style="position: absolute; left: 0; top: 0; width: 42px; height: 100%; background-color: #005758;"></div>
        <div style="margin-left: 70px; padding-top: 120px;">
            <div style="font-size: 36px; font-weight: bold; color: #262626; line-height: 1.15; margin-bottom: 5px;">RELATÓRIO</div>
            <div style="font-size: 36px; font-weight: bold; color: #262626; line-height: 1.15; margin-bottom: 25px;">EXECUTIVO</div>
            <div style="font-size: 24px; font-weight: bold; color: #005758; margin-bottom: 8px;">Azure Migration</div>
            <div style="font-size: 18px; color: #737373; margin-bottom: 25px;">Analyzer</div>
            <div style="width: 170px; height: 3px; background-color: #005758; margin-bottom: 35px;"></div>
        </div>
        <div style="margin-left: 70px; margin-right: 80px; background-color: #f5f5f7; padding: 0;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td width="6" style="background-color: #005758;"></td>
                    <td style="padding: 25px 30px;">
                        <div style="font-size: 12px; color: #262626; line-height: 1.6; margin-bottom: 25px;">
                            Análise técnica de viabilidade para movimentação<br>
                            de recursos Azure entre assinaturas e resource groups
                        </div>
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td width="50%" style="vertical-align: top; padding-bottom: 20px;">
                                    <div style="font-size: 10px; color: #737373; margin-bottom: 3px;">Data de Emissão</div>
                                    <div style="font-size: 11px; color: #262626; font-weight: bold;">' . date('d \d\e F \d\e Y') . '</div>
                                </td>
                                <td width="50%" style="vertical-align: top; padding-bottom: 20px;">
                                    <div style="font-size: 10px; color: #737373; margin-bottom: 3px;">Total de Recursos</div>
                                    <div style="font-size: 11px; color: #262626; font-weight: bold;">' . $summary['total'] . '</div>
                                </td>
                            </tr>
                            <tr>
                                <td width="50%" style="vertical-align: top;">
                                    <div style="font-size: 10px; color: #737373; margin-bottom: 3px;">Cliente</div>
                                    <div style="font-size: 11px; color: #262626; font-weight: bold;">' . ($clientName ? htmlspecialchars($clientName) : 'Não identificado') . '</div>
                                </td>
                                <td width="50%" style="vertical-align: top;">
                                    <div style="font-size: 10px; color: #737373; margin-bottom: 3px;">Taxa de Migração</div>
                                    <div style="font-size: 11px; color: #262626; font-weight: bold;">' . $summary['movablePercent'] . '% migrável</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        <pagebreak />';

        // --- CONTENT PAGES ---
        $html .= '<div class="content-wrapper">';
        $html .= '
        <div class="header-container">
            <table width="100%" border="0" cellpadding="0" cellspacing="0" style="border:0;border-collapse:collapse;">
                <tr style="border:0;">
                    <td width="70%"><div class="header-title">Relatório de Migração Azure</div></td>
                    <td width="30%" align="right" style="color: #08BED5; font-size: 10px;">' . date('d/m/Y') . '</td>
                </tr>
            </table>
        </div>';

        $html .= '<div class="section-title">Resumo Executivo</div>';

        $viabilidade      = $summary['movablePercent'] >= 80 ? 'ALTA' : ($summary['movablePercent'] >= 50 ? 'MÉDIA' : 'BAIXA');
        $viabilidadeColor = $summary['movablePercent'] >= 80 ? '#2e7d32' : ($summary['movablePercent'] >= 50 ? '#f57f17' : '#c62828');

        $html .= '
        <div style="background: #f8fcfd; padding: 20px; margin-bottom: 25px; border-left: 4px solid #005758;">
            <div style="font-size: 11px; color: #444; line-height: 1.6; margin-bottom: 15px;">
                Esta análise técnica avaliou <strong>' . $summary['total'] . ' recursos Azure</strong> quanto à viabilidade
                de migração entre assinaturas e/ou resource groups. A taxa de sucesso esperada é de
                <strong style="color: ' . $viabilidadeColor . ';">' . $summary['movablePercent'] . '%</strong>.
            </div>
            <table border="0" cellpadding="0" cellspacing="0" style="border:0; width:100%;">
                <tr style="border:0;">
                    <td style="border:0; width: 120px; vertical-align: middle;">
                        <span style="font-size: 10px; color: #666; text-transform: uppercase;">Viabilidade Geral:</span>
                    </td>
                    <td style="border:0; vertical-align: middle;">
                        <span style="background: ' . $viabilidadeColor . '; color: #fff; padding: 4px 12px;
                            font-size: 10px; font-weight: bold; letter-spacing: 1px;">' . $viabilidade . '</span>
                    </td>
                </tr>
            </table>
        </div>';

        // Recomendações
        if (!empty($recommendations)) {
            $html .= '<div class="section-title">Recomendações e Insights</div>';
            foreach ($recommendations as $rec) {
                $typeClass = match($rec['type'] ?? 'info') {
                    'success' => 'rec-green',
                    'warning' => 'rec-yellow',
                    'danger'  => 'rec-red',
                    default   => 'rec-blue'
                };
                $html .= '<div class="rec-box ' . $typeClass . '">';
                $html .= '<span class="rec-title">' . htmlspecialchars($rec['title']) . '</span>';
                $html .= htmlspecialchars($rec['message']);
                $html .= '</div>';
            }
        }

        // Não migráveis (tabela compacta - geralmente bem menor que 5000)
        $notMovable = array_filter($results, fn($r) => $r['status'] === 'not-movable');
        if (!empty($notMovable)) {
            $html .= '<div class="section-title" style="color: #D9272E; border-bottom-color: #D9272E;">Recursos Não Migráveis (Bloqueantes)</div>';
            $html .= '<table class="data-table" border="0" cellpadding="0" cellspacing="0" style="border:0;border-collapse:collapse;">';
            $html .= '<thead style="border:0;"><tr style="border:0;">
                    <th width="5%">#</th>
                    <th width="30%">Nome do Recurso</th>
                    <th width="30%">Tipo</th>
                    <th width="35%">Motivo / Observação</th>
                </tr></thead><tbody style="border:0;">';
            $i = 1;
            foreach ($notMovable as $nm) {
                $html .= '<tr>
                    <td style="font-weight:bold;color:#D9272E;">' . $i++ . '</td>
                    <td>' . htmlspecialchars($nm['resourceName']) . '</td>
                    <td style="font-family:monospace;color:#666;">' . htmlspecialchars($nm['resourceType']) . '</td>
                    <td>' . htmlspecialchars($nm['notes'] ?: 'Não suportado para migração') . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '<div class="section-title">Detalhamento Completo dos Recursos</div>';

        return $html;
    }

    /**
     * Abre a tabela detalhada (thead)
     */
    private function generatePDFTableHeader(): string
    {
        return '<table class="data-table" border="0" cellpadding="0" cellspacing="0" style="border:0;border-collapse:collapse;">
            <thead style="border:0;"><tr style="border:0;">
                <th width="18%">Nome do Recurso</th>
                <th width="22%">Tipo</th>
                <th width="12%">Grupo de Recursos</th>
                <th width="12%">Status</th>
                <th width="18%">Observações</th>
                <th width="18%">Notas Personalizadas</th>
            </tr></thead>
            <tbody style="border:0;">';
    }

    /**
     * Gera as linhas de um lote de recursos (sem abrir/fechar a tabela)
     */
    private function generatePDFTableRows(array $rows, array $customNotes): string
    {
        $html = '';
        foreach ($rows as $result) {
            $statusClass = match($result['status']) {
                'movable'                   => 'pill-green',
                'movable-with-restrictions' => 'pill-yellow',
                'not-movable'               => 'pill-red',
                default                     => 'pill-gray'
            };
            $icon = match($result['status']) {
                'movable'                   => '&#10004;',
                'movable-with-restrictions' => '&#9888;',
                'not-movable'               => '&#10008;',
                default                     => '?'
            };
            $label = match($result['status']) {
                'movable'                   => 'Migrável',
                'movable-with-restrictions' => 'Com Restrições',
                'not-movable'               => 'Não Migrável',
                default                     => 'Desconhecido'
            };
            $resourceId  = base64_encode($result['resourceName'] . '|' . $result['resourceType'] . '|' . $result['resourceGroup']);
            $customNote  = $customNotes[$resourceId] ?? '';

            $html .= '<tr>
                <td>' . htmlspecialchars($result['resourceName']) . '</td>
                <td class="cell-type">' . htmlspecialchars($result['resourceType']) . '</td>
                <td>' . htmlspecialchars($result['resourceGroup']) . '</td>
                <td><span class="status-pill ' . $statusClass . '">' . $icon . ' ' . $label . '</span></td>
                <td>' . ($result['notes'] ? htmlspecialchars($result['notes']) : '-') . '</td>
                <td' . ($customNote ? ' class="cell-note"' : '') . '>'
                    . ($customNote ? htmlspecialchars($customNote) : '-') . '</td>
            </tr>';
        }
        return $html;
    }

    /**
     * Fecha a tabela detalhada + legenda + rodapé
     */
    private function generatePDFFooter(): string
    {
        return '</tbody></table>
        <div style="margin-top:30px;padding:15px;background:#f8fcfd;border:1px solid #e0e0e0;font-size:9px;">
            <strong style="color:#005758;text-transform:uppercase;">Legenda:</strong><br><br>
            <span style="color:#2e7d32;">&#10004; Migrável</span> — Recurso pode ser movido entre resource groups e assinaturas<br>
            <span style="color:#f57f17;">&#9888; Com Restrições</span> — Recurso pode ser movido, mas com limitações específicas<br>
            <span style="color:#c62828;">&#10008; Não Migrável</span> — Recurso não suporta migração e precisará ser recriado
        </div>
        </div>
        <div class="footer">Gerado por Azure Migration Analyzer • TD SYNNEX • ' . date('Y') . '</div>';
    }

    /**
     * Gera relatório em Excel
     * 
     * @param array $analysisResults Resultados da análise
     * @param string|null $clientName Nome do cliente
     * @param array $customNotes Notas personalizadas por recurso
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    public function generateExcel(array $analysisResults, ?string $clientName = null, array $customNotes = []): array
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        try {
            $spreadsheet = new Spreadsheet();
            
            // Aba de Resumo
            $this->createSummarySheet($spreadsheet, $analysisResults, $clientName);
            
            // Aba de Detalhes
            $this->createDetailsSheet($spreadsheet, $analysisResults, $customNotes);
            
            // Aba de Recursos por Provider
            $this->createProviderSheet($spreadsheet, $analysisResults);

            // Define a primeira aba como ativa
            $spreadsheet->setActiveSheetIndex(0);

            $filename = 'relatorio_migracao_' . date('Y-m-d_H-i-s') . '.xlsx';
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
     * Cria a aba de resumo
     */
    private function createSummarySheet(Spreadsheet $spreadsheet, array $analysisResults, ?string $clientName): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumo Executivo');
        $summary = $analysisResults['summary'];

        // Cores TD SYNNEX
        $tealColor = '005758';
        $blueColor = '08BED5';
        $greenColor = '82C341';
        $yellowColor = 'FFD100';
        $redColor = 'D9272E';
        $grayColor = 'F5F5F7';
        $darkGray = '333333';

        // ========== CABEÇALHO ==========
        $sheet->setCellValue('A1', 'RELATÓRIO EXECUTIVO');
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(22)->setName('Segoe UI');
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB($tealColor);
        $sheet->getRowDimension(1)->setRowHeight(35);

        $sheet->setCellValue('A2', 'Azure Migration Analyzer');
        $sheet->mergeCells('A2:I2');
        $sheet->getStyle('A2')->getFont()->setSize(12)->setName('Segoe UI');
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('666666');
        $sheet->getRowDimension(2)->setRowHeight(20);

        // Linha decorativa
        $sheet->setCellValue('A3', '');
        $sheet->mergeCells('A3:I3');
        $sheet->getStyle('A3:I3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($blueColor);
        $sheet->getRowDimension(3)->setRowHeight(4);

        // ========== INFORMAÇÕES DO CLIENTE ==========
        $row = 5;
        $sheet->setCellValue('A' . $row, 'INFORMAÇÕES GERAIS');
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11)->setName('Segoe UI');
        $sheet->getStyle('A' . $row)->getFont()->getColor()->setRGB($tealColor);
        $sheet->getStyle('A' . $row . ':I' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($blueColor);
        $row += 2;

        // Grid de informações
        $infoStyle = function($sheet, $labelCell, $valueCell, $label, $value, $tealColor) {
            $sheet->setCellValue($labelCell, $label);
            $sheet->setCellValue($valueCell, $value);
            $sheet->getStyle($labelCell)->getFont()->setBold(true)->setSize(9)->setName('Segoe UI');
            $sheet->getStyle($labelCell)->getFont()->getColor()->setRGB('888888');
            $sheet->getStyle($valueCell)->getFont()->setSize(10)->setName('Segoe UI');
        };

        $infoStyle($sheet, 'A' . $row, 'B' . $row, 'Cliente:', $clientName ?: 'Não identificado', $tealColor);
        $infoStyle($sheet, 'D' . $row, 'E' . $row, 'Data da Análise:', date('d/m/Y H:i'), $tealColor);
        $row++;
        $infoStyle($sheet, 'A' . $row, 'B' . $row, 'Total de Recursos:', $summary['total'], $tealColor);
        $infoStyle($sheet, 'D' . $row, 'E' . $row, 'Taxa de Migração:', $summary['movablePercent'] . '%', $tealColor);
        $row += 2;

        // ========== RESUMO EXECUTIVO ==========
        $sheet->setCellValue('A' . $row, 'ANÁLISE DE VIABILIDADE');
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11)->setName('Segoe UI');
        $sheet->getStyle('A' . $row)->getFont()->getColor()->setRGB($tealColor);
        $sheet->getStyle('A' . $row . ':I' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($blueColor);
        $row += 2;

        // Cards de estatísticas
        $cardData = [
            ['label' => 'Recursos Migráveis', 'value' => $summary['movable'], 'percent' => $summary['movablePercent'] . '%', 'color' => $greenColor],
            ['label' => 'Com Restrições', 'value' => $summary['movableWithRestrictions'], 'percent' => $summary['movableWithRestrictionsPercent'] . '%', 'color' => $yellowColor],
            ['label' => 'Não Migráveis', 'value' => $summary['notMovable'], 'percent' => $summary['notMovablePercent'] . '%', 'color' => $redColor],
            ['label' => 'Desconhecidos', 'value' => $summary['unknown'] ?? 0, 'percent' => round((($summary['unknown'] ?? 0) / max(1, $summary['total'])) * 100, 1) . '%', 'color' => $grayColor],
            ['label' => 'Total Analisado', 'value' => $summary['total'], 'percent' => '100%', 'color' => $blueColor],
        ];

        $col = 'A';
        foreach ($cardData as $card) {
            // Número grande
            $sheet->setCellValue($col . $row, $card['value']);
            $sheet->getStyle($col . $row)->getFont()->setBold(true)->setSize(28)->setName('Segoe UI');
            $sheet->getStyle($col . $row)->getFont()->getColor()->setRGB($card['color']);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Percentual
            $sheet->setCellValue($col . ($row + 1), $card['percent']);
            $sheet->getStyle($col . ($row + 1))->getFont()->setBold(true)->setSize(11)->setName('Segoe UI');
            $sheet->getStyle($col . ($row + 1))->getFont()->getColor()->setRGB($card['color']);
            $sheet->getStyle($col . ($row + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Label
            $sheet->setCellValue($col . ($row + 2), $card['label']);
            $sheet->getStyle($col . ($row + 2))->getFont()->setSize(9)->setName('Segoe UI');
            $sheet->getStyle($col . ($row + 2))->getFont()->getColor()->setRGB('666666');
            $sheet->getStyle($col . ($row + 2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Borda superior colorida
            $sheet->getStyle($col . $row)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK)->getColor()->setRGB($card['color']);
            
            // Background
            $sheet->getStyle($col . $row . ':' . $col . ($row + 2))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FAFAFA');
            
            $col = chr(ord($col) + 2); // Pula uma coluna
        }
        
        $row += 5;

        // ========== INDICADOR DE VIABILIDADE ==========
        $viabilidade = $summary['movablePercent'] >= 80 ? 'ALTA' : ($summary['movablePercent'] >= 50 ? 'MÉDIA' : 'BAIXA');
        $viabilidadeColor = $summary['movablePercent'] >= 80 ? $greenColor : ($summary['movablePercent'] >= 50 ? $yellowColor : $redColor);

        $sheet->setCellValue('A' . $row, 'VIABILIDADE GERAL:');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(10)->setName('Segoe UI');
        $sheet->setCellValue('B' . $row, $viabilidade);
        $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setSize(12)->setName('Segoe UI');
        $sheet->getStyle('B' . $row)->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($viabilidadeColor);
        $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ========== AJUSTES FINAIS ==========
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(5);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(5);
        $sheet->getColumnDimension('G')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(5);
        $sheet->getColumnDimension('I')->setWidth(18);

        // Freeze pane
        $sheet->freezePane('A4');
    }

    /**
     * Cria a aba de detalhes
     */
    private function createDetailsSheet(Spreadsheet $spreadsheet, array $analysisResults, array $customNotes = []): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Detalhamento');
        $results = $analysisResults['results'];

        // Cores TD SYNNEX
        $tealColor = '005758';
        $blueColor = '08BED5';
        $greenColor = 'D4EDDA';
        $greenText = '155724';
        $yellowColor = 'FFF3CD';
        $yellowText = '856404';
        $redColor = 'F8D7DA';
        $redText = '721C24';
        $grayColor = 'F8F9FA';
        $noteBlueColor = 'E3F2FD';

        // Cabeçalhos
        $headers = ['Nome do Recurso', 'Tipo do Recurso', 'Resource Group', 'Localização', 'Status', 'Move RG', 'Move Sub', 'Move Região', 'Observações', 'Notas Personalizadas'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true)->setSize(10)->setName('Segoe UI');
            $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($tealColor);
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($col . '1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('FFFFFF');
            $col++;
        }
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Dados — escreve apenas valores no loop (sem styling por linha)
        $row = 2;
        foreach ($results as $result) {
            $sheet->setCellValue('A' . $row, $result['resourceName']);
            $sheet->setCellValue('B' . $row, $result['resourceType']);
            $sheet->setCellValue('C' . $row, $result['resourceGroup']);
            $sheet->setCellValue('D' . $row, $result['location']);
            $sheet->setCellValue('E' . $row, match($result['status']) {
                'movable'                   => 'Migrável',
                'movable-with-restrictions' => 'Com Restrições',
                'not-movable'               => 'Não Migrável',
                default                     => 'Desconhecido'
            });
            $sheet->setCellValue('F' . $row, $result['resourceGroupMove'] ? 'Sim' : 'Não');
            $sheet->setCellValue('G' . $row, $result['subscriptionMove'] ? 'Sim' : 'Não');
            $sheet->setCellValue('H' . $row, $result['regionMove'] ? 'Sim' : 'Não');
            $sheet->setCellValue('I' . $row, $result['notes']);
            $resourceId = base64_encode($result['resourceName'] . '|' . $result['resourceType'] . '|' . $result['resourceGroup']);
            $sheet->setCellValue('J' . $row, $customNotes[$resourceId] ?? '');
            $row++;
        }

        $lastDataRow = $row - 1;

        // Estilo base para todo o range de dados — 1 chamada em vez de 30.000
        $sheet->getStyle("A2:J{$lastDataRow}")->applyFromArray([
            'font'    => ['size' => 9, 'name' => 'Segoe UI'],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
        ]);
        $sheet->getStyle("F2:H{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Formatação condicional — avaliada pelo Excel no cliente (zero work para PHP)
        $condRules = [];

        // Verde: Migrável
        $c1 = new Conditional();
        $c1->setConditionType(Conditional::CONDITION_EXPRESSION);
        $c1->addCondition('$E2="Migrável"');
        $c1->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($greenColor);
        $c1->getStyle()->getFont()->getColor()->setRGB($greenText);
        $condRules[] = $c1;

        // Amarelo: Com Restrições
        $c2 = new Conditional();
        $c2->setConditionType(Conditional::CONDITION_EXPRESSION);
        $c2->addCondition('$E2="Com Restrições"');
        $c2->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($yellowColor);
        $c2->getStyle()->getFont()->getColor()->setRGB($yellowText);
        $condRules[] = $c2;

        // Vermelho: Não Migrável
        $c3 = new Conditional();
        $c3->setConditionType(Conditional::CONDITION_EXPRESSION);
        $c3->addCondition('$E2="Não Migrável"');
        $c3->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($redColor);
        $c3->getStyle()->getFont()->getColor()->setRGB($redText);
        $condRules[] = $c3;

        // Cinza: Desconhecido
        $c4 = new Conditional();
        $c4->setConditionType(Conditional::CONDITION_EXPRESSION);
        $c4->addCondition('$E2="Desconhecido"');
        $c4->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($grayColor);
        $condRules[] = $c4;

        $sheet->setConditionalStyles("A2:J{$lastDataRow}", $condRules);

        // Ajusta largura das colunas
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(45);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(10);
        $sheet->getColumnDimension('H')->setWidth(12);
        $sheet->getColumnDimension('I')->setWidth(40);
        $sheet->getColumnDimension('J')->setWidth(40);

        // Filtro automático e freeze
        $sheet->setAutoFilter('A1:J' . $lastDataRow);
        $sheet->freezePane('A2');
    }

    /**
     * Cria a aba de recursos por provider
     */
    private function createProviderSheet(Spreadsheet $spreadsheet, array $analysisResults): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Por Provider');
        $byProvider = $analysisResults['summary']['byProvider'] ?? [];

        // Cores TD SYNNEX
        $tealColor = '005758';
        $blueColor = '08BED5';
        $greenColor = '82C341';
        $redColor = 'D9272E';

        // Título
        $sheet->setCellValue('A1', 'ANÁLISE POR PROVIDER');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setName('Segoe UI');
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB($tealColor);
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Linha decorativa
        $sheet->setCellValue('A2', '');
        $sheet->mergeCells('A2:E2');
        $sheet->getStyle('A2:E2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($blueColor);
        $sheet->getRowDimension(2)->setRowHeight(3);

        // Cabeçalhos
        $headers = ['Provider', 'Total', 'Migráveis', 'Não Migráveis', '% Migrável'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '4', $header);
            $sheet->getStyle($col . '4')->getFont()->setBold(true)->setSize(10)->setName('Segoe UI');
            $sheet->getStyle($col . '4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($tealColor);
            $sheet->getStyle($col . '4')->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($col . '4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $col++;
        }
        $sheet->getRowDimension(4)->setRowHeight(22);

        // Dados
        $row = 5;
        $alternate = false;
        
        // Ordenar por total (decrescente)
        uasort($byProvider, function($a, $b) {
            return $b['total'] - $a['total'];
        });

        foreach ($byProvider as $provider => $stats) {
            $percent = $stats['total'] > 0 ? round(($stats['movable'] / $stats['total']) * 100, 1) : 0;
            
            $sheet->setCellValue('A' . $row, $provider);
            $sheet->setCellValue('B' . $row, $stats['total']);
            $sheet->setCellValue('C' . $row, $stats['movable']);
            $sheet->setCellValue('D' . $row, $stats['notMovable']);
            $sheet->setCellValue('E' . $row, $percent . '%');

            // Estilo da linha
            $bgColor = $alternate ? 'F8F9FA' : 'FFFFFF';
            $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bgColor);
            $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setSize(9)->setName('Segoe UI');
            $sheet->getStyle('A' . $row . ':E' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E0E0E0');
            
            // Centralizar números
            $sheet->getStyle('B' . $row . ':E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Cor do percentual baseado no valor
            $percentColor = $percent >= 80 ? $greenColor : ($percent >= 50 ? 'F5A623' : $redColor);
            $sheet->getStyle('E' . $row)->getFont()->setBold(true);
            $sheet->getStyle('E' . $row)->getFont()->getColor()->setRGB($percentColor);

            // Barra de progresso visual na coluna E (como cor de fundo parcial)
            if ($percent > 0) {
                $barColor = $percent >= 80 ? 'E8F5E9' : ($percent >= 50 ? 'FFF8E1' : 'FFEBEE');
                $sheet->getStyle('E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($barColor);
            }

            $alternate = !$alternate;
            $row++;
        }

        // Linha de total
        $totalResources = array_sum(array_column($byProvider, 'total'));
        $totalMovable = array_sum(array_column($byProvider, 'movable'));
        $totalNotMovable = array_sum(array_column($byProvider, 'notMovable'));
        $totalPercent = $totalResources > 0 ? round(($totalMovable / $totalResources) * 100, 1) : 0;

        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->setCellValue('B' . $row, $totalResources);
        $sheet->setCellValue('C' . $row, $totalMovable);
        $sheet->setCellValue('D' . $row, $totalNotMovable);
        $sheet->setCellValue('E' . $row, $totalPercent . '%');
        $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true)->setSize(10)->setName('Segoe UI');
        $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($tealColor);
        $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('B' . $row . ':E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Ajusta largura das colunas
        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(14);

        // Freeze pane
        $sheet->freezePane('A5');
    }

    /**
     * Gera relatório em PowerPoint
     * 
     * @param array $analysisResults Resultados da análise
     * @param string|null $clientName Nome do cliente
     * @param array $customNotes Notas personalizadas por recurso
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    public function generatePowerPoint(array $analysisResults, ?string $clientName = null, array $customNotes = []): array
    {
        try {
            $presentation = new PhpPresentation();
            
            // Configurar formato widescreen (16:9)
            $presentation->getLayout()->setDocumentLayout(
                \PhpOffice\PhpPresentation\DocumentLayout::LAYOUT_SCREEN_16X9
            );
            
            // Configuração do documento
            $presentation->getDocumentProperties()
                ->setCreator('TD SYNNEX - Azure Migration Analyzer')
                ->setTitle('Análise de Migração Azure')
                ->setSubject('Relatório Executivo de Viabilidade')
                ->setDescription('Análise técnica de viabilidade para migração de recursos Azure')
                ->setCompany('TD SYNNEX');

            // Cores TD SYNNEX
            $tealColor = new PptColor('FF005758');
            $blueColor = new PptColor('FF08BED5');
            $greenColor = new PptColor('FF82C341');
            $yellowColor = new PptColor('FFFFD100');
            $redColor = new PptColor('FFD9272E');
            $whiteColor = new PptColor('FFFFFFFF');
            $darkColor = new PptColor('FF333333');
            $grayColor = new PptColor('FF6C757D');

            $summary = $analysisResults['summary'];
            $results = $analysisResults['results'];
            $recommendations = $analysisResults['recommendations'] ?? [];

            // Slide 1 - Capa
            $this->createCoverSlide($presentation, $clientName, $tealColor, $blueColor, $whiteColor);
            
            // Slide 2 - Resumo Executivo
            $this->createSummarySlide($presentation, $summary, $tealColor, $blueColor, $greenColor, $yellowColor, $redColor, $whiteColor, $darkColor);
            
            // Slide 3 - Gráfico de Distribuição
            $this->createChartSlide($presentation, $summary, $tealColor, $blueColor, $greenColor, $yellowColor, $redColor, $whiteColor);
            
            // Slide 4 - Recursos Não Migráveis
            $notMovable = array_filter($results, fn($r) => $r['status'] === 'not-movable');
            if (!empty($notMovable)) {
                $this->createBlockingResourcesSlide($presentation, $notMovable, $tealColor, $redColor, $whiteColor, $darkColor);
            }
            
            // Slide 5 - Recursos com Restrições
            $withRestrictions = array_filter($results, fn($r) => $r['status'] === 'movable-with-restrictions');
            if (!empty($withRestrictions)) {
                $this->createRestrictionsSlide($presentation, $withRestrictions, $tealColor, $yellowColor, $whiteColor, $darkColor);
            }
            
            // Slide 6 - Próximos Passos
            $this->createNextStepsSlide($presentation, $recommendations, $tealColor, $blueColor, $whiteColor, $darkColor);

            // Salva o arquivo
            $filename = 'apresentacao_migracao_' . date('Y-m-d_H-i-s') . '.pptx';
            $filepath = $this->reportsDir . DIRECTORY_SEPARATOR . $filename;
            
            $writer = IOFactory::createWriter($presentation, 'PowerPoint2007');
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
                'error' => 'Erro ao gerar PowerPoint: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cria o slide de capa - Design Executivo Minimalista
     */
    private function createCoverSlide(
        PhpPresentation $presentation, 
        ?string $clientName,
        PptColor $tealColor,
        PptColor $blueColor,
        PptColor $whiteColor
    ): void {
        $slide = $presentation->getActiveSlide();
        
        // Background branco clean
        $bgColor = new BackgroundColor();
        $bgColor->setColor($whiteColor);
        $slide->setBackground($bgColor);
        
        // Barra lateral esquerda (accent vertical)
        $accentBar = $slide->createRichTextShape();
        $accentBar->setHeight(540)->setWidth(8)->setOffsetX(0)->setOffsetY(0);
        $accentBar->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor($blueColor);
        
        // Área de conteúdo principal - centralizado verticalmente
        $contentY = 160;
        
        // Logo/Brand no topo esquerdo
        $brand = $slide->createRichTextShape();
        $brand->setHeight(35)->setWidth(300)->setOffsetX(80)->setOffsetY(40);
        $brandText = $brand->createTextRun('TD SYNNEX');
        $brandText->getFont()
            ->setBold(true)
            ->setSize(18)
            ->setColor($tealColor)
            ->setName('Segoe UI');
        
        // Label pequeno acima do título
        $label = $slide->createRichTextShape();
        $label->setHeight(25)->setWidth(530)->setOffsetX(80)->setOffsetY($contentY);
        $labelText = $label->createTextRun('RELATÓRIO EXECUTIVO');
        $labelText->getFont()
            ->setSize(11)
            ->setColor(new PptColor('FF999999'))
            ->setName('Segoe UI')
            ->setBold(true);
        
        // Título principal - mais compacto e elegante
        $title = $slide->createRichTextShape();
        $title->setHeight(75)->setWidth(900)->setOffsetX(80)->setOffsetY($contentY + 35);
        $title->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_LEFT);
        $title->getActiveParagraph()->setLineSpacing(110);
        
        $titleText = $title->createTextRun('Análise de Migração');
        $titleText->getFont()
            ->setBold(false)
            ->setSize(42)
            ->setColor(new PptColor('FF2C3E50'))
            ->setName('Segoe UI Light');
        
        // Subtítulo Azure - menor e mais sutil
        $subtitle = $slide->createRichTextShape();
        $subtitle->setHeight(60)->setWidth(900)->setOffsetX(80)->setOffsetY($contentY + 105);
        $subtitle->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_LEFT);
        
        $subtitleText = $subtitle->createTextRun('Microsoft Azure');
        $subtitleText->getFont()
            ->setBold(true)
            ->setSize(36)
            ->setColor($blueColor)
            ->setName('Segoe UI');
        
        // Linha sutil de separação
        $line = $slide->createLineShape(80, $contentY + 175, 320, $contentY + 175);
        $line->getBorder()->setColor(new PptColor('FFE0E0E0'))->setLineWidth(2);
        
        // Informações do cliente - mais clean
        $infoY = $contentY + 200;
        if ($clientName) {
            $clientLabel = $slide->createRichTextShape();
            $clientLabel->setHeight(20)->setWidth(100)->setOffsetX(80)->setOffsetY($infoY);
            $clientLabelText = $clientLabel->createTextRun('Cliente');
            $clientLabelText->getFont()
                ->setSize(10)
                ->setColor(new PptColor('FF999999'))
                ->setName('Segoe UI');
            
            $client = $slide->createRichTextShape();
            $client->setHeight(30)->setWidth(660)->setOffsetX(80)->setOffsetY($infoY + 20);
            $clientText = $client->createTextRun($clientName);
            $clientText->getFont()
                ->setSize(16)
                ->setColor(new PptColor('FF2C3E50'))
                ->setName('Segoe UI')
                ->setBold(true);
            $infoY += 60;
        }
        
        // Data
        $dateLabel = $slide->createRichTextShape();
        $dateLabel->setHeight(20)->setWidth(100)->setOffsetX(80)->setOffsetY($infoY);
        $dateLabelText = $dateLabel->createTextRun('Data');
        $dateLabelText->getFont()
            ->setSize(10)
            ->setColor(new PptColor('FF999999'))
            ->setName('Segoe UI');
        
        $date = $slide->createRichTextShape();
        $date->setHeight(30)->setWidth(400)->setOffsetX(80)->setOffsetY($infoY + 20);
        $dateText = $date->createTextRun(date('d/m/Y'));
        $dateText->getFont()
            ->setSize(16)
            ->setColor(new PptColor('FF2C3E50'))
            ->setName('Segoe UI');
        
        // Elemento decorativo geométrico no canto inferior direito
        $deco1 = $slide->createRichTextShape();
        $deco1->setHeight(100)->setWidth(100)->setOffsetX(820)->setOffsetY(420);
        $deco1->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor(new PptColor('FFF5F5F5'));
        
        $deco2 = $slide->createRichTextShape();
        $deco2->setHeight(60)->setWidth(60)->setOffsetX(860)->setOffsetY(460);
        $deco2->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor(new PptColor('FFE8F8FC'));
    }

    /**
     * Cria o slide de resumo executivo - Design Moderno com Cards
     */
    private function createSummarySlide(
        PhpPresentation $presentation,
        array $summary,
        PptColor $tealColor,
        PptColor $blueColor,
        PptColor $greenColor,
        PptColor $yellowColor,
        PptColor $redColor,
        PptColor $whiteColor,
        PptColor $darkColor
    ): void {
        $slide = $presentation->createSlide();
        
        // Background branco
        $bgColor = new BackgroundColor();
        $bgColor->setColor($whiteColor);
        $slide->setBackground($bgColor);
        
        // Header com linha de accent
        $accentLine = $slide->createLineShape(0, 0, 1280, 0);
        $accentLine->getBorder()->setColor($blueColor)->setLineWidth(4);
        
        // Título do slide - mais clean
        $title = $slide->createRichTextShape();
        $title->setHeight(50)->setWidth(1160)->setOffsetX(70)->setOffsetY(40);
        $titleText = $title->createTextRun('Resumo Executivo');
        $titleText->getFont()
            ->setBold(false)
            ->setSize(28)
            ->setColor(new PptColor('FF2C3E50'))
            ->setName('Segoe UI Light');
        
        // Linha sutil abaixo do título
        $line = $slide->createLineShape(70, 88, 320, 88);
        $line->getBorder()->setColor(new PptColor('FFE0E0E0'))->setLineWidth(2);
        
        // Cards de estatísticas - design moderno com sombra simulada
        $cards = [
            ['label' => 'Total de Recursos', 'value' => $summary['total'], 'color' => $blueColor, 'x' => 100, 'bgLight' => 'FFF8FBFD'],
            ['label' => 'Migráveis', 'value' => $summary['movable'], 'color' => $greenColor, 'x' => 380, 'bgLight' => 'FFF5FDF8'],
            ['label' => 'Com Restrições', 'value' => $summary['movableWithRestrictions'], 'color' => $yellowColor, 'x' => 660, 'bgLight' => 'FFFFFBF0'],
            ['label' => 'Não Migráveis', 'value' => $summary['notMovable'], 'color' => $redColor, 'x' => 940, 'bgLight' => 'FFFEF7F7'],
        ];
        
        foreach ($cards as $card) {
            // Sombra do card (simulada com shape cinza)
            $shadow = $slide->createRichTextShape();
            $shadow->setHeight(110)->setWidth(240)->setOffsetX($card['x'] + 3)->setOffsetY(123);
            $shadow->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor(new PptColor('FFF0F0F0'));
            
            // Card background principal
            $cardBg = $slide->createRichTextShape();
            $cardBg->setHeight(110)->setWidth(240)->setOffsetX($card['x'])->setOffsetY(120);
            $cardBg->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor(new PptColor($card['bgLight']));
            
            // Borda superior colorida (accent)
            $topAccent = $slide->createRichTextShape();
            $topAccent->setHeight(4)->setWidth(240)->setOffsetX($card['x'])->setOffsetY(120);
            $topAccent->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor($card['color']);
            
            // Número - maior e mais destacado
            $number = $slide->createRichTextShape();
            $number->setHeight(50)->setWidth(240)->setOffsetX($card['x'])->setOffsetY(140);
            $number->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_CENTER);
            $numberText = $number->createTextRun((string)$card['value']);
            $numberText->getFont()
                ->setBold(true)
                ->setSize(40)
                ->setColor($card['color'])
                ->setName('Segoe UI');
            
            // Label - menor e mais sutil
            $label = $slide->createRichTextShape();
            $label->setHeight(25)->setWidth(240)->setOffsetX($card['x'])->setOffsetY(195);
            $label->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_CENTER);
            $labelText = $label->createTextRun($card['label']);
            $labelText->getFont()
                ->setSize(11)
                ->setColor(new PptColor('FF7F8C8D'))
                ->setName('Segoe UI');
        }
        
        // Box de viabilidade - design mais sofisticado
        $viabilityPercent = $summary['total'] > 0 
            ? round((($summary['movable'] + $summary['movableWithRestrictions']) / $summary['total']) * 100, 1) 
            : 0;
        
        // Background do box com gradiente simulado (shapes sobrepostos)
        $viabBg = $slide->createRichTextShape();
        $viabBg->setHeight(130)->setWidth(660)->setOffsetX(310)->setOffsetY(280);
        $viabBg->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor(new PptColor('FFF8F9FA'));
        
        // Borda esquerda accent
        $viabAccent = $slide->createRichTextShape();
        $viabAccent->setHeight(130)->setWidth(6)->setOffsetX(310)->setOffsetY(280);
        $viabAccent->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor($tealColor);
        
        // Label superior
        $viabLabel = $slide->createRichTextShape();
        $viabLabel->setHeight(25)->setWidth(640)->setOffsetX(325)->setOffsetY(295);
        $viabLabel->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_LEFT);
        $viabLabelText = $viabLabel->createTextRun('Viabilidade de Migração');
        $viabLabelText->getFont()
            ->setSize(13)
            ->setColor(new PptColor('FF7F8C8D'))
            ->setName('Segoe UI');
        
        // Percentual grande
        $viabValue = $slide->createRichTextShape();
        $viabValue->setHeight(65)->setWidth(640)->setOffsetX(325)->setOffsetY(320);
        $viabValue->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_LEFT);
        $viabValueText = $viabValue->createTextRun($viabilityPercent . '%');
        $viabValueText->getFont()
            ->setBold(true)
            ->setSize(52)
            ->setColor($tealColor)
            ->setName('Segoe UI');
        
        // Nota de rodapé - mais discreta
        $footer = $slide->createRichTextShape();
        $footer->setHeight(20)->setWidth(860)->setOffsetX(50)->setOffsetY(440);
        $footer->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_CENTER);
        $footerText = $footer->createTextRun('Recursos que podem ser migrados, incluindo aqueles com restrições específicas');
        $footerText->getFont()
            ->setSize(10)
            ->setColor(new PptColor('FFBDC3C7'))
            ->setName('Segoe UI');
    }

    /**
     * Cria o slide com gráfico de pizza - Design Clean
     */
    private function createChartSlide(
        PhpPresentation $presentation,
        array $summary,
        PptColor $tealColor,
        PptColor $blueColor,
        PptColor $greenColor,
        PptColor $yellowColor,
        PptColor $redColor,
        PptColor $whiteColor
    ): void {
        $slide = $presentation->createSlide();
        
        // Background branco
        $bgColor = new BackgroundColor();
        $bgColor->setColor($whiteColor);
        $slide->setBackground($bgColor);
        
        // Header accent
        $accentLine = $slide->createLineShape(0, 0, 1280, 0);
        $accentLine->getBorder()->setColor($blueColor)->setLineWidth(4);
        
        // Título
        $title = $slide->createRichTextShape();
        $title->setHeight(50)->setWidth(1160)->setOffsetX(70)->setOffsetY(40);
        $titleText = $title->createTextRun('Distribuição dos Recursos');
        $titleText->getFont()
            ->setBold(false)
            ->setSize(28)
            ->setColor(new PptColor('FF2C3E50'))
            ->setName('Segoe UI Light');
        
        // Linha sutil
        $line = $slide->createLineShape(80, 88, 310, 88);
        $line->getBorder()->setColor(new PptColor('FFE0E0E0'))->setLineWidth(2);
        
        // Criar gráfico de pizza
        $chartShape = $slide->createChartShape();
        $chartShape->setHeight(350)->setWidth(500)->setOffsetX(100)->setOffsetY(140);
        
        $pie = new Pie3D();
        $series = new Series('Recursos', [
            'Migráveis' => $summary['movable'],
            'Com Restrições' => $summary['movableWithRestrictions'],
            'Não Migráveis' => $summary['notMovable'],
        ]);
        
        $series->setShowValue(true);
        $series->setShowPercentage(true);
        $series->getDataPointFill(0)->setFillType(PptFill::FILL_SOLID)->setStartColor($greenColor);
        $series->getDataPointFill(1)->setFillType(PptFill::FILL_SOLID)->setStartColor($yellowColor);
        $series->getDataPointFill(2)->setFillType(PptFill::FILL_SOLID)->setStartColor($redColor);
        
        $pie->addSeries($series);
        $chartShape->getPlotArea()->setType($pie);
        $chartShape->getLegend()->setVisible(false);
        
        // Legenda customizada mais elegante
        $legendX = 750;
        $legendY = 180;
        
        // Título da legenda
        $legendTitle = $slide->createRichTextShape();
        $legendTitle->setHeight(25)->setWidth(300)->setOffsetX($legendX)->setOffsetY($legendY - 20);
        $legendTitleText = $legendTitle->createTextRun('Categorias');
        $legendTitleText->getFont()
            ->setSize(13)
            ->setColor(new PptColor('FF7F8C8D'))
            ->setName('Segoe UI')
            ->setBold(true);
        
        $legendItems = [
            ['label' => 'Migráveis', 'desc' => 'Movimentação sem restrições', 'color' => $greenColor, 'count' => $summary['movable']],
            ['label' => 'Com Restrições', 'desc' => 'Requerem atenção especial', 'color' => $yellowColor, 'count' => $summary['movableWithRestrictions']],
            ['label' => 'Não Migráveis', 'desc' => 'Precisam ser recriados', 'color' => $redColor, 'count' => $summary['notMovable']],
        ];
        
        foreach ($legendItems as $item) {
            // Box colorido com número
            $box = $slide->createRichTextShape();
            $box->setHeight(35)->setWidth(35)->setOffsetX($legendX)->setOffsetY($legendY);
            $box->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor($item['color']);
            $box->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_CENTER);
            
            // Número dentro do box
            $boxNum = $slide->createRichTextShape();
            $boxNum->setHeight(35)->setWidth(35)->setOffsetX($legendX)->setOffsetY($legendY + 5);
            $boxNum->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_CENTER);
            $boxNumText = $boxNum->createTextRun((string)$item['count']);
            $boxNumText->getFont()
                ->setBold(true)
                ->setSize(14)
                ->setColor($whiteColor)
                ->setName('Segoe UI');
            
            // Label principal
            $label = $slide->createRichTextShape();
            $label->setHeight(20)->setWidth(280)->setOffsetX($legendX + 45)->setOffsetY($legendY);
            $labelText = $label->createTextRun($item['label']);
            $labelText->getFont()
                ->setSize(13)
                ->setColor(new PptColor('FF2C3E50'))
                ->setName('Segoe UI')
                ->setBold(true);
            
            // Descrição
            $desc = $slide->createRichTextShape();
            $desc->setHeight(18)->setWidth(280)->setOffsetX($legendX + 45)->setOffsetY($legendY + 18);
            $descText = $desc->createTextRun($item['desc']);
            $descText->getFont()
                ->setSize(10)
                ->setColor(new PptColor('FF95A5A6'))
                ->setName('Segoe UI');
            
            $legendY += 60;
        }
    }

    /**
     * Cria o slide de recursos bloqueantes - Design Clean
     */
    private function createBlockingResourcesSlide(
        PhpPresentation $presentation,
        array $notMovable,
        PptColor $tealColor,
        PptColor $redColor,
        PptColor $whiteColor,
        PptColor $darkColor
    ): void {
        $slide = $presentation->createSlide();
        
        // Background branco
        $bgColor = new BackgroundColor();
        $bgColor->setColor($whiteColor);
        $slide->setBackground($bgColor);
        
        // Header com accent vermelho suave
        $accentLine = $slide->createLineShape(0, 0, 1280, 0);
        $accentLine->getBorder()->setColor(new PptColor('FFEF5350'))->setLineWidth(4);
        
        // Título
        $title = $slide->createRichTextShape();
        $title->setHeight(50)->setWidth(1160)->setOffsetX(70)->setOffsetY(40);
        $titleText = $title->createTextRun('Recursos Não Migráveis');
        $titleText->getFont()
            ->setBold(false)
            ->setSize(28)
            ->setColor(new PptColor('FF2C3E50'))
            ->setName('Segoe UI Light');
        
        // Ícone de informação sutil (não alerta)
        $icon = $slide->createRichTextShape();
        $icon->setHeight(35)->setWidth(35)->setOffsetX(1150)->setOffsetY(37);
        $iconText = $icon->createTextRun('ℹ');
        $iconText->getFont()
            ->setSize(24)
            ->setColor(new PptColor('FFEF5350'));
        
        // Linha sutil
        $line = $slide->createLineShape(70, 88, 290, 88);
        $line->getBorder()->setColor(new PptColor('FFE0E0E0'))->setLineWidth(2);
        
        // Subtítulo informativo
        $subtitle = $slide->createRichTextShape();
        $subtitle->setHeight(35)->setWidth(1110)->setOffsetX(70)->setOffsetY(100);
        $subtitleText = $subtitle->createTextRun('Estes recursos precisarão ser recriados manualmente no ambiente de destino');
        $subtitleText->getFont()
            ->setSize(12)
            ->setColor(new PptColor('FF7F8C8D'))
            ->setName('Segoe UI');
        
        // Card container com lista
        $yPos = 160;
        $count = 0;
        
        foreach ($notMovable as $resource) {
            if ($count >= 10) {
                // Indicador de mais recursos
                $more = $slide->createRichTextShape();
                $more->setHeight(30)->setWidth(1100)->setOffsetX(70)->setOffsetY($yPos);
                $moreText = $more->createTextRun('+ ' . (count($notMovable) - 10) . ' recursos adicionais...');
                $moreText->getFont()
                    ->setItalic(true)
                    ->setSize(11)
                    ->setColor(new PptColor('FF95A5A6'))
                    ->setName('Segoe UI');
                break;
            }
            
            // Card item com sombra simulada
            $shadow = $slide->createRichTextShape();
            $shadow->setHeight(42)->setWidth(1162)->setOffsetX(72)->setOffsetY($yPos + 2);
            $shadow->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor(new PptColor('FFF0F0F0'));
            
            $card = $slide->createRichTextShape();
            $card->setHeight(42)->setWidth(1160)->setOffsetX(70)->setOffsetY($yPos);
            $card->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor(new PptColor('FFFEF7F7'));
            
            // Accent vermelho lateral
            $accent = $slide->createLineShape(70, $yPos, 70, $yPos + 42);
            $accent->getBorder()->setColor(new PptColor('FFEF5350'))->setLineWidth(4);
            
            // Nome do recurso
            $name = $slide->createRichTextShape();
            $name->setHeight(42)->setWidth(550)->setOffsetX(100)->setOffsetY($yPos + 8);
            $nameText = $name->createTextRun($resource['resourceName']);
            $nameText->getFont()
                ->setBold(true)
                ->setSize(12)
                ->setColor(new PptColor('FF2C3E50'))
                ->setName('Segoe UI');
            
            // Tipo do recurso
            $type = $slide->createRichTextShape();
            $type->setHeight(42)->setWidth(500)->setOffsetX(650)->setOffsetY($yPos + 8);
            $typeText = $type->createTextRun($resource['resourceType']);
            $typeText->getFont()
                ->setSize(11)
                ->setColor(new PptColor('FF7F8C8D'))
                ->setName('Consolas');
            
            $yPos += 47;
            $count++;
        }
    }

    /**
     * Cria o slide de recursos com restrições - Design Clean
     */
    private function createRestrictionsSlide(
        PhpPresentation $presentation,
        array $withRestrictions,
        PptColor $tealColor,
        PptColor $yellowColor,
        PptColor $whiteColor,
        PptColor $darkColor
    ): void {
        $slide = $presentation->createSlide();
        
        // Background branco
        $bgColor = new BackgroundColor();
        $bgColor->setColor($whiteColor);
        $slide->setBackground($bgColor);
        
        // Header accent
        $accentLine = $slide->createLineShape(0, 0, 1280, 0);
        $accentLine->getBorder()->setColor(new PptColor('FFFFA726'))->setLineWidth(4);
        
        // Título
        $title = $slide->createRichTextShape();
        $title->setHeight(50)->setWidth(1160)->setOffsetX(60)->setOffsetY(40);
        $titleText = $title->createTextRun('Recursos com Restrições');
        $titleText->getFont()
            ->setBold(false)
            ->setSize(28)
            ->setColor(new PptColor('FF2C3E50'))
            ->setName('Segoe UI Light');
        
        // Linha sutil
        $line = $slide->createLineShape(70, 88, 290, 88);
        $line->getBorder()->setColor(new PptColor('FFE0E0E0'))->setLineWidth(2);
        
        // Subtítulo
        $subtitle = $slide->createRichTextShape();
        $subtitle->setHeight(35)->setWidth(1110)->setOffsetX(70)->setOffsetY(100);
        $subtitleText = $subtitle->createTextRun('Estes recursos podem ser migrados, mas requerem atenção especial');
        $subtitleText->getFont()
            ->setSize(12)
            ->setColor(new PptColor('FF7F8C8D'))
            ->setName('Segoe UI');
        
        // Criar tabela moderna
        $table = $slide->createTableShape(3);
        $table->setHeight(300)->setWidth(1160)->setOffsetX(60)->setOffsetY(150);
        
        // Cabeçalho moderno
        $row = $table->createRow();
        $row->setHeight(40);
        
        $cell = $row->getCell(0);
        $cell->setWidth(380);
        $cell->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor(new PptColor('FFF8F9FA'));
        $cell->getBorders()->getBottom()->setColor(new PptColor('FFE0E0E0'))->setLineWidth(2);
        $headerText = $cell->createTextRun('Nome do Recurso');
        $headerText->getFont()->setBold(true)->setSize(11)->setColor(new PptColor('FF2C3E50'))->setName('Segoe UI');
        
        $cell = $row->getCell(1);
        $cell->setWidth(420);
        $cell->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor(new PptColor('FFF8F9FA'));
        $cell->getBorders()->getBottom()->setColor(new PptColor('FFE0E0E0'))->setLineWidth(2);
        $headerText = $cell->createTextRun('Tipo');
        $headerText->getFont()->setBold(true)->setSize(11)->setColor(new PptColor('FF2C3E50'))->setName('Segoe UI');
        
        $cell = $row->getCell(2);
        $cell->setWidth(360);
        $cell->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor(new PptColor('FFF8F9FA'));
        $cell->getBorders()->getBottom()->setColor(new PptColor('FFE0E0E0'))->setLineWidth(2);
        $headerText = $cell->createTextRun('Observação');
        $headerText->getFont()->setBold(true)->setSize(11)->setColor(new PptColor('FF2C3E50'))->setName('Segoe UI');
        
        // Dados (máximo 8 para caber)
        $count = 0;
        $altBg = false;
        foreach ($withRestrictions as $resource) {
            if ($count >= 8) break;
            
            $row = $table->createRow();
            $row->setHeight(35);
            
            $bgColor = $altBg ? new PptColor('FFFFFEF9') : $whiteColor;
            
            $cell = $row->getCell(0);
            $cell->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor($bgColor);
            $cell->getBorders()->getBottom()->setColor(new PptColor('FFF0F0F0'))->setLineWidth(1);
            $cellText = $cell->createTextRun($resource['resourceName']);
            $cellText->getFont()->setSize(10)->setColor(new PptColor('FF2C3E50'))->setName('Segoe UI')->setBold(false);
            
            $cell = $row->getCell(1);
            $cell->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor($bgColor);
            $cell->getBorders()->getBottom()->setColor(new PptColor('FFF0F0F0'))->setLineWidth(1);
            $cellText = $cell->createTextRun($resource['resourceType']);
            $cellText->getFont()->setSize(9)->setColor(new PptColor('FF7F8C8D'))->setName('Consolas');
            
            $cell = $row->getCell(2);
            $cell->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor($bgColor);
            $cell->getBorders()->getBottom()->setColor(new PptColor('FFF0F0F0'))->setLineWidth(1);
            $notes = $resource['notes'] ?: 'Verificar documentação';
            $cellText = $cell->createTextRun(substr($notes, 0, 40) . (strlen($notes) > 40 ? '...' : ''));
            $cellText->getFont()->setSize(9)->setColor(new PptColor('FFFFA726'))->setName('Segoe UI');
            
            $altBg = !$altBg;
            $count++;
        }
        
        // Indicar se há mais
        if (count($withRestrictions) > 8) {
            $more = $slide->createRichTextShape();
            $more->setHeight(30)->setWidth(500)->setOffsetX(70)->setOffsetY(465);
            $moreText = $more->createTextRun('+ ' . (count($withRestrictions) - 8) . ' recursos adicionais com restrições');
            $moreText->getFont()
                ->setItalic(true)
                ->setSize(11)
                ->setColor(new PptColor('FF95A5A6'))
                ->setName('Segoe UI');
        }
    }

    /**
     * Cria o slide de próximos passos - Design Clean
     */
    private function createNextStepsSlide(
        PhpPresentation $presentation,
        array $recommendations,
        PptColor $tealColor,
        PptColor $blueColor,
        PptColor $whiteColor,
        PptColor $darkColor
    ): void {
        $slide = $presentation->createSlide();
        
        // Background branco
        $bgColor = new BackgroundColor();
        $bgColor->setColor($whiteColor);
        $slide->setBackground($bgColor);
        
        // Header accent
        $accentLine = $slide->createLineShape(0, 0, 960, 0);
        $accentLine->getBorder()->setColor($blueColor)->setLineWidth(4);
        
        // Título
        $title = $slide->createRichTextShape();
        $title->setHeight(50)->setWidth(1160)->setOffsetX(70)->setOffsetY(40);
        $titleText = $title->createTextRun('Próximos Passos');
        $titleText->getFont()
            ->setBold(false)
            ->setSize(28)
            ->setColor(new PptColor('FF2C3E50'))
            ->setName('Segoe UI Light');
        
        // Linha sutil
        $line = $slide->createLineShape(80, 88, 260, 88);
        $line->getBorder()->setColor(new PptColor('FFE0E0E0'))->setLineWidth(2);
        
        // Recomendações
        $yPos = 130;
        $index = 1;
        
        if (!empty($recommendations)) {
            foreach ($recommendations as $rec) {
                if ($index > 5) break;
                
                // Número circular moderno
                $numCircle = $slide->createRichTextShape();
                $numCircle->setHeight(38)->setWidth(38)->setOffsetX(70)->setOffsetY($yPos);
                $numCircle->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor(new PptColor('FFF8F9FA'));
                $numCircle->getBorder()->setColor($blueColor)->setLineWidth(2);
                $numCircle->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_CENTER);
                
                // Número
                $num = $slide->createRichTextShape();
                $num->setHeight(38)->setWidth(38)->setOffsetX(70)->setOffsetY($yPos + 7);
                $num->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_CENTER);
                $numText = $num->createTextRun((string)$index);
                $numText->getFont()
                    ->setBold(true)
                    ->setSize(15)
                    ->setColor($blueColor)
                    ->setName('Segoe UI');
                
                // Título da recomendação
                $recTitle = $slide->createRichTextShape();
                $recTitle->setHeight(25)->setWidth(1080)->setOffsetX(125)->setOffsetY($yPos);
                $recTitleText = $recTitle->createTextRun($rec['title']);
                $recTitleText->getFont()
                    ->setBold(true)
                    ->setSize(13)
                    ->setColor(new PptColor('FF2C3E50'))
                    ->setName('Segoe UI');
                
                // Descrição
                $recDesc = $slide->createRichTextShape();
                $recDesc->setHeight(22)->setWidth(1080)->setOffsetX(125)->setOffsetY($yPos + 23);
                $recDescText = $recDesc->createTextRun($rec['message']);
                $recDescText->getFont()
                    ->setSize(11)
                    ->setColor(new PptColor('FF7F8C8D'))
                    ->setName('Segoe UI');
                
                $yPos += 60;
                $index++;
            }
        } else {
            // Recomendações padrão
            $defaultRecs = [
                ['title' => 'Revisar Recursos Bloqueantes', 'desc' => 'Analisar recursos não migráveis e planejar recriação'],
                ['title' => 'Documentar Dependências', 'desc' => 'Mapear relações e dependências entre recursos'],
                ['title' => 'Criar Cronograma Faseado', 'desc' => 'Estabelecer fases da migração com marcos claros'],
                ['title' => 'Ambiente de Homologação', 'desc' => 'Testar migração completa antes do go-live'],
                ['title' => 'Plano de Contingência', 'desc' => 'Preparar estratégia de rollback se necessário'],
            ];
            
            foreach ($defaultRecs as $rec) {
                // Número circular moderno
                $numCircle = $slide->createRichTextShape();
                $numCircle->setHeight(38)->setWidth(38)->setOffsetX(70)->setOffsetY($yPos);
                $numCircle->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor(new PptColor('FFF8F9FA'));
                $numCircle->getBorder()->setColor($blueColor)->setLineWidth(2);
                
                // Número
                $num = $slide->createRichTextShape();
                $num->setHeight(38)->setWidth(38)->setOffsetX(70)->setOffsetY($yPos + 7);
                $num->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_CENTER);
                $numText = $num->createTextRun((string)$index);
                $numText->getFont()
                    ->setBold(true)
                    ->setSize(15)
                    ->setColor($blueColor)
                    ->setName('Segoe UI');
                
                // Título
                $recTitle = $slide->createRichTextShape();
                $recTitle->setHeight(25)->setWidth(1080)->setOffsetX(125)->setOffsetY($yPos);
                $recTitleText = $recTitle->createTextRun($rec['title']);
                $recTitleText->getFont()
                    ->setBold(true)
                    ->setSize(13)
                    ->setColor(new PptColor('FF2C3E50'))
                    ->setName('Segoe UI');
                
                // Descrição
                $recDesc = $slide->createRichTextShape();
                $recDesc->setHeight(22)->setWidth(1080)->setOffsetX(125)->setOffsetY($yPos + 23);
                $recDescText = $recDesc->createTextRun($rec['desc']);
                $recDescText->getFont()
                    ->setSize(11)
                    ->setColor(new PptColor('FF7F8C8D'))
                    ->setName('Segoe UI');
                
                $yPos += 60;
                $index++;
            }
        }
        
        // Call to action box moderno (não mais solid color)
        $ctaBox = $slide->createRichTextShape();
        $ctaBox->setHeight(70)->setWidth(800)->setOffsetX(240)->setOffsetY(430);
        $ctaBox->getFill()->setFillType(PptFill::FILL_SOLID)->setStartColor(new PptColor('FFF8FBFD'));
        $ctaBox->getBorder()->setColor($blueColor)->setLineWidth(2);
        $ctaBox->getActiveParagraph()->getAlignment()
            ->setHorizontal(PptAlignment::HORIZONTAL_CENTER);
        
        // Texto do CTA
        $cta = $slide->createRichTextShape();
        $cta->setHeight(30)->setWidth(800)->setOffsetX(240)->setOffsetY(442);
        $cta->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_CENTER);
        $ctaText = $cta->createTextRun('Entre em contato com a TD SYNNEX');
        $ctaText->getFont()
            ->setBold(true)
            ->setSize(16)
            ->setColor($blueColor)
            ->setName('Segoe UI');
        
        // Subtexto do CTA
        $ctaSub = $slide->createRichTextShape();
        $ctaSub->setHeight(25)->setWidth(800)->setOffsetX(240)->setOffsetY(467);
        $ctaSub->getActiveParagraph()->getAlignment()->setHorizontal(PptAlignment::HORIZONTAL_CENTER);
        $ctaSubText = $ctaSub->createTextRun('para suporte especializado em migração Azure');
        $ctaSubText->getFont()
            ->setSize(11)
            ->setColor(new PptColor('FF7F8C8D'))
            ->setName('Segoe UI');
    }

    /**
     * Remove relatórios antigos
     */
    public function cleanOldReports(int $maxAgeDays = 7): void
    {
        if (!is_dir($this->reportsDir)) {
            return;
        }

        $files = glob($this->reportsDir . '/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= $maxAgeDays * 86400) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Lista relatórios existentes
     */
    public function listReports(): array
    {
        $reports = [];
        
        if (!is_dir($this->reportsDir)) {
            return $reports;
        }

        $files = glob($this->reportsDir . '/*.{pdf,xlsx}', GLOB_BRACE);
        
        foreach ($files as $file) {
            $reports[] = [
                'filename' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'created' => date('Y-m-d H:i:s', filemtime($file)),
                'type' => pathinfo($file, PATHINFO_EXTENSION)
            ];
        }

        // Ordena por data (mais recente primeiro)
        usort($reports, fn($a, $b) => strcmp($b['created'], $a['created']));

        return $reports;
    }
}
