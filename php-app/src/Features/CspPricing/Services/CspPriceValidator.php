<?php
declare(strict_types=1);

namespace App\Features\CspPricing\Services;

/**
 * Valida e compara dados de modelos CSP (MOSA, MPA, MCA) com a API pública de preços do Azure.
 * Referência: https://learn.microsoft.com/en-us/azure/cost-management-billing/dataset-schema/schema-index
 * Export versão: 2019-11-01
 */
class CspPriceValidator
{
    private const API_URL   = 'https://prices.azure.com/api/retail/prices';
    private const API_VER   = '2023-01-01-preview';
    private const TIMEOUT   = 30;
    private const BATCH     = 8;
    private const CACHE_KEY = 'cspValidationCache';

    /**
     * Colunas obrigatórias para comparação com API Azure
     * Nota: ResourceLocation pode estar como 'resourcelocation' ou 'location'
     *       ServiceName pode estar como 'servicename', 'metercategory' ou 'consumedservice'
     */
    private const REQUIRED_COLUMNS = [
        'meterid',
        'metername',
        'usagequantity',
        'unitofmeasure',
        'productname',
        'quantity',
    ];

    /**
     * Colunas numéricas que precisam de validação de tipo
     */
    private const NUMERIC_COLUMNS = [
        'usagequantity',
        'quantity',
    ];

    /** @var array Configuração de mapeamento de campos */
    private array $fieldMapping;

    /** @var array Mapeamento de regiões */
    private array $regionMapping;

    /** @var array Campos de comparação com pesos */
    private array $comparisonFields;

