<?php

declare(strict_types=1);

namespace App\Features\M365Migration\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Processa arquivos de fatura M365 (T1) do Partner Center.
 *
 * Fase 1:
 *  - Normaliza SkuId (zero-padding à esquerda, 4 caracteres)
 *  - Converte TermAndBillingCycle para código curto
 *  - Gera ChaveM365 = ProductId + SkuId + SkuName + TermAndBillingCycle (tratados)
 */
class M365Processor
{
    /** Mapeamento de TermAndBillingCycle → código curto */
    private const TERM_MAP = [
        'One-Year commitment for monthly/yearly billing' => 'P1Y-M',
        'One-Month commitment for monthly billing'       => 'P1M',
        'One-Year commitment for monthly billing'        => 'P1YM',
    ];

    /** Colunas exigidas para processamento */
    private const REQUIRED_COLUMNS = ['ProductId', 'SkuId', 'SkuName', 'TermAndBillingCycle'];

    /** Tamanho máximo aceito: 50 MB */
    private const MAX_FILE_SIZE = 52_428_800;

    // ─── Validação ───────────────────────────────────────────────────────────

    public function validateFile(array $file): array
    {
        if (empty($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Nenhum arquivo foi enviado.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Erro no upload (código ' . (int)$file['error'] . ').'];
        }

        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            return ['valid' => false, 'error' => 'Formato não suportado. Use CSV, XLSX ou XLS.'];
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['valid' => false, 'error' => 'O arquivo excede o tamanho máximo permitido (50 MB).'];
        }

        return ['valid' => true, 'error' => null];
    }

    // ─── Processamento principal ──────────────────────────────────────────────

    /**
     * Lê, valida e processa o arquivo. Salva o CSV resultante em $uploadDir.
     *
     * @return array{
     *   success:    bool,
     *   error?:     string,
     *   totalRows?: int,
     *   headers?:   string[],
     *   preview?:   array,
     *   outputFile?: string,
     *   outputPath?: string,
     * }
     */
    public function processFile(string $filePath, string $uploadDir): array
    {
        try {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            $parsed = $ext === 'csv'
                ? $this->parseCsv($filePath)
                : $this->parseExcel($filePath);

            if (!$parsed['success']) {
                return $parsed;
            }

            // Valida colunas obrigatórias
            $missing = array_diff(self::REQUIRED_COLUMNS, $parsed['headers']);
            if (!empty($missing)) {
                return [
                    'success' => false,
                    'error'   => 'Colunas obrigatórias não encontradas: ' . implode(', ', $missing),
                ];
            }

            $processed  = $this->processRows($parsed['data']);
            $outputFile = 'm365_processed_' . uniqid() . '.csv';
            $outputPath = $uploadDir . '/' . $outputFile;

            $this->writeCsv($processed['rows'], $outputPath);

            return [
                'success'    => true,
                'totalRows'  => $processed['totalRows'],
                'headers'    => $processed['headers'],
                'preview'    => array_slice($processed['rows'], 0, 200),
                'outputFile' => $outputFile,
                'outputPath' => $outputPath,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Erro ao processar o arquivo: ' . $e->getMessage()];
        }
    }

    // ─── Transformações ───────────────────────────────────────────────────────

    private function processRows(array $data): array
    {
        $rows = [];

        foreach ($data as $row) {
            // 1. Normalizar SkuId (4 chars, zeros à esquerda)
            $row['SkuId'] = str_pad(trim((string)($row['SkuId'] ?? '')), 4, '0', STR_PAD_LEFT);

            // 2. Converter TermAndBillingCycle
            $termRaw                    = trim((string)($row['TermAndBillingCycle'] ?? ''));
            $row['TermAndBillingCycle'] = self::TERM_MAP[$termRaw] ?? 'P1Y';

            // 3. Chave completa (com SkuName) — usada para rastreabilidade
            $row['ChaveM365'] = ($row['ProductId'] ?? '') . $row['SkuId'] . ($row['SkuName'] ?? '') . $row['TermAndBillingCycle'];

            // 4. Chave de comparação (sem SkuName) — usada para match com lista de preços
            //    P1YM → P1Y-M para alinhar com os códigos da lista de preços
            $termForLookup = str_replace('P1YM', 'P1Y-M', $row['TermAndBillingCycle']);
            $row['ChaveComparacao'] = ($row['ProductId'] ?? '') . $row['SkuId'] . $termForLookup;

            $rows[] = $row;
        }

        return [
            'rows'      => $rows,
            'headers'   => empty($rows) ? [] : array_keys($rows[0]),
            'totalRows' => count($rows),
        ];
    }

    // ─── Parsers ──────────────────────────────────────────────────────────────

    private function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return ['success' => false, 'error' => 'Não foi possível abrir o arquivo.'];
        }

        // Remove BOM UTF-8 se presente
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Detecta delimitador a partir da primeira linha
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return ['success' => false, 'error' => 'Arquivo CSV vazio.'];
        }
        $delimiter = $this->detectDelimiter($firstLine);
        rewind($handle);
        // Pula BOM novamente
        $bom2 = fread($handle, 3);
        if ($bom2 !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = [];
        $data    = [];
        $rowNum  = 0;

        while (($cols = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($rowNum === 0) {
                $headers = array_map('trim', $cols);
                $rowNum++;
                continue;
            }
            if (!empty(array_filter($cols)) && count($cols) >= count($headers)) {
                $data[] = array_combine($headers, array_slice(array_map('trim', $cols), 0, count($headers)));
            }
            $rowNum++;
        }

        fclose($handle);

        if (empty($data)) {
            return ['success' => false, 'error' => 'O arquivo CSV não contém dados.'];
        }

        return ['success' => true, 'data' => $data, 'headers' => $headers];
    }

    private function parseExcel(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $headers     = [];
        $data        = [];
        $rowNum      = 0;

        foreach ($sheet->getRowIterator() as $rowIterator) {
            $cellIterator = $rowIterator->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $cols = [];
            foreach ($cellIterator as $cell) {
                $cols[] = trim((string)$cell->getFormattedValue());
            }

            if ($rowNum === 0) {
                $headers = $cols;
                $rowNum++;
                continue;
            }

            if (empty(array_filter($cols))) {
                $rowNum++;
                continue;
            }

            if (count($cols) >= count($headers)) {
                $data[] = array_combine($headers, array_slice($cols, 0, count($headers)));
            }
            $rowNum++;
        }

        if (empty($data)) {
            return ['success' => false, 'error' => 'A planilha não contém dados.'];
        }

        return ['success' => true, 'data' => $data, 'headers' => $headers];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function detectDelimiter(string $line): string
    {
        $candidates = [',', ';', "\t", '|'];
        $counts     = array_map(fn($d) => substr_count($line, $d), array_combine($candidates, $candidates));
        arsort($counts);
        $best = array_key_first($counts);
        return $best ?: ',';
    }

    private function writeCsv(array $rows, string $path): void
    {
        if (empty($rows)) {
            file_put_contents($path, '');
            return;
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Não foi possível criar o arquivo de saída.');
        }

        fwrite($handle, "\xEF\xBB\xBF"); // BOM para compatibilidade com Excel
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }
        fclose($handle);
    }
}
