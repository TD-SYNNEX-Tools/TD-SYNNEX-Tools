<?php
declare(strict_types=1);
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/**
 * Comparativo de Preços de VMs por Região Azure
 * Dashboard executivo com gráficos interativos e filtros dinâmicos
 */

// Configuração de VMs de referência - SKUs mais populares
$vmSkus = [
    'Standard_B2s' => ['name' => 'B2s', 'fullName' => 'B2s (2 vCPU, 4 GB)', 'cores' => 2, 'ram' => 4, 'series' => 'Burstable'],
    'Standard_B4ms' => ['name' => 'B4ms', 'fullName' => 'B4ms (4 vCPU, 16 GB)', 'cores' => 4, 'ram' => 16, 'series' => 'Burstable'],
    'Standard_D2s_v5' => ['name' => 'D2s v5', 'fullName' => 'D2s v5 (2 vCPU, 8 GB)', 'cores' => 2, 'ram' => 8, 'series' => 'General Purpose'],
    'Standard_D4s_v5' => ['name' => 'D4s v5', 'fullName' => 'D4s v5 (4 vCPU, 16 GB)', 'cores' => 4, 'ram' => 16, 'series' => 'General Purpose'],
    'Standard_D8s_v5' => ['name' => 'D8s v5', 'fullName' => 'D8s v5 (8 vCPU, 32 GB)', 'cores' => 8, 'ram' => 32, 'series' => 'General Purpose'],
    'Standard_D16s_v5' => ['name' => 'D16s v5', 'fullName' => 'D16s v5 (16 vCPU, 64 GB)', 'cores' => 16, 'ram' => 64, 'series' => 'General Purpose'],
    'Standard_E2s_v5' => ['name' => 'E2s v5', 'fullName' => 'E2s v5 (2 vCPU, 16 GB)', 'cores' => 2, 'ram' => 16, 'series' => 'Memory Optimized'],
    'Standard_E4s_v5' => ['name' => 'E4s v5', 'fullName' => 'E4s v5 (4 vCPU, 32 GB)', 'cores' => 4, 'ram' => 32, 'series' => 'Memory Optimized'],
    'Standard_E8s_v5' => ['name' => 'E8s v5', 'fullName' => 'E8s v5 (8 vCPU, 64 GB)', 'cores' => 8, 'ram' => 64, 'series' => 'Memory Optimized'],
    'Standard_F2s_v2' => ['name' => 'F2s v2', 'fullName' => 'F2s v2 (2 vCPU, 4 GB)', 'cores' => 2, 'ram' => 4, 'series' => 'Compute Optimized'],
    'Standard_F4s_v2' => ['name' => 'F4s v2', 'fullName' => 'F4s v2 (4 vCPU, 8 GB)', 'cores' => 4, 'ram' => 8, 'series' => 'Compute Optimized'],
];

// Regiões principais para comparação
$mainRegions = [
    'brazilsouth' => 'Brazil South',
    'eastus' => 'East US',
    'eastus2' => 'East US 2',
    'westus2' => 'West US 2',
    'westeurope' => 'West Europe',
    'northeurope' => 'North Europe',
    'uksouth' => 'UK South',
    'southeastasia' => 'Southeast Asia',
    'japaneast' => 'Japan East',
    'australiaeast' => 'Australia East',
];

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $os = in_array($_POST['os'] ?? '', ['Windows', 'Linux']) ? $_POST['os'] : 'Linux';
    $selectedSku = isset($_POST['sku']) && array_key_exists($_POST['sku'], $vmSkus) ? $_POST['sku'] : null;
    $skusToFetch = $selectedSku ? [$selectedSku => $vmSkus[$selectedSku]] : $vmSkus;
    $result = fetchAllSkuPrices($skusToFetch, $mainRegions, $os);
    
    echo json_encode($result);
    exit;
}

/**
 * Consulta preços de múltiplos SKUs em múltiplas regiões
 */
