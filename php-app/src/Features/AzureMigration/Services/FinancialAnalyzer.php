<?php
declare(strict_types=1);

namespace App\Features\AzureMigration\Services;

use App\Shared\Services\ServerLogger;

/**
 * Analisa arquivos de exportacao do Azure Cost Management e calcula
 * comparativo de custos MOSP vs CSP usando a API de precos da Microsoft.
 */
class FinancialAnalyzer
{
    private MicrosoftPricingApi $api;
    private ServerLogger $logger;

    public function __construct()
    {
        $this->api = new MicrosoftPricingApi();
        $this->logger = ServerLogger::getInstance();
    }

    /**
     * Faz o parse do arquivo CSV exportado do Cost Management.
     * @param string $qtyColumn  Coluna de quantidade: 'quantity' (MOSP) ou 'usagequantity' (MPN)
     * Retorna ['success' => bool, 'data' => array, 'error' => string|null]
     */
    public function parseFile(string $path, string $qtyColumn = 'quantity'): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            return ['success' => false, 'error' => 'Apenas arquivos CSV sao suportados.', 'data' => []];
        }
        return $this->parseCsv($path, $qtyColumn);
    }

    private function parseCsv(string $path, string $qtyColumn = 'quantity'): array
    {
        $fh = fopen($path, 'r');
        if (!$fh) {
            return ['success' => false, 'error' => 'Nao foi possivel abrir o arquivo.', 'data' => []];
        }

        // Remove BOM UTF-8 se presente
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        // Le primeira linha para detectar separador (virgula ou ponto-e-virgula)
        $first = fgets($fh);
        rewind($fh);
        if ($bom === "\xEF\xBB\xBF") {
            fread($fh, 3);
        }
        $sep = substr_count((string)$first, ';') > substr_count((string)$first, ',') ? ';' : ',';

        // Le cabecalho
        $raw = fgetcsv($fh, 0, $sep);
        if (!$raw) {
            fclose($fh);
            return ['success' => false, 'error' => 'Arquivo sem cabecalho.', 'data' => []];
        }
        $headers = array_map(fn($h) => strtolower(trim((string)$h)), $raw);

        // Valida colunas obrigatorias baseadas no tipo de migracao
        // Colunas essenciais para comparacao com API Azure: MeterID, Quantity, UnitOfMeasure, MeterName, ServiceName
        $requiredColumns = [
            'meterid'       => 'MeterID',
            $qtyColumn      => $qtyColumn === 'usagequantity' ? 'UsageQuantity (MPN/CSP)' : 'Quantity (MOSP)',
            'unitofmeasure' => 'UnitOfMeasure',
            'metername'     => 'MeterName',
        ];
        
        // ServiceName pode estar em diferentes colunas dependendo do export
        $serviceNameFound = in_array('servicename', $headers, true) || 
                            in_array('metercategory', $headers, true) ||
                            in_array('consumedservice', $headers, true);
        
        $missingColumns = [];
        foreach ($requiredColumns as $col => $label) {
            if (!in_array($col, $headers, true)) {
                $missingColumns[] = $label;
            }
        }
        
        if (!$serviceNameFound) {
            $missingColumns[] = 'ServiceName (ou MeterCategory/ConsumedService)';
        }
        
        if (!empty($missingColumns)) {
            fclose($fh);
            return [
                'success' => false,
                'error'   => "Colunas obrigatorias ausentes: " . implode(', ', $missingColumns) . ". Verifique se o arquivo CSV corresponde ao tipo de migracao selecionado.",
                'data'    => [],
            ];
        }

        $rows = [];
        $skippedRows = 0;
        while (($cols = fgetcsv($fh, 0, $sep)) !== false) {
            if (count($cols) < count($headers)) {
                continue;
            }
            $r = array_combine($headers, $cols);
            if (!$r) {
                continue;
            }

            $meterId      = trim($r['meterid'] ?? '');
            $quantity     = (float)($r[$qtyColumn] ?? 0);
            $unitOfMeasure = trim($r['unitofmeasure'] ?? '');
            $meterName    = trim($r['metername'] ?? '');
            // ServiceName: prioriza servicename > metercategory > consumedservice
            $serviceName  = trim($r['servicename'] ?? $r['metercategory'] ?? $r['consumedservice'] ?? '');

            // Valida campos obrigatorios por linha
            if ($meterId === '' || $quantity <= 0) {
                $skippedRows++;
                continue;
            }

            $rows[] = [
                'meterId'               => $meterId,
                'quantity'              => $quantity,
                // CSP exports may use InstanceName / ResourceName interchangeably
                'resourceName'          => (function() use ($r): string {
                    $raw = trim($r['resourcename'] ?? $r['instancename'] ?? $r['resourceid'] ?? '');
                    // InstanceName and ResourceId are full ARM paths like
                    // /subscriptions/.../storageAccounts/stopocalpine2025
                    // Extract only the last segment after the final "/"
                    if (str_contains($raw, '/')) {
                        $raw = ltrim((string)strrchr($raw, '/'), '/');
                    }
                    return $raw;
                })(),
                // CSP exports may use ResourceGroupName instead of ResourceGroup
                'resourceGroup'         => trim($r['resourcegroup'] ?? $r['resourcegroupname'] ?? ''),
                'resourceLocation'      => trim($r['resourcelocation'] ?? $r['location'] ?? ''),
                'productName'           => trim($r['productname']           ?? ''),
                'productId'             => trim($r['productid']             ?? ''),
                'meterName'             => $meterName,
                'meterCategory'         => trim($r['metercategory']         ?? ''),
                'meterSubcategory'      => trim($r['metersubcategory']      ?? ''),
                'serviceName'           => $serviceName,
                'serviceFamily'         => trim($r['servicefamily']         ?? ''),
                'consumedService'       => trim($r['consumedservice']       ?? ''),
                'unitOfMeasure'         => $unitOfMeasure,
                'costInBillingCurrency' => (float)($r['costinbillingcurrency'] ?? 0),
                'unitPrice'             => (float)($r['unitprice']          ?? 0),
                'billingCurrencyCode'   => trim($r['billingcurrencycode']   ?? 'USD'),
                'date'                  => trim($r['date'] ?? $r['usagedatetime'] ?? ''),
                'subscriptionName'      => trim($r['subscriptionname']      ?? ''),
                'subscriptionId'        => trim($r['subscriptionid']        ?? ''),
                'resourceId'            => trim($r['resourceid'] ?? $r['instanceid'] ?? ''),
            ];
        }
        fclose($fh);

        if (empty($rows)) {
            return ['success' => false, 'error' => 'Nenhuma linha valida encontrada no arquivo.', 'data' => []];
        }

        $this->logger->success('FileParser', 'CSV parseado: ' . count($rows) . ' linhas validas, ' . $skippedRows . ' ignoradas', [
            'columns' => $headers,
            'rows' => count($rows),
            'skipped' => $skippedRows,
        ]);

        return [
            'success'     => true,
            'data'        => $rows,
            'error'       => null,
            'skippedRows' => $skippedRows,
            'columns'     => $headers,
        ];
    }

    /**
     * Executa a analise: consulta API para cada meterId unico e calcula custo CSP.
     */
    public function analyze(array $rows, float $exchangeRate): array
    {
        // Monta specs únicas por combinação (meterId + productId + resourceLocation + unitOfMeasure)
        // A query à API usa exatamente os mesmos campos do CSV — mais preciso e com fallback progressivo.
        $uniqueSpecs = [];
        foreach ($rows as $row) {
            $k = strtolower($row['meterId'] . '|' . $row['productId'] . '|' . $row['resourceLocation'] . '|' . $row['unitOfMeasure']);
            if (!isset($uniqueSpecs[$k])) {
                $uniqueSpecs[$k] = [
                    'key'              => $k,
                    'meterId'          => $row['meterId'],
                    'productId'        => $row['productId'],
                    'resourceLocation' => $row['resourceLocation'],
                    'unitOfMeasure'    => $row['unitOfMeasure'],
                    'meterName'        => $row['meterName'],
                ];
            }
        }
        $this->logger->info('Analyzer', 'Specs unicas montadas: ' . count($uniqueSpecs) . ' combinacoes de meterId+productId+location+uom');
        $this->logger->api('Analyzer', 'Iniciando consulta a API de precos Microsoft...');
        $analyzeStart = microtime(true);
        $prices = $this->api->getPricesBySpecs(array_values($uniqueSpecs));
        $this->logger->success('Analyzer', 'Consulta API concluida em ' . round((microtime(true) - $analyzeStart) * 1000) . 'ms');

        $results    = [];
        $totalMosp  = 0.0;
        $totalCsp   = 0.0;
        $byService  = [];
        $byRg       = [];
        $bySub      = [];
        $notFound   = [];

        foreach ($rows as $row) {
            $id        = $row['meterId'];
            $specKey   = strtolower($id . '|' . $row['productId'] . '|' . $row['resourceLocation'] . '|' . $row['unitOfMeasure']);
            $priceData = $prices[$specKey] ?? null;
            $costMosp  = $row['costInBillingCurrency'];
            $costCsp   = null;
            $unitCsp   = null;

            if ($priceData !== null) {
                $unitCsp = $priceData['unitPrice'];
                $costCsp = round($row['quantity'] * $unitCsp, 6);
            } elseif (!in_array($id, $notFound, true)) {
                $notFound[] = $id;
            }

            $totalMosp += $costMosp;
            if ($costCsp !== null) {
                $totalCsp += $costCsp;
            }

            // Agrupamento por servico
            $svc = $row['meterCategory'] ?: ($row['serviceFamily'] ?: 'Outros');
            $byService[$svc] ??= ['costMosp' => 0.0, 'costCsp' => 0.0, 'count' => 0];
            $byService[$svc]['costMosp'] += $costMosp;
            $byService[$svc]['count']++;
            if ($costCsp !== null) {
                $byService[$svc]['costCsp'] += $costCsp;
            }

            // Agrupamento por Resource Group
            $rg = $row['resourceGroup'] ?: 'Sem Resource Group';
            $byRg[$rg] ??= ['costMosp' => 0.0, 'costCsp' => 0.0, 'count' => 0];
            $byRg[$rg]['costMosp'] += $costMosp;
            $byRg[$rg]['count']++;
            if ($costCsp !== null) {
                $byRg[$rg]['costCsp'] += $costCsp;
            }

            // Agrupamento por Assinatura
            $subKey = $row['subscriptionName'] ?: ($row['subscriptionId'] ?: 'Sem Assinatura');
            $bySub[$subKey] ??= ['costMosp' => 0.0, 'costCsp' => 0.0, 'count' => 0, 'subscriptionId' => $row['subscriptionId'] ?? ''];
            $bySub[$subKey]['costMosp'] += $costMosp;
            $bySub[$subKey]['count']++;
            if ($costCsp !== null) {
                $bySub[$subKey]['costCsp'] += $costCsp;
            }

            $diff    = $costCsp !== null ? $costCsp - $costMosp : null;
            $diffPct = ($costMosp > 0 && $diff !== null) ? ($diff / $costMosp) * 100 : null;

            $results[] = [
                'meterId'           => $id,
                'resourceName'      => $row['resourceName'],
                'resourceGroup'     => $row['resourceGroup'],
                'meterCategory'     => $row['meterCategory'],
                'serviceFamily'     => $row['serviceFamily'],
                'meterName'         => $row['meterName'],
                'productName'       => $row['productName'],
                'quantity'          => $row['quantity'],
                'unitOfMeasure'     => $row['unitOfMeasure'],
                'unitPriceMosp'     => $row['unitPrice'],
                'costMosp'          => $costMosp,
                'unitPriceCsp'      => $unitCsp,
                'costCsp'           => $costCsp,
                'difference'        => $diff,
                'differencePercent' => $diffPct,
                'priceFound'        => $priceData !== null,
                'cspServiceName'    => $priceData['serviceName']   ?? null,
                'cspMeterName'      => $priceData['meterName']     ?? null,
                'resourceLocation'  => $row['resourceLocation'],
                'productId'         => $row['productId'],
                'subscriptionId'    => $row['subscriptionId'],
                'subscriptionName'  => $row['subscriptionName'],
                'meterSubcategory'  => $row['meterSubcategory'],
                'apiFilterUsed'     => $priceData['filterUsed']   ?? null,
                'apiMatchLevel'     => $priceData['matchLevel']   ?? null,
                'apiMatchScore'     => $priceData['matchScore']   ?? null,
                'apiMatchMaxScore'  => $priceData['matchMaxScore'] ?? null,
                'apiUnitOfMeasure'  => $priceData['unitOfMeasure'] ?? null,
                'apiMeterId'        => $priceData['meterId']       ?? null,
                'apiLocation'       => $priceData['location']      ?? null,
                'apiProductId'      => $priceData['productId']     ?? null,
                'date'              => $row['date'],
                'consumedService'   => $row['consumedService'],
                'resourceId'        => $row['resourceId'],
            ];
        }

        uasort($byService, fn($a, $b) => $b['costMosp'] <=> $a['costMosp']);
        uasort($byRg,      fn($a, $b) => $b['costMosp'] <=> $a['costMosp']);
        uasort($bySub,     fn($a, $b) => $b['costMosp'] <=> $a['costMosp']);

        $globalDiff    = $totalCsp - $totalMosp;
        $globalDiffPct = $totalMosp > 0 ? ($globalDiff / $totalMosp) * 100 : 0.0;

        $this->logger->success('Analyzer', 'Analise concluida: ' . count($rows) . ' linhas, ' . count($notFound) . ' MeterIDs nao encontrados', [
            'totalMosp' => round($totalMosp, 2),
            'totalCsp'  => round($totalCsp, 2),
            'diff'      => round($globalDiffPct, 1) . '%',
            'notFound'  => count($notFound),
        ]);

        return [
            'results' => $results,
            'summary' => [
                'totalRows'         => count($rows),
                'uniqueMeterIds'    => count($uniqueSpecs),
                'totalMosp'         => $totalMosp,
                'totalCsp'          => $totalCsp,
                'totalMospBrl'      => $totalMosp * $exchangeRate,
                'totalCspBrl'       => $totalCsp  * $exchangeRate,
                'difference'        => $globalDiff,
                'differencePercent' => $globalDiffPct,
                'differenceBrl'     => $globalDiff * $exchangeRate,
                'exchangeRate'      => $exchangeRate,
                'notFoundMeterIds'  => $notFound,
                'notFoundCount'     => count($notFound),
                'byService'         => $byService,
                'byResourceGroup'   => $byRg,
                'bySubscription'    => $bySub,
            ],
        ];
    }
}