    /** @var array Thresholds de score */
    private array $scoreThresholds;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION[self::CACHE_KEY])) {
            $_SESSION[self::CACHE_KEY] = [];
        }

        // Carrega configuração de mapeamento
        $config = require __DIR__ . '/config/api-field-mapping.php';
        $this->fieldMapping     = $config['fieldMapping'] ?? [];
        $this->regionMapping    = $config['regionMapping'] ?? [];
        $this->comparisonFields = $config['comparisonFields'] ?? [];
        $this->scoreThresholds  = $config['scoreThresholds'] ?? [
            'maxScore'        => 33,
            'matchThreshold'  => 28,
            'partialThreshold'=> 20,
        ];
    }

    /**
     * Valida estrutura do CSV antes do processamento.
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array, 'columns' => array]
     */
    public function validateCsvStructure(string $path): array
    {
        $result = [
            'valid'    => true,
            'errors'   => [],
            'warnings' => [],
            'columns'  => [],
            'rowCount' => 0,
        ];

        if (!file_exists($path)) {
            $result['valid'] = false;
            $result['errors'][] = 'Arquivo não encontrado.';
            return $result;
        }

        $fh = fopen($path, 'r');
        if (!$fh) {
            $result['valid'] = false;
            $result['errors'][] = 'Não foi possível abrir o arquivo.';
            return $result;
        }

        // Remove BOM UTF-8 se presente
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        // Detecta separador
        $first = fgets($fh);
        rewind($fh);
        if ($bom === "\xEF\xBB\xBF") {
            fread($fh, 3);
        }
        $sep = substr_count((string)$first, ';') > substr_count((string)$first, ',') ? ';' : ',';

        // Lê cabeçalho
        $raw = fgetcsv($fh, 0, $sep);
        if (!$raw) {
            fclose($fh);
            $result['valid'] = false;
            $result['errors'][] = 'Arquivo sem cabeçalho válido.';
            return $result;
        }

        $headers = array_map(fn($h) => strtolower(trim((string)$h)), $raw);
        $result['columns'] = $headers;

        // Verifica colunas obrigatórias
        $missing = [];
        foreach (self::REQUIRED_COLUMNS as $required) {
            if (!in_array($required, $headers, true)) {
                $missing[] = $required;
            }
        }

        // Verifica colunas alternativas
        // ServiceName: pode estar em servicename, metercategory ou consumedservice
        $hasServiceName = in_array('servicename', $headers, true) || 
                          in_array('metercategory', $headers, true) ||
                          in_array('consumedservice', $headers, true);
        if (!$hasServiceName) {
            $missing[] = 'servicename (ou metercategory/consumedservice)';
        }

        // ResourceLocation: pode estar em resourcelocation ou location
        $hasLocation = in_array('resourcelocation', $headers, true) || 
                       in_array('location', $headers, true);
        if (!$hasLocation) {
            $result['warnings'][] = 'Coluna ResourceLocation/Location não encontrada - comparação de região não será feita.';
        }

        if (!empty($missing)) {
            $result['valid'] = false;
            $result['errors'][] = 'Colunas obrigatórias ausentes: ' . implode(', ', array_map('strtoupper', $missing));
        }

        // Conta linhas e valida tipos de dados
        $rowNum = 1;
        $numericErrors = 0;
        $validRows = 0;

        while (($cols = fgetcsv($fh, 0, $sep)) !== false) {
            $rowNum++;
            if (count($cols) < count($headers)) {
                $result['warnings'][] = "Linha {$rowNum}: número de colunas inconsistente.";
                continue;
            }

            $row = array_combine($headers, $cols);
            if (!$row) {
                continue;
            }

            // Valida campos numéricos
            foreach (self::NUMERIC_COLUMNS as $numCol) {
                if (isset($row[$numCol]) && !empty($row[$numCol])) {
                    $val = str_replace([',', ' '], ['.', ''], $row[$numCol]);
                    if (!is_numeric($val)) {
                        $numericErrors++;
                        if ($numericErrors <= 5) {
                            $result['warnings'][] = "Linha {$rowNum}: {$numCol} não é numérico ('{$row[$numCol]}').";
                        }
                    }
                }
            }

            $validRows++;
        }

        if ($numericErrors > 5) {
            $result['warnings'][] = "...e mais " . ($numericErrors - 5) . " erros de tipo numérico.";
        }

        $result['rowCount'] = $validRows;
        fclose($fh);

        if ($validRows === 0) {
            $result['valid'] = false;
            $result['errors'][] = 'Nenhuma linha válida encontrada no arquivo.';
        }

        return $result;
    }

    /**
     * Faz o parse completo do CSV após validação.
     * @return array ['success' => bool, 'data' => array, 'error' => string|null]
     */
    public function parseCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        if (!$fh) {
            return ['success' => false, 'error' => 'Não foi possível abrir o arquivo.', 'data' => []];
        }

        // Remove BOM UTF-8 se presente
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        // Detecta separador
        $first = fgets($fh);
        rewind($fh);
        if ($bom === "\xEF\xBB\xBF") {
            fread($fh, 3);
        }
        $sep = substr_count((string)$first, ';') > substr_count((string)$first, ',') ? ';' : ',';

        // Lê cabeçalho
        $raw = fgetcsv($fh, 0, $sep);
        if (!$raw) {
            fclose($fh);
            return ['success' => false, 'error' => 'Arquivo sem cabeçalho.', 'data' => []];
        }
        $headers = array_map(fn($h) => strtolower(trim((string)$h)), $raw);

        $rows = [];
        while (($cols = fgetcsv($fh, 0, $sep)) !== false) {
            if (count($cols) < count($headers)) {
                continue;
            }

            $r = array_combine($headers, array_map('trim', $cols));
            if (!$r) {
                continue;
            }

            // Normaliza e extrai dados
            $meterId       = trim($r['meterid'] ?? '');
            $usageQty      = $this->parseNumeric($r['usagequantity'] ?? '0');
            $qty           = $this->parseNumeric($r['quantity'] ?? '0');

            if ($meterId === '' || ($usageQty <= 0 && $qty <= 0)) {
                continue;
            }

            // ServiceName: prioriza servicename > metercategory > consumedservice
            $serviceName = trim($r['servicename'] ?? $r['metercategory'] ?? $r['consumedservice'] ?? '');

            $rows[] = [
                'meterId'           => $meterId,
                'meterName'         => trim($r['metername'] ?? ''),
                'serviceName'       => $serviceName,
                'resourceType'      => trim($r['resourcetype'] ?? ''),
                'usageQuantity'     => $usageQty,
                'unitOfMeasure'     => trim($r['unitofmeasure'] ?? ''),
                'serviceFamily'     => trim($r['servicefamily'] ?? ''),
                'productId'         => trim($r['productid'] ?? ''),
                'productName'       => trim($r['productname'] ?? ''),
                'quantity'          => $qty,
                // Campos adicionais opcionais - ResourceLocation com fallback para location
                'resourceLocation'  => trim($r['resourcelocation'] ?? $r['location'] ?? ''),
                'subscriptionName'  => trim($r['subscriptionname'] ?? ''),
                'resourceGroup'     => trim($r['resourcegroup'] ?? $r['resourcegroupname'] ?? ''),
                'date'              => trim($r['date'] ?? $r['usagedatetime'] ?? ''),
                'effectivePrice'    => $this->parseNumeric($r['effectiveprice'] ?? $r['unitprice'] ?? '0'),
                'costInBilling'     => $this->parseNumeric($r['costinbillingcurrency'] ?? $r['cost'] ?? '0'),
                'billingCurrency'   => trim($r['billingcurrencycode'] ?? $r['currency'] ?? 'USD'),
            ];
        }

        fclose($fh);

        if (empty($rows)) {
            return ['success' => false, 'error' => 'Nenhuma linha válida encontrada.', 'data' => []];
        }

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    /**
     * Compara dados do CSV com a API pública de preços do Azure.
     * @return array ['results' => array, 'summary' => array]
     */
    public function compareWithAzureApi(array $rows): array
    {
        $results  = [];
        $matches  = 0;
        $mismatches = 0;
        $notFound = 0;

        // Agrupa por combinações únicas (meterId + productId + resourceLocation + unitOfMeasure)
        $uniqueSpecs = [];
        foreach ($rows as $idx => $row) {
            $key = $this->buildSpecKey($row);
            if (!isset($uniqueSpecs[$key])) {
                $uniqueSpecs[$key] = [
                    'key'              => $key,
                    'meterId'          => $row['meterId'],
                    'productId'        => $row['productId'],
                    'resourceLocation' => $row['resourceLocation'],
                    'unitOfMeasure'    => $row['unitOfMeasure'],
                    'meterName'        => $row['meterName'],
                    'serviceName'      => $row['serviceName'],
                    'rowIndices'       => [],
                ];
            }
            $uniqueSpecs[$key]['rowIndices'][] = $idx;
        }

        // Busca preços na API
        $apiPrices = $this->fetchPricesFromApi(array_values($uniqueSpecs));

        // Compara cada linha
        foreach ($rows as $idx => $row) {
            $key      = $this->buildSpecKey($row);
            $apiData  = $apiPrices[$key] ?? null;

            $comparison = [
                'rowIndex'     => $idx,
                'csvData'      => $row,
                'apiData'      => $apiData,
                'status'       => 'NOT_FOUND',
                'discrepancies'=> [],
                'matchScore'   => 0,
                'maxScore'     => 6,
            ];

            if ($apiData !== null) {
                $comparison = $this->compareRow($row, $apiData, $idx);
                
                if ($comparison['status'] === 'MATCH') {
                    $matches++;
                } else {
                    $mismatches++;
                }
            } else {
                $notFound++;
            }

            $results[] = $comparison;
        }

        $total = count($rows);
        $assertiveness = $total > 0 ? round(($matches / $total) * 100, 2) : 0;

        return [
            'results' => $results,
            'summary' => [
                'total'        => $total,
                'matches'      => $matches,
                'mismatches'   => $mismatches,
                'notFound'     => $notFound,
                'assertiveness'=> $assertiveness,
                'uniqueSpecs'  => count($uniqueSpecs),
            ],
        ];
    }

    /**
     * Compara uma linha do CSV com dados da API usando mapeamento configurado.
     * 
     * Campos comparados (baseado na resposta da API):
     * - meterId: GUID único do medidor (peso 10)
     * - meterName: Nome do medidor (peso 4)
     * - unitOfMeasure: Unidade de medida (peso 4)
     * - location: Região Azure (peso 4) - NÃO usar armRegionName
     * - productName: Nome do produto (peso 3)
     * - serviceName: Nome do serviço (peso 3)
     * - serviceFamily: Família do serviço (peso 2)
     * - productId: ID do produto (peso 2)
     * - currencyCode: Moeda (peso 1)
     */
    private function compareRow(array $csvRow, array $apiData, int $idx): array
    {
        $discrepancies = [];
        $matchedFields = [];
        $score = 0;
        $maxScore = $this->scoreThresholds['maxScore'];

        // ══════════════════════════════════════════════════════════════════════
        // CAMPOS PRIMÁRIOS (obrigatórios - peso alto)
        // ══════════════════════════════════════════════════════════════════════
        foreach ($this->comparisonFields['primary'] as $field) {
            $result = $this->compareField($csvRow, $apiData, $field);
            $score += $result['points'];
            if ($result['match']) {
                $matchedFields[] = $result;
            } else {
                $discrepancies[] = $result;
            }
        }

        // ══════════════════════════════════════════════════════════════════════
        // CAMPOS SECUNDÁRIOS (confirmação - peso médio)
        // ══════════════════════════════════════════════════════════════════════
        foreach ($this->comparisonFields['secondary'] as $field) {
            $result = $this->compareField($csvRow, $apiData, $field);
            $score += $result['points'];
            if ($result['match']) {
                $matchedFields[] = $result;
            } else {
                $discrepancies[] = $result;
            }
        }

        // ══════════════════════════════════════════════════════════════════════
        // CAMPOS TERCIÁRIOS (informativos - peso baixo)
        // ══════════════════════════════════════════════════════════════════════
        foreach ($this->comparisonFields['tertiary'] as $field) {
            $result = $this->compareField($csvRow, $apiData, $field);
            $score += $result['points'];
            if ($result['match']) {
                $matchedFields[] = $result;
            } else {
                $discrepancies[] = $result;
            }
        }

        // ══════════════════════════════════════════════════════════════════════
        // COMPARAÇÃO DE PREÇOS (informativo, não afeta score)
        // ══════════════════════════════════════════════════════════════════════
        $priceComparison = $this->comparePrices($csvRow, $apiData);

        // Define status baseado no score
        $status = 'MISMATCH';
        $scorePercent = $maxScore > 0 ? round(($score / $maxScore) * 100, 1) : 0;
        
        if ($score >= $this->scoreThresholds['matchThreshold']) {
            $status = 'MATCH';
        } elseif ($score >= $this->scoreThresholds['partialThreshold']) {
            $status = 'PARTIAL_MATCH';
        }

        return [
            'rowIndex'        => $idx,
            'csvData'         => $csvRow,
            'apiData'         => $apiData,
            'status'          => $status,
            'discrepancies'   => $discrepancies,
            'matchedFields'   => $matchedFields,
            'priceComparison' => $priceComparison,
            'matchScore'      => $score,
            'maxScore'        => $maxScore,
            'scorePercent'    => $scorePercent,
        ];
    }

    /**
     * Compara um campo específico entre CSV e API.
     */
    private function compareField(array $csvRow, array $apiData, array $fieldConfig): array
    {
        $csvField  = $fieldConfig['csv'];
        $apiField  = $fieldConfig['api'];
        $weight    = $fieldConfig['weight'] ?? 1;
        $exact     = $fieldConfig['exact'] ?? false;
        $fuzzy     = $fieldConfig['fuzzy'] ?? false;
        $normalizer= $fieldConfig['normalizer'] ?? null;

        // Obtém valores
        $csvValue = $this->getCsvFieldValue($csvRow, $csvField);
        $apiValue = $apiData[$apiField] ?? '';

        // Aplica normalização se necessário
        if ($normalizer === 'uom') {
            $csvNorm = $this->normalizeUom($csvValue);
            $apiNorm = $this->normalizeUom($apiValue);
        } elseif ($normalizer === 'region') {
            $csvNorm = $this->normalizeRegionToArmName($csvValue);
            $apiNorm = $this->normalizeRegionToArmName($apiValue);
        } else {
            $csvNorm = $this->normalizeString($csvValue);
            $apiNorm = $this->normalizeString($apiValue);
        }

        // Compara
        $match  = false;
        $points = 0;

        if ($csvValue === '' || $apiValue === '') {
            // Campo vazio em um dos lados - não penaliza mas não pontua
            $match = ($csvValue === '' && $apiValue === '');
            $points = 0;
        } elseif ($exact) {
            $match = (strtolower($csvNorm) === strtolower($apiNorm));
            $points = $match ? $weight : 0;
        } elseif ($fuzzy) {
            // Fuzzy: match se um contém o outro ou são iguais
            $match = (strtolower($csvNorm) === strtolower($apiNorm)) ||
                     str_contains(strtolower($apiNorm), strtolower($csvNorm)) ||
                     str_contains(strtolower($csvNorm), strtolower($apiNorm));
            $points = $match ? $weight : 0;
        } else {
            $match = (strtolower($csvNorm) === strtolower($apiNorm));
            $points = $match ? $weight : 0;
        }

        return [
            'field'     => $apiField,
            'csvField'  => $csvField,
            'csvValue'  => $csvValue,
            'apiValue'  => $apiValue,
            'csvNorm'   => $csvNorm,
            'apiNorm'   => $apiNorm,
            'match'     => $match,
            'points'    => $points,
            'maxPoints' => $weight,
        ];
    }

    /**
     * Obtém valor do campo no CSV considerando aliases.
     */
    private function getCsvFieldValue(array $row, string $field): string
    {
        $fieldLower = strtolower($field);
        
        // Mapeamento de campos CSV para chaves do array
        $fieldMap = [
            'meterid'           => 'meterId',
            'metername'         => 'meterName',
            'unitofmeasure'     => 'unitOfMeasure',
            'resourcelocation'  => 'resourceLocation',
            'productname'       => 'productName',
            'servicename'       => 'serviceName',
            'servicefamily'     => 'serviceFamily',
            'productid'         => 'productId',
            'billingcurrencycode'=> 'billingCurrency',
        ];

        $key = $fieldMap[$fieldLower] ?? $fieldLower;
        return trim((string)($row[$key] ?? ''));
    }

    /**
     * Compara preços entre CSV e API (informativo).
     */
    private function comparePrices(array $csvRow, array $apiData): array
    {
        $csvPrice = $csvRow['effectivePrice'] ?? 0;
        $apiRetail = (float)($apiData['retailPrice'] ?? 0);
        $apiUnit   = (float)($apiData['unitPrice'] ?? 0);
        $csvCurrency = $csvRow['billingCurrency'] ?? 'USD';
        $apiCurrency = $apiData['currencyCode'] ?? 'USD';

        $priceDiff = $csvPrice > 0 ? abs($csvPrice - $apiRetail) : 0;
        $priceDiffPercent = $csvPrice > 0 ? round(($priceDiff / $csvPrice) * 100, 2) : 0;

        return [
            'csvPrice'         => $csvPrice,
            'apiRetailPrice'   => $apiRetail,
            'apiUnitPrice'     => $apiUnit,
            'csvCurrency'      => $csvCurrency,
            'apiCurrency'      => $apiCurrency,
            'difference'       => $priceDiff,
            'differencePercent'=> $priceDiffPercent,
            'currencyMatch'    => strtoupper($csvCurrency) === strtoupper($apiCurrency),
        ];
    }

    /**
     * Normaliza região para armRegionName (ex: "BR South" → "brazilsouth").
     */
    private function normalizeRegionToArmName(string $region): string
    {
        $region = strtolower(trim($region));
        
        // Verifica mapeamento configurado
        if (isset($this->regionMapping[$region])) {
            return $this->regionMapping[$region]['armRegionName'] ?? $region;
        }
        
        // Fallback: remove espaços
        return preg_replace('/\s+/', '', $region);
    }

    /**
     * Busca preços na API pública do Azure usando curl_multi (paralelo).
     */
    private function fetchPricesFromApi(array $specs): array
    {
        $results = [];

        // Verifica cache
        $toFetch = [];
        foreach ($specs as $spec) {
            $key = $spec['key'];
            if (isset($_SESSION[self::CACHE_KEY][$key])) {
                $results[$key] = $_SESSION[self::CACHE_KEY][$key];
            } else {
                $toFetch[] = $spec;
            }
        }

        if (empty($toFetch)) {
            return $results;
        }

        // Busca em batches paralelos
        $batches = array_chunk($toFetch, self::BATCH);
        
        foreach ($batches as $batch) {
            $fetched = $this->multiFetch($batch);
            foreach ($fetched as $key => $data) {
                $results[$key] = $data;
                if ($data !== null) {
                    $_SESSION[self::CACHE_KEY][$key] = $data;
                }
            }
        }

        return $results;
    }

    /**
     * Executa múltiplas requisições em paralelo via curl_multi.
     */
    private function multiFetch(array $specs): array
    {
        $mh = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($specs as $spec) {
            $filter = $this->buildApiFilter($spec);
            $url    = self::API_URL . '?api-version=' . self::API_VER . '&$filter=' . urlencode($filter);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            curl_multi_add_handle($mh, $ch);
            $handles[$spec['key']] = $ch;
        }

        // Executa
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        // Processa resultados
        foreach ($handles as $key => $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $results[$key] = null;

            if ($httpCode === 200 && $response) {
                $json = json_decode($response, true);
                if (!empty($json['Items'])) {
                    // Seleciona o melhor match
                    $spec = array_values(array_filter($specs, fn($s) => $s['key'] === $key))[0] ?? [];
                    $best = $this->selectBestMatch($json['Items'], $spec);
                    $results[$key] = $best;
                }
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);

        return $results;
    }

    /**
     * Seleciona o melhor item da API baseado em pontuação de match.
     * 
     * Retorna todos os campos relevantes da API:
     * - meterId, meterName, productId, skuId, productName, skuName
     * - serviceName, serviceId, serviceFamily
     * - unitOfMeasure, currencyCode, retailPrice, unitPrice
     * - location, armRegionName
     * - type (priceType), effectiveStartDate, isPrimaryMeterRegion
     */
    private function selectBestMatch(array $items, array $spec): ?array
    {
        $best      = null;
        $bestScore = -1;

        $csvRegion = $this->normalizeRegionToArmName($spec['resourceLocation'] ?? '');
        $csvUom    = $this->normalizeUom($spec['unitOfMeasure'] ?? '');
        $csvMeter  = strtolower(trim($spec['meterName'] ?? ''));

        foreach ($items as $item) {
            $score = 0;

            // Região (usando location e armRegionName)
            $apiRegion = $this->normalizeRegionToArmName($item['location'] ?? $item['armRegionName'] ?? '');
            if ($csvRegion !== '' && $apiRegion === $csvRegion) {
                $score += 4;
            }
            
            // UnitOfMeasure (normalizado)
            $apiUom = $this->normalizeUom($item['unitOfMeasure'] ?? '');
            if ($csvUom !== '' && $apiUom === $csvUom) {
                $score += 4;
            }
            
            // MeterName
            if ($csvMeter !== '' && strtolower($item['meterName'] ?? '') === $csvMeter) {
                $score += 3;
            }
            
            // Consumption type (preferido para preços on-demand)
            if (($item['type'] ?? '') === 'Consumption') {
                $score += 2;
            }
            
            // isPrimaryMeterRegion = true (preferido)
            if (($item['isPrimaryMeterRegion'] ?? false) === true) {
                $score += 1;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        if ($best === null) {
            return null;
        }

        // Retorna TODOS os campos relevantes da API conforme documentação
        return [
            // Identificadores
            'meterId'             => $best['meterId'] ?? '',
            'meterName'           => $best['meterName'] ?? '',
            'productId'           => $best['productId'] ?? '',
            'skuId'               => $best['skuId'] ?? '',
            'productName'         => $best['productName'] ?? '',
            'skuName'             => $best['skuName'] ?? '',
            
            // Serviço
            'serviceName'         => $best['serviceName'] ?? '',
            'serviceId'           => $best['serviceId'] ?? '',
            'serviceFamily'       => $best['serviceFamily'] ?? '',
            
            // Medição e Preço
            'unitOfMeasure'       => $best['unitOfMeasure'] ?? '',
            'currencyCode'        => $best['currencyCode'] ?? 'USD',
            'retailPrice'         => (float)($best['retailPrice'] ?? 0),
            'unitPrice'           => (float)($best['unitPrice'] ?? 0),
            'tierMinimumUnits'    => (float)($best['tierMinimumUnits'] ?? 0),
            
            // Região
            'location'            => $best['location'] ?? '',
            'armRegionName'       => $best['armRegionName'] ?? '',
            
            // Tipo e Datas
            'type'                => $best['type'] ?? '',
            'priceType'           => $best['type'] ?? '',  // Alias para compatibilidade
            'effectiveStartDate'  => $best['effectiveStartDate'] ?? '',
            'effectiveEndDate'    => $best['effectiveEndDate'] ?? null,
            
            // Flags
            'isPrimaryMeterRegion'=> $best['isPrimaryMeterRegion'] ?? false,
            'armSkuName'          => $best['armSkuName'] ?? '',
            
            // Reservations/Savings (se aplicável)
            'reservationTerm'     => $best['reservationTerm'] ?? null,
            'savingsPlan'         => $best['savingsPlan'] ?? null,
            
            // Meta
            'matchScore'          => $bestScore,
            'totalApiResults'     => count($items),
        ];
    }

    /**
     * Constrói filtro OData para a API.
     */
    private function buildApiFilter(array $spec): string
    {
        $meterId = addslashes($spec['meterId']);
        $filter  = "meterId eq '{$meterId}'";

        // Adiciona filtros adicionais se disponíveis
        if (!empty($spec['productId'])) {
            // ProductId do CSV pode ter formato diferente do skuId da API
            // Não adiciona diretamente, usa apenas meterId
        }

        return $filter;
    }

    /**
     * Constrói chave única para spec.
     */
    private function buildSpecKey(array $row): string
    {
        return strtolower(
            ($row['meterId'] ?? '') . '|' .
            ($row['productId'] ?? '') . '|' .
            ($row['resourceLocation'] ?? '') . '|' .
            ($row['unitOfMeasure'] ?? '')
        );
    }

    /**
     * Normaliza região para comparação (alias para normalizeRegionToArmName).
     * @deprecated Use normalizeRegionToArmName
     */
    private function normalizeRegion(string $region): string
    {
        return $this->normalizeRegionToArmName($region);
    }

    /**
     * Normaliza Unit of Measure para comparação.
     * Remove espaços ao redor de "/" e normaliza para lowercase.
     * Exemplo: "1 / Hour" → "1/hour", "1 GB / Month" → "1 gb/month"
     */
    private function normalizeUom(string $uom): string
    {
        $uom = strtolower(trim($uom));
        // Remove espaços antes e depois de /
        $uom = preg_replace('/\s*\/\s*/', '/', $uom);
        // Normaliza múltiplos espaços para um único
        $uom = preg_replace('/\s+/', ' ', $uom);
        // Normalizações adicionais
        $uom = str_replace(['hrs', 'hours'], 'hour', $uom);
        $uom = str_replace('gb-month', 'gb/month', $uom);
        return $uom;
    }

    /**
     * Normaliza string para comparação.
     */
    private function normalizeString(string $str): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $str)));
    }

    /**
     * Converte string para número.
     */
    private function parseNumeric(string $value): float
    {
        $value = str_replace([',', ' '], ['.', ''], trim($value));
        return is_numeric($value) ? (float)$value : 0.0;
    }

    /**
     * Gera relatório de discrepâncias em formato exportável.
     */
    public function generateReport(array $comparisonResult): array
    {
        $report = [
            'generatedAt' => date('Y-m-d H:i:s'),
            'summary'     => $comparisonResult['summary'],
            'discrepancies' => [],
            'notFound'    => [],
            'matches'     => [],
        ];

        foreach ($comparisonResult['results'] as $item) {
            $entry = [
                'row'          => $item['rowIndex'] + 1,
                'meterId'      => $item['csvData']['meterId'],
                'meterName'    => $item['csvData']['meterName'],
                'productName'  => $item['csvData']['productName'],
                'status'       => $item['status'],
                'matchScore'   => $item['matchScore'] . '/' . $item['maxScore'],
            ];

            if ($item['status'] === 'NOT_FOUND') {
                $report['notFound'][] = $entry;
            } elseif ($item['status'] === 'MATCH') {
                $report['matches'][] = $entry;
            } else {
                $entry['discrepancies'] = $item['discrepancies'];
                $report['discrepancies'][] = $entry;
            }
        }

        return $report;
    }
}