function fetchAllSkuPrices(array $vmSkus, array $mainRegions, string $os): array
{
    $apiUrl = 'https://prices.azure.com/api/retail/prices';
    $apiVer = '2023-01-01-preview';
    
    $results = [];
    $regionStats = [];
    $errors = [];
    
    foreach ($vmSkus as $skuId => $skuInfo) {
        // Monta filtro OData - para Linux, não filtramos por Windows e fazemos a filtragem no PHP
        // porque 'not contains()' retorna HTTP 400 na API
        $filter = "armSkuName eq '{$skuId}' and priceType eq 'Consumption'";
        if ($os === 'Windows') {
            $filter .= " and contains(productName, 'Windows')";
        }
        
        $url = $apiUrl . '?api-version=' . urlencode($apiVer) 
             . '&currencyCode=USD'
             . '&$filter=' . urlencode($filter);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            $errors[] = "cURL error for {$skuId}: {$curlError}";
            continue;
        }
        
        if ($httpCode !== 200 || !$response) {
            $errors[] = "HTTP {$httpCode} for {$skuId}";
            continue;
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "JSON parse error for {$skuId}";
            continue;
        }
        
        $items = $data['Items'] ?? [];
        
        if (empty($items)) {
            continue;
        }
        
        // Processa preços por região
        $skuPrices = [];
        foreach ($items as $item) {
            $productName = $item['productName'] ?? '';
            
            // Para Linux, filtramos no PHP (API não suporta 'not contains')
            if ($os === 'Linux' && stripos($productName, 'Windows') !== false) {
                continue;
            }
            
            $region = strtolower($item['armRegionName'] ?? '');
            $location = $item['location'] ?? $region;
            $price = (float)($item['retailPrice'] ?? 0);
            
            if ($region === '' || $price <= 0) continue;
            if (stripos($item['skuName'] ?? '', 'Spot') !== false) continue;
            if (stripos($item['meterName'] ?? '', 'Spot') !== false) continue;
            if (stripos($item['skuName'] ?? '', 'Low Priority') !== false) continue;
            
            // Pega menor preço por região
            if (!isset($skuPrices[$region]) || $price < $skuPrices[$region]['price']) {
                $skuPrices[$region] = [
                    'price' => $price,
                    'priceMonthly' => $price * 730,
                    'location' => $location,
                ];
            }
            
            if (!isset($regionStats[$region])) {
                $regionStats[$region] = [
                    'location' => $location,
                    'code' => $region,
                    'geography' => inferGeography($region, $location),
                    'prices' => [],
                    'total' => 0,
                ];
            }
        }
        
        $results[$skuId] = [
            'info' => $skuInfo,
            'prices' => $skuPrices,
            'minPrice' => !empty($skuPrices) ? min(array_column($skuPrices, 'price')) : 0,
            'maxPrice' => !empty($skuPrices) ? max(array_column($skuPrices, 'price')) : 0,
            'avgPrice' => !empty($skuPrices) ? array_sum(array_column($skuPrices, 'price')) / count($skuPrices) : 0,
        ];
        
        // Adiciona aos stats de região
        foreach ($skuPrices as $region => $priceData) {
            if (!isset($regionStats[$region])) {
                $regionStats[$region] = [
                    'location' => $priceData['location'],
                    'code' => $region,
                    'geography' => inferGeography($region, $priceData['location']),
                    'prices' => [],
                    'total' => 0,
                ];
            }
            $regionStats[$region]['prices'][$skuId] = $priceData['price'];
            $regionStats[$region]['total'] += $priceData['price'];
        }
    }
    
    if (empty($results)) {
        $errorMsg = 'Não foi possível obter preços da API.';
        if (!empty($errors)) {
            $errorMsg .= ' Detalhes: ' . implode('; ', array_slice($errors, 0, 3));
        }
        return ['error' => $errorMsg];
    }
    
    // Calcula média por região
    foreach ($regionStats as $region => &$stats) {
        $skuCount = count($stats['prices']);
        $stats['skuCount'] = $skuCount;
        $stats['avgPrice'] = $skuCount > 0 ? $stats['total'] / $skuCount : 0;
        $stats['avgMonthly'] = $stats['avgPrice'] * 730;
        $stats['coveragePct'] = count($results) > 0 ? ($skuCount / count($results)) * 100 : 0;
    }
    unset($stats);
    
    // Ordena regiões por preço médio
    uasort($regionStats, fn($a, $b) => $a['avgPrice'] <=> $b['avgPrice']);
    
    return [
        'skus' => $results,
        'regions' => $regionStats,
        'mainRegions' => $mainRegions,
        'os' => $os,
        'totalSkus' => count($results),
        'totalRegions' => count($regionStats),
        'lastUpdatedUtc' => gmdate('Y-m-d H:i:s') . ' UTC',
    ];
}

/**
 * Classificação aproximada por geografia a partir do código da região.
 */
