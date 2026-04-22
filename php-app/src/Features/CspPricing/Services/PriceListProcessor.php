<?php

declare(strict_types=1);

namespace App\Features\CspPricing\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Processa a Lista de Preços M365 CSP NCE (TD SYNNEX).
 *
 * Suporta dois formatos:
 *  1. Aba "Dados" (novo formato) — header na linha 1, dados a partir de 2:
 *       C: Product ID  |  D: SKU ID (número raw)  |  F: TERM (código curto p1y/p1m/p1y-m)
 *       G: Produto     |  H: SKU numérico         |  I: Preço  |  J: Anual Pg Mensal
 *
 *  2. Aba "MW" (formato NCE legado) — header na linha 8, dados a partir de 9.
 *
 * ChaveComparacao = ProductId + SkuId(4 chars, zero-pad) + TERM
 */
class PriceListProcessor
{
    /** Mapeamento "Modelo de Faturamento" → código curto (formato legado MW) */
    private const TERM_MAP_MW = [
        'Mensal'                     => 'P1M',
        'Anual com pagamento mensal' => 'P1Y-M',
        'Anual'                      => 'P1Y',
    ];

    /** Part Number pattern para aba MW: MST-{ProductId}-{SkuId}-{Term}-{Country} */
    private const PN_PATTERN = '/^MST-([A-Z0-9]+)-([A-Z0-9]{4})-(.+)-([A-Z]{2})$/i';

    // ─── Processamento principal ─────────────────────────────────────────────

    /**
     * Carrega e processa um arquivo Excel de lista de preços.
     *
     * @return array{success:bool, error?:string, totalRows?:int, preview?:array, lookup?:array}
     */
    public function processFile(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);

            // Detecta formato pelo nome da aba
            $sheetDados = $spreadsheet->getSheetByName('Dados');
            $sheetMW    = $spreadsheet->getSheetByName('MW');

            if ($sheetDados) {
                $rows = $this->parseDadosSheet($sheetDados);
                $fmt  = 'Dados';
            } elseif ($sheetMW) {
                $rows = $this->parseMWSheet($sheetMW);
                $fmt  = 'MW';
            } else {
                return ['success' => false, 'error' => 'Formato não reconhecido. O arquivo deve conter a aba "Dados" ou "MW".'];
            }

            if (empty($rows)) {
                return ['success' => false, 'error' => "Nenhum item válido encontrado na aba $fmt."];
            }

            // Constrói lookup indexado por ChaveComparacao
            $lookup = [];
            foreach ($rows as $row) {
                $key = $row['ChaveComparacao'];
                if (!isset($lookup[$key])) {
                    $lookup[$key] = $row;
                }
            }

            return [
                'success'   => true,
                'totalRows' => count($rows),
                'preview'   => array_slice($rows, 0, 300),
                'lookup'    => $lookup,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Erro ao processar a lista de preços: ' . $e->getMessage()];
        }
    }

    // ─── Parser: aba "Dados" (novo formato) ─────────────────────────────────────

    private function parseDadosSheet(Worksheet $sheet): array
    {
        $rows   = [];
        $maxRow = $sheet->getHighestRow();

        for ($r = 2; $r <= $maxRow; $r++) {   // linha 1 = header
            $productId = strtoupper($this->cellVal($sheet, 'C', $r));
            if ($productId === '') continue;

            $skuRaw      = $this->cellVal($sheet, 'D', $r);    // número raw, ex: 5
            $skuId       = str_pad($skuRaw, 4, '0', STR_PAD_LEFT);  // "0005"
            $term        = trim($this->cellVal($sheet, 'F', $r));    // já normalizado: P1Y, P1Y-M, P1M
            $produto     = $this->cellVal($sheet, 'G', $r);
            $skuNum      = $this->cellVal($sheet, 'H', $r);
            $precoRaw    = $this->cellVal($sheet, 'I', $r);
            $anualMRaw   = $this->cellVal($sheet, 'J', $r);
            $partNumber  = $this->cellVal($sheet, 'A', $r);

            $rows[] = [
                'ChaveComparacao' => $productId . $skuId . $term,
                'ProductId'       => $productId,
                'SkuId'           => $skuId,
                'TermShort'       => $term,
                'PartNumber'      => $partNumber,
                'Produto'         => $produto,
                'SKU_SYNNEX'      => $skuNum,
                'Preco'           => is_numeric($precoRaw) ? $precoRaw : null,
                'AnualPgMensal'   => is_numeric($anualMRaw) ? $anualMRaw : null,
                'Familia'         => '',
                'Seguimento'      => '',
                'Tipo'            => '',
            ];
        }

        return $rows;
    }

    // ─── Parser: aba "MW" (formato legado NCE) ────────────────────────────────────

    private function parseMWSheet(Worksheet $sheet): array
    {
        $rows   = [];
        $maxRow = $sheet->getHighestRow();

        for ($r = 9; $r <= $maxRow; $r++) {   // linha 8 = header, dados de 9
            $pn = $this->cellVal($sheet, 'B', $r);
            if (!preg_match(self::PN_PATTERN, $pn, $m)) continue;

            $productId     = strtoupper($m[1]);
            $skuId         = str_pad(strtoupper($m[2]), 4, '0', STR_PAD_LEFT);
            $termoTabela   = $this->cellVal($sheet, 'G', $r);
            $termShort     = self::TERM_MAP_MW[$termoTabela] ?? 'P1Y';
            $precoRaw      = $this->cellVal($sheet, 'E', $r);
            $anualMRaw     = $this->cellVal($sheet, 'F', $r);

            $rows[] = [
                'ChaveComparacao' => $productId . $skuId . $termShort,
                'ProductId'       => $productId,
                'SkuId'           => $skuId,
                'TermShort'       => $termShort,
                'PartNumber'      => $pn,
                'Produto'         => $this->cellVal($sheet, 'C', $r),
                'SKU_SYNNEX'      => $this->cellVal($sheet, 'D', $r),
                'Preco'           => is_numeric($precoRaw) ? $precoRaw : null,
                'AnualPgMensal'   => is_numeric($anualMRaw) ? $anualMRaw : null,
                'Familia'         => $this->cellVal($sheet, 'H', $r),
                'Seguimento'      => $this->cellVal($sheet, 'I', $r),
                'Tipo'            => $this->cellVal($sheet, 'K', $r),
            ];
        }

        return $rows;
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────────────

    private function cellVal(Worksheet $sheet, string $col, int $row): string
    {
        $v = $sheet->getCell($col . $row)->getValue();
        if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            $v = $v->getPlainText();
        }
        return trim((string)$v);
    }

    public static function formatBRL(?string $val): string
    {
        if ($val === null || $val === '') return '—';
        if (!is_numeric($val)) return $val;
        return 'R$ ' . number_format((float)$val, 2, ',', '.');
    }
}