function inferGeography(string $regionCode, string $location): string
{
    $code = strtolower($regionCode);

    if (str_starts_with($code, 'eastus') || str_starts_with($code, 'westus') || str_contains($code, 'centralus') || str_starts_with($code, 'northcentral') || str_starts_with($code, 'southcentral') || str_starts_with($code, 'usgov')) {
        return 'US';
    }

    if (str_contains($code, 'brazil') || str_starts_with($code, 'chile') || str_starts_with($code, 'mexico')) {
        return 'South America';
    }

    if (str_starts_with($code, 'canada')) {
        return 'Canada';
    }

    if (str_contains($code, 'europe') || str_starts_with($code, 'uk') || str_starts_with($code, 'germany') || str_starts_with($code, 'france') || str_starts_with($code, 'norway') || str_starts_with($code, 'sweden') || str_starts_with($code, 'switzerland') || str_starts_with($code, 'italy') || str_starts_with($code, 'spain') || str_starts_with($code, 'poland') || str_starts_with($code, 'belgium') || str_starts_with($code, 'austria')) {
        return 'Europe';
    }

    if (str_contains($code, 'asia') || str_contains($code, 'india') || str_starts_with($code, 'japan') || str_starts_with($code, 'korea') || str_starts_with($code, 'southeast') || str_starts_with($code, 'eastasia') || str_starts_with($code, 'australia') || str_starts_with($code, 'newzealand') || str_starts_with($code, 'indonesia') || str_starts_with($code, 'malaysia')) {
        return 'Asia Pacific';
    }

    if (str_starts_with($code, 'uae') || str_starts_with($code, 'qatar') || str_starts_with($code, 'israel')) {
        return 'Middle East';
    }

    if (str_starts_with($code, 'southafrica')) {
        return 'Africa';
    }

    return $location !== '' ? $location : 'Other';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparativo de Regiões Azure | TD SYNNEX</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        /* ══════════════════════════════════════════════════════════
           Page-specific styles
           ══════════════════════════════════════════════════════════ */
        .page-content { padding: 1.5rem 2rem 3rem; background: var(--light-gray); min-height: calc(100vh - 56px); }
        
        /* Page Header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--charcoal);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 svg { width: 28px; height: 28px; color: var(--teal); }
        .page-header p { color: var(--gray); font-size: .88rem; margin-top: 4px; }
        
        /* Filters Panel */
        .filters-panel {
            background: #fff;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            border: 1px solid #e5e7eb;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label {
            font-size: .75rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .filter-group select, .filter-group input {
            padding: 10px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: .88rem;
            font-family: inherit;
            background: #fff;
            min-width: 160px;
            transition: all .15s;
        }
        .filter-group select:focus, .filter-group input:focus {
            outline: none;
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(0,87,88,.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--teal) 0%, var(--teal-dark) 100%);
            color: #fff;
            border: none;
            padding: 11px 24px;
            border-radius: 8px;
            font-size: .88rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all .2s;
            font-family: inherit;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,87,88,.25); }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; transform: none; }
        .btn-primary svg { width: 18px; height: 18px; }
        
        /* SKU Tags */
        .sku-tags { display: flex; flex-wrap: wrap; gap: 6px; flex: 1; }
        .sku-tag {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 600;
            transition: all .15s;
        }
        .sku-tag.burstable { background: #fef3c7; color: #92400e; }
        .sku-tag.general { background: #dbeafe; color: #1e40af; }
        .sku-tag.memory { background: #ede9fe; color: #5b21b6; }
        .sku-tag.compute { background: #dcfce7; color: #166534; }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid #e5e7eb;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 4px;
        }
        .stat-card.teal::before { background: var(--teal); }
        .stat-card.blue::before { background: var(--blue); }
        .stat-card.green::before { background: #16a34a; }
        .stat-card.red::before { background: #dc2626; }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 4px;
        }
        .stat-label { font-size: .78rem; color: var(--gray); font-weight: 500; }

        /* Regions Explorer (CloudPrice-like) */
        .regions-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .regions-summary-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 1rem 1.1rem;
        }
        .regions-summary-title {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .45px;
            color: var(--gray);
            font-weight: 700;
            margin-bottom: .4rem;
        }
        .regions-summary-value {
            font-size: 1.15rem;
            color: var(--charcoal);
            font-weight: 800;
            line-height: 1.2;
        }
        .regions-summary-sub {
            font-size: .78rem;
            color: var(--gray);
            margin-top: .35rem;
        }
        .regions-explorer-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .regions-explorer-header {
            padding: .9rem 1.1rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .75rem;
        }
        .regions-explorer-title {
            font-size: .92rem;
            font-weight: 700;
            color: var(--charcoal);
        }
        .regions-controls {
            display: flex;
            align-items: center;
            gap: .55rem;
            flex-wrap: wrap;
        }
        .regions-controls select,
        .regions-controls input {
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: .8rem;
            font-family: inherit;
            background: #fff;
        }
        .regions-controls input { min-width: 220px; }
        .regions-ranking-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .8rem;
        }
        .regions-ranking-table th,
        .regions-ranking-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f4f4f5;
            text-align: left;
            white-space: nowrap;
        }
        .regions-ranking-table th {
            background: #fafafa;
            color: var(--charcoal);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .45px;
            font-size: .68rem;
        }
        .regions-ranking-table td.rank-cell {
            font-weight: 800;
            color: var(--teal-dark);
        }
        .regions-ranking-table .text-right { text-align: right; }
        .regions-ranking-table .region-code {
            color: var(--gray);
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: .74rem;
        }
        .coverage-badge {
            background: #ecfeff;
            color: #0f766e;
            border: 1px solid #bae6fd;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: .72rem;
            font-weight: 700;
        }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .chart-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        .chart-card.full-width { grid-column: 1 / -1; }
        .chart-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .chart-header h3 {
            font-size: .92rem;
            font-weight: 700;
            color: var(--charcoal);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chart-header h3 svg { width: 18px; height: 18px; color: var(--teal); }
        .chart-header .badge {
            background: #f0f0f0;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: .72rem;
            font-weight: 600;
            color: var(--gray);
        }
        .chart-body { padding: 1rem 1.25rem; }
        .chart-container { position: relative; height: 320px; }
        .chart-container-lg { position: relative; height: 400px; }
        
        /* Data Table */
        .data-table-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        .data-table-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .data-table-header h3 {
            font-size: .92rem;
            font-weight: 700;
            color: var(--charcoal);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .data-table-header h3 svg { width: 18px; height: 18px; color: var(--teal); }
        .data-table-wrapper { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .8rem;
        }
        .data-table th, .data-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #f5f5f5;
        }
        .data-table th {
            background: #fafafa;
            font-weight: 700;
            color: var(--charcoal);
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            white-space: nowrap;
        }
        .data-table th.sticky { position: sticky; left: 0; z-index: 2; background: #fafafa; }
        .data-table td.sticky { position: sticky; left: 0; z-index: 1; background: #fff; font-weight: 600; }
        .data-table tbody tr:hover td { background: #f8fafc; }
        .data-table .text-right { text-align: right; }
        .data-table .text-success { color: #16a34a; font-weight: 700; }
        .data-table .text-danger { color: #dc2626; }
        .data-table .text-muted { color: #9ca3af; }
        .data-table .avg-col { background: #f0fdf4 !important; font-weight: 700; }
        
        /* Loading State */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 500;
        }
        .loading-spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #f0f0f0;
            border-top-color: var(--teal);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { margin-top: 1rem; font-size: .92rem; color: var(--gray); font-weight: 500; }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }
        .empty-state-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e6f4f4 0%, #dbeafe 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .empty-state-icon svg { width: 40px; height: 40px; color: var(--teal); }
        .empty-state h3 { font-size: 1.25rem; font-weight: 700; color: var(--charcoal); margin-bottom: .5rem; }
        .empty-state p { color: var(--gray); font-size: .92rem; max-width: 500px; margin: 0 auto 1.5rem; }
        
        /* Legend */
        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f0f0f0;
        }
        .legend-item { display: flex; align-items: center; gap: 6px; font-size: .75rem; color: var(--gray); }
        .legend-dot { width: 10px; height: 10px; border-radius: 3px; }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .regions-summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .page-content { padding: 1rem; }
            .filters-panel { flex-direction: column; align-items: stretch; }
            .stats-grid { grid-template-columns: 1fr; }
            .regions-summary-grid { grid-template-columns: 1fr; }
            .regions-controls input { min-width: 100%; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../templates/topbar.php'; ?>

<main class="page-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                </svg>
                Comparativo de VMs por Região Azure
            </h1>
            <p>Analise preços de VMs em tempo real e identifique as melhores oportunidades de economia</p>
        </div>
    </div>

    <!-- Filters Panel -->
    <div class="filters-panel">
        <div class="filter-group">
            <label>Máquina Virtual</label>
            <select id="skuFilter" onchange="fetchPrices()">
                <?php foreach ($vmSkus as $skuId => $skuInfo): ?>
                <option value="<?= $skuId ?>" <?= $skuId === 'Standard_D4s_v5' ? 'selected' : '' ?>>
                    <?= $skuInfo['fullName'] ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>Sistema Operacional</label>
            <select id="osFilter" onchange="fetchPrices()">
                <option value="Linux" selected>Linux</option>
                <option value="Windows">Windows</option>
            </select>
        </div>
        
        <button type="button" class="btn-primary" id="fetchBtn" onclick="fetchPrices()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            Consultar Preços
        </button>
        
        <div class="sku-tags">
            <?php foreach ($vmSkus as $sku => $info): 
                $seriesClass = match(true) {
                    $info['series'] === 'Burstable' => 'burstable',
                    str_contains($info['series'], 'Memory') => 'memory',
                    str_contains($info['series'], 'Compute') => 'compute',
                    default => 'general'
                };
            ?>
            <span class="sku-tag <?= $seriesClass ?>"><?= $info['name'] ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Results Container -->
    <div id="resultsContainer">
        <!-- Empty State -->
        <div class="empty-state" id="emptyState">
            <div class="empty-state-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                </svg>
            </div>
            <h3>Comparativo de Preços de VMs</h3>
            <p>Consulte a API de preços da Microsoft Azure para comparar custos de <strong><?= count($vmSkus) ?> SKUs principais</strong> em tempo real. Visualize gráficos executivos e identifique as melhores oportunidades de economia.</p>
            <button type="button" class="btn-primary" onclick="fetchPrices()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                </svg>
                Iniciar Consulta
            </button>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" style="display:none;">
        <div class="loading-spinner"></div>
        <div class="loading-text">Consultando preços da API Azure...</div>
    </div>
</main>

<script>
// Chart instances
let chartSkusByRegion = null;
let chartCheapest = null;
let chartExpensive = null;
let chartBySeries = null;

// Chart colors
const chartColors = [
    'rgba(0, 87, 88, 0.85)',
    'rgba(0, 120, 212, 0.85)',
    'rgba(16, 163, 74, 0.85)',
    'rgba(234, 88, 12, 0.85)',
    'rgba(139, 92, 246, 0.85)',
    'rgba(236, 72, 153, 0.85)',
    'rgba(20, 184, 166, 0.85)',
    'rgba(245, 158, 11, 0.85)',
    'rgba(99, 102, 241, 0.85)',
    'rgba(239, 68, 68, 0.85)',
    'rgba(34, 197, 94, 0.85)',
];

const seriesColors = {
    'Burstable': 'rgba(245, 158, 11, 0.85)',
    'General Purpose': 'rgba(0, 120, 212, 0.85)',
    'Memory Optimized': 'rgba(139, 92, 246, 0.85)',
    'Compute Optimized': 'rgba(16, 163, 74, 0.85)'
};

async function fetchPrices() {
    const os = document.getElementById('osFilter').value;
    const sku = document.getElementById('skuFilter').value;
    const btn = document.getElementById('fetchBtn');
    const loading = document.getElementById('loadingOverlay');
    const container = document.getElementById('resultsContainer');
    
    btn.disabled = true;
    loading.style.display = 'flex';
    
    try {
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('os', os);
        formData.append('sku', sku);
        
        const response = await fetch('comparativo-regioes.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.error) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon" style="background:linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color:#dc2626;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <h3>Erro na Consulta</h3>
                    <p>${data.error}</p>
                </div>
            `;
        } else {
            renderResults(data);
        }
    } catch (error) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon" style="background:linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color:#dc2626;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                </div>
                <h3>Erro de Conexão</h3>
                <p>${error.message}</p>
            </div>
        `;
    } finally {
        btn.disabled = false;
        loading.style.display = 'none';
    }
}

function renderResults(data) {
    const container = document.getElementById('resultsContainer');
    const regions = Object.entries(data.regions);
    const cheapestRegion = regions[0];
    const expensiveRegion = regions[regions.length - 1];
    const cheapestValue = cheapestRegion ? cheapestRegion[1].avgPrice : 0;
    const expensiveValue = expensiveRegion ? expensiveRegion[1].avgPrice : 0;
    const globalSpread = expensiveValue > 0 ? ((expensiveValue - cheapestValue) / expensiveValue) * 100 : 0;
    
    container.innerHTML = `
        <!-- Regions Summary (CloudPrice-like) -->
        <div class="regions-summary-grid">
            <div class="regions-summary-card">
                <div class="regions-summary-title">Total de Regiões</div>
                <div class="regions-summary-value">${data.totalRegions}</div>
                <div class="regions-summary-sub">com preços válidos na API</div>
            </div>
            <div class="regions-summary-card">
                <div class="regions-summary-title">Região Mais Barata</div>
                <div class="regions-summary-value">${cheapestRegion ? cheapestRegion[1].location : '-'}</div>
                <div class="regions-summary-sub">$${cheapestValue.toFixed(4)}/hora (média)</div>
            </div>
            <div class="regions-summary-card">
                <div class="regions-summary-title">Região Mais Cara</div>
                <div class="regions-summary-value">${expensiveRegion ? expensiveRegion[1].location : '-'}</div>
                <div class="regions-summary-sub">$${expensiveValue.toFixed(4)}/hora (média)</div>
            </div>
            <div class="regions-summary-card">
                <div class="regions-summary-title">Diferença Global</div>
                <div class="regions-summary-value">${globalSpread.toFixed(1)}%</div>
                <div class="regions-summary-sub">gap entre menor e maior custo</div>
            </div>
        </div>

        <div style="font-size:.77rem;color:var(--gray);margin-bottom:1rem;">Última atualização: ${data.lastUpdatedUtc}</div>

        <div class="regions-explorer-card">
            <div class="regions-explorer-header">
                <div class="regions-explorer-title">Azure Regions - Ranking de Custo</div>
                <div class="regions-controls">
                    <select id="rankingViewMode" onchange="renderRegionRanking(dataCache)">
                        <option value="hourly" selected>Mostrar: Preço médio/hora</option>
                        <option value="monthly">Mostrar: Preço médio/mês</option>
                    </select>
                    <select id="rankingGroupMode" onchange="renderRegionRanking(dataCache)">
                        <option value="region" selected>Agrupar: Por região</option>
                        <option value="geography">Agrupar: Por geografia</option>
                    </select>
                    <input id="rankingSearch" type="text" placeholder="Buscar região ou código..." oninput="renderRegionRanking(dataCache)">
                </div>
            </div>
            <div class="data-table-wrapper">
                <table class="regions-ranking-table" id="regionsRankingTable"></table>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card teal">
                <div class="stat-value" style="color:var(--teal);">${data.totalSkus}</div>
                <div class="stat-label">SKUs Analisados</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-value" style="color:var(--blue);">${data.totalRegions}</div>
                <div class="stat-label">Regiões Encontradas</div>
            </div>
            <div class="stat-card green">
                <div class="stat-value" style="color:#16a34a;font-size:1.2rem;">${cheapestRegion ? cheapestRegion[1].location : '-'}</div>
                <div class="stat-label">Região Mais Barata (Média)</div>
            </div>
            <div class="stat-card red">
                <div class="stat-value" style="color:#dc2626;font-size:1.2rem;">${expensiveRegion ? expensiveRegion[1].location : '-'}</div>
                <div class="stat-label">Região Mais Cara (Média)</div>
            </div>
        </div>
        
        <!-- Charts Grid -->
        <div class="charts-grid" id="topChartsGrid">
            <!-- Main Chart: SKUs by Region -->
            <div class="chart-card full-width">
                <div class="chart-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                        Preço/Hora por SKU nas Regiões Principais
                    </h3>
                    <span class="badge">${data.os}</span>
                </div>
                <div class="chart-body">
                    <div class="chart-container-lg"><canvas id="chartSkusByRegion"></canvas></div>
                </div>
            </div>
            
            <!-- Cheapest Regions -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                        </svg>
                        Top 10 Regiões Mais Baratas
                    </h3>
                </div>
                <div class="chart-body">
                    <div class="chart-container"><canvas id="chartCheapest"></canvas></div>
                </div>
            </div>
            
            <!-- Expensive Regions -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        Top 10 Regiões Mais Caras
                    </h3>
                </div>
                <div class="chart-body">
                    <div class="chart-container"><canvas id="chartExpensive"></canvas></div>
                </div>
            </div>
            
            <!-- By Series -->
            <div class="chart-card full-width">
                <div class="chart-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                        </svg>
                        Preço Médio por Série de VM
                    </h3>
                </div>
                <div class="chart-body">
                    <div class="chart-container"><canvas id="chartBySeries"></canvas></div>
                    <div class="chart-legend">
                        <div class="legend-item"><div class="legend-dot" style="background:${seriesColors['Burstable']};"></div>Burstable</div>
                        <div class="legend-item"><div class="legend-dot" style="background:${seriesColors['General Purpose']};"></div>General Purpose</div>
                        <div class="legend-item"><div class="legend-dot" style="background:${seriesColors['Memory Optimized']};"></div>Memory Optimized</div>
                        <div class="legend-item"><div class="legend-dot" style="background:${seriesColors['Compute Optimized']};"></div>Compute Optimized</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Data Table -->
        <div class="data-table-card">
            <div class="data-table-header">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125" />
                    </svg>
                    Tabela de Preços Detalhada
                </h3>
                <span style="font-size:.78rem;color:var(--gray);">Preços em USD/hora</span>
            </div>
            <div class="data-table-wrapper">
                <table class="data-table" id="dataTable"></table>
            </div>
        </div>
    `;
    
    renderRegionRanking(data);

    // Keep the charts at the top of the results area.
    const topChartsGrid = container.querySelector('#topChartsGrid');
    if (topChartsGrid) {
        container.prepend(topChartsGrid);
    }

    // Render charts
    renderCharts(data);
    renderTable(data);
}

let dataCache = null;

function renderRegionRanking(data) {
    if (!data) return;

    dataCache = data;

    const table = document.getElementById('regionsRankingTable');
    if (!table) return;

    const viewModeEl = document.getElementById('rankingViewMode');
    const groupModeEl = document.getElementById('rankingGroupMode');
    const searchEl = document.getElementById('rankingSearch');

    const viewMode = viewModeEl ? viewModeEl.value : 'hourly';
    const groupMode = groupModeEl ? groupModeEl.value : 'region';
    const search = (searchEl ? searchEl.value : '').trim().toLowerCase();

    const regions = Object.entries(data.regions).map(([code, info]) => ({
        code,
        location: info.location,
        geography: info.geography || 'Other',
        coveragePct: info.coveragePct || 0,
        avgPrice: info.avgPrice || 0,
        avgMonthly: info.avgMonthly || 0,
        skuCount: info.skuCount || 0,
    }));

    if (groupMode === 'geography') {
        const grouped = {};

        regions.forEach((r) => {
            const key = r.geography;
            if (!grouped[key]) {
                grouped[key] = {
                    geography: key,
                    regionCount: 0,
                    sumCoverage: 0,
                    sumAvgPrice: 0,
                    sumAvgMonthly: 0,
                };
            }
            grouped[key].regionCount += 1;
            grouped[key].sumCoverage += r.coveragePct;
            grouped[key].sumAvgPrice += r.avgPrice;
            grouped[key].sumAvgMonthly += r.avgMonthly;
        });

        let rows = Object.values(grouped).map((g) => ({
            geography: g.geography,
            regionCount: g.regionCount,
            coveragePct: g.regionCount > 0 ? g.sumCoverage / g.regionCount : 0,
            avgPrice: g.regionCount > 0 ? g.sumAvgPrice / g.regionCount : 0,
            avgMonthly: g.regionCount > 0 ? g.sumAvgMonthly / g.regionCount : 0,
        }));

        if (search !== '') {
            rows = rows.filter((r) => r.geography.toLowerCase().includes(search));
        }

        rows.sort((a, b) => (viewMode === 'monthly' ? a.avgMonthly - b.avgMonthly : a.avgPrice - b.avgPrice));

        table.innerHTML = `
            <thead>
                <tr>
                    <th>#</th>
                    <th>Geografia</th>
                    <th class="text-right">Qtd Regiões</th>
                    <th class="text-right">Cobertura Média</th>
                    <th class="text-right">Preço Médio</th>
                </tr>
            </thead>
            <tbody>
                ${rows.map((r, idx) => `
                    <tr>
                        <td class="rank-cell">${idx + 1}</td>
                        <td>${r.geography}</td>
                        <td class="text-right">${r.regionCount}</td>
                        <td class="text-right"><span class="coverage-badge">${r.coveragePct.toFixed(0)}%</span></td>
                        <td class="text-right">${viewMode === 'monthly' ? ('$' + r.avgMonthly.toFixed(2) + '/mês') : ('$' + r.avgPrice.toFixed(4) + '/hora')}</td>
                    </tr>
                `).join('')}
            </tbody>
        `;

        return;
    }

    let rows = regions;
    if (search !== '') {
        rows = rows.filter((r) => r.location.toLowerCase().includes(search) || r.code.toLowerCase().includes(search) || r.geography.toLowerCase().includes(search));
    }

    rows.sort((a, b) => (viewMode === 'monthly' ? a.avgMonthly - b.avgMonthly : a.avgPrice - b.avgPrice));

    table.innerHTML = `
        <thead>
            <tr>
                <th>#</th>
                <th>Geografia</th>
                <th>Região</th>
                <th>Código</th>
                <th class="text-right">Cobertura</th>
                <th class="text-right">Preço Médio</th>
            </tr>
        </thead>
        <tbody>
            ${rows.map((r, idx) => `
                <tr>
                    <td class="rank-cell">${idx + 1}</td>
                    <td>${r.geography}</td>
                    <td>${r.location}</td>
                    <td class="region-code">${r.code}</td>
                    <td class="text-right"><span class="coverage-badge">${r.coveragePct.toFixed(0)}%</span></td>
                    <td class="text-right">${viewMode === 'monthly' ? ('$' + r.avgMonthly.toFixed(2) + '/mês') : ('$' + r.avgPrice.toFixed(4) + '/hora')}</td>
                </tr>
            `).join('')}
        </tbody>
    `;
}

function renderCharts(data) {
    const skuData = data.skus;
    const regionData = data.regions;
    const mainRegions = data.mainRegions;
    
    // Destroy existing charts
    [chartSkusByRegion, chartCheapest, chartExpensive, chartBySeries].forEach(c => c?.destroy());
    
    // Chart 1: SKUs by Region
    const ctx1 = document.getElementById('chartSkusByRegion').getContext('2d');
    const regionLabels = Object.values(mainRegions);
    const regionKeys = Object.keys(mainRegions);
    
    const datasets1 = [];
    Object.entries(skuData).forEach(([skuId, sku], idx) => {
        const prices = regionKeys.map(r => sku.prices[r]?.price || null);
        datasets1.push({
            label: sku.info.name,
            data: prices,
            backgroundColor: chartColors[idx % chartColors.length],
            borderRadius: 3
        });
    });
    
    chartSkusByRegion = new Chart(ctx1, {
        type: 'bar',
        data: { labels: regionLabels, datasets: datasets1 },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': $' + (ctx.raw?.toFixed(4) || 'N/A') + '/hora' } }
            },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'USD/hora', font: { size: 11 } }, ticks: { callback: v => '$' + v.toFixed(3), font: { size: 10 } } },
                x: { ticks: { font: { size: 10 } } }
            }
        }
    });
    
    // Chart 2: Cheapest Regions
    const ctx2 = document.getElementById('chartCheapest').getContext('2d');
    const cheapestRegions = Object.entries(regionData).slice(0, 10);
    chartCheapest = new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: cheapestRegions.map(([k, v]) => v.location),
            datasets: [{
                label: 'Preço Médio',
                data: cheapestRegions.map(([k, v]) => v.avgPrice),
                backgroundColor: 'rgba(22, 163, 74, 0.8)',
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => '$' + ctx.raw.toFixed(4) + '/hora' } } },
            scales: { x: { beginAtZero: true, ticks: { callback: v => '$' + v.toFixed(3), font: { size: 10 } } }, y: { ticks: { font: { size: 10 } } } }
        }
    });
    
    // Chart 3: Expensive Regions
    const ctx3 = document.getElementById('chartExpensive').getContext('2d');
    const allRegions = Object.entries(regionData);
    const expensiveRegions = allRegions.slice(-10).reverse();
    chartExpensive = new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: expensiveRegions.map(([k, v]) => v.location),
            datasets: [{
                label: 'Preço Médio',
                data: expensiveRegions.map(([k, v]) => v.avgPrice),
                backgroundColor: 'rgba(220, 38, 38, 0.8)',
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => '$' + ctx.raw.toFixed(4) + '/hora' } } },
            scales: { x: { beginAtZero: true, ticks: { callback: v => '$' + v.toFixed(3), font: { size: 10 } } }, y: { ticks: { font: { size: 10 } } } }
        }
    });
    
    // Chart 4: By Series
    const ctx4 = document.getElementById('chartBySeries').getContext('2d');
    const seriesAvg = {};
    Object.values(skuData).forEach(sku => {
        const series = sku.info.series;
        if (!seriesAvg[series]) seriesAvg[series] = { total: 0, count: 0 };
        seriesAvg[series].total += sku.avgPrice;
        seriesAvg[series].count++;
    });
    const seriesLabels = Object.keys(seriesAvg);
    const seriesValues = seriesLabels.map(s => seriesAvg[s].total / seriesAvg[s].count);
    
    chartBySeries = new Chart(ctx4, {
        type: 'bar',
        data: {
            labels: seriesLabels,
            datasets: [{
                label: 'Preço Médio/Hora',
                data: seriesValues,
                backgroundColor: seriesLabels.map(s => seriesColors[s] || chartColors[0]),
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => '$' + ctx.raw.toFixed(4) + '/hora' } } },
            scales: { y: { beginAtZero: true, ticks: { callback: v => '$' + v.toFixed(3), font: { size: 11 } } }, x: { ticks: { font: { size: 11, weight: 600 } } } }
        }
    });
}

function renderTable(data) {
    const table = document.getElementById('dataTable');
    const skus = Object.entries(data.skus);
    const regions = Object.entries(data.regions).slice(0, 20);
    
    let html = `
        <thead>
            <tr>
                <th class="sticky">Região</th>
                ${skus.map(([id, s]) => `<th class="text-right">${s.info.name}</th>`).join('')}
                <th class="text-right avg-col">Média</th>
            </tr>
        </thead>
        <tbody>
    `;
    
    regions.forEach(([region, info]) => {
        html += `<tr><td class="sticky">${info.location}</td>`;
        skus.forEach(([skuId, sku]) => {
            const price = sku.prices[region]?.price;
            if (price !== undefined) {
                const isMin = price === sku.minPrice;
                const isMax = price === sku.maxPrice;
                html += `<td class="text-right ${isMin ? 'text-success' : (isMax ? 'text-danger' : '')}">$${price.toFixed(4)}</td>`;
            } else {
                html += `<td class="text-right text-muted">-</td>`;
            }
        });
        html += `<td class="text-right avg-col">$${info.avgPrice.toFixed(4)}</td></tr>`;
    });
    
    html += '</tbody>';
    table.innerHTML = html;
}

// Auto-load on page open
document.addEventListener('DOMContentLoaded', fetchPrices);
</script>
</body>
</html>
