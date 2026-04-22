<?php
declare(strict_types=1);

namespace App\Features\AzureMigration\Services;

use App\Shared\Services\ServerLogger;

/**
 * Consulta a API pública de preços da Microsoft (prices.azure.com).
 * Usa curl_multi para disparar todas as requisições em PARALELO,
 * reduzindo o tempo de N × latência para ~1 × latência.
 * Cache de resultados na sessão para evitar re-consultas.
 */
class MicrosoftPricingApi
{
    private const API_URL   = 'https://prices.azure.com/api/retail/prices';
    private const API_VER   = '2023-01-01-preview';
    private const CACHE_KEY = 'financialPriceCache';
    private const TIMEOUT   = 20;  // segundos por requisição (Azure App Service precisa de mais)
    private const BATCH     = 8;   // máximo de conexões simultâneas (evita rate limiting)
    private ServerLogger $logger;

    public function __construct()
    {
        if (!isset($_SESSION[self::CACHE_KEY])) {
            $_SESSION[self::CACHE_KEY] = [];
        }
        $this->logger = ServerLogger::getInstance();
    }

    /**
     * Consulta a API para uma lista de specs (meterId + productId + resourceLocation + unitOfMeasure).
     *
     * Estratégia:
     *   Pass 1 — query ampla: meterId + priceType=Consumption  → obtém TODOS os itens do meterId
     *            Itera por todos os resultados e pontua cada um contra os campos do CSV.
     *            Escolhe o item com maior score (melhor match exato).
     *   Pass 2 — se Pass 1 retornou zero itens: repete sem filtro priceType (captura Reserved, etc.)
     *
     * Pontuação por item:
     *   meterId bate         → +5 (confirmação do match)
     *   location bate        → +4
     *   unitOfMeasure bate   → +3
     *   meterName bate       → +2
     *   priceType=Consumption → +1
     *
     * @param  array[] $specs  Cada item: ['key'=>string, 'meterId'=>string, 'productId'=>string,
     *                                     'resourceLocation'=>string, 'unitOfMeasure'=>string]
     * @return array<string, array|null>
     */
    public function getPricesBySpecs(array $specs): array
    {
        $results = [];

        // Separa cache hits dos que precisam ir à API
        $toFetch = [];
        $cacheHits = 0;
        foreach ($specs as $spec) {
            $key = $spec['key'];
            if (array_key_exists($key, $_SESSION[self::CACHE_KEY])) {
                $results[$key] = $_SESSION[self::CACHE_KEY][$key];
                $cacheHits++;
            } else {
                $toFetch[] = $spec;
                $results[$key] = null;
            }
        }

        $this->logger->info('PricingAPI', "Consulta iniciada: " . count($specs) . " specs, {$cacheHits} cache hits, " . count($toFetch) . " para buscar na API");

        if (empty($toFetch)) {
            $this->logger->success('PricingAPI', 'Todos os precos encontrados no cache da sessao');
            return $results;
        }

        // Pass 1: meterId + priceType=Consumption — query ampla, todos os itens do meterId
        $this->logger->api('PricingAPI', 'Pass 1: meterId + priceType=Consumption (' . count($toFetch) . ' specs, batch=' . self::BATCH . ')');
        $pass1Start = microtime(true);
        $fetched     = $this->multiGetSpecs($toFetch, false, true);
        $pass1Found  = count(array_filter($fetched, fn($v) => !empty($v)));
        $this->logger->info('PricingAPI', 'Pass 1 concluido em ' . round((microtime(true) - $pass1Start) * 1000) . 'ms — ' . $pass1Found . '/' . count($toFetch) . ' encontrados');
        $matchLevels = [];
        foreach ($fetched as $key => $items) {
            if (!empty($items)) { $matchLevels[$key] = 1; }
        }

        // Pass 2: misses → sem filtro priceType (captura Reserved, DevTest, etc.)
        $retry2 = array_values(array_filter($toFetch, fn($s) => empty($fetched[$s['key']])));
        if (!empty($retry2)) {
            $this->logger->api('PricingAPI', 'Pass 2: sem filtro priceType (' . count($retry2) . ' specs)');
            $pass2Start = microtime(true);
            $pass2Found = 0;
            foreach ($this->multiGetSpecs($retry2, false, false) as $key => $items) {
                if (!empty($items)) { $fetched[$key] = $items; $matchLevels[$key] = 2; $pass2Found++; }
            }
            $this->logger->info('PricingAPI', 'Pass 2 concluido em ' . round((microtime(true) - $pass2Start) * 1000) . 'ms — ' . $pass2Found . '/' . count($retry2) . ' encontrados');
        }

        // Pass 3: misses restantes → retry sequencial com timeout estendido (30s)
        $retry3 = array_values(array_filter($toFetch, fn($s) => empty($fetched[$s['key']])));
        if (!empty($retry3)) {
            $this->logger->warn('PricingAPI', 'Pass 3: retry sequencial com timeout estendido (' . count($retry3) . ' specs)');
            error_log('[MicrosoftPricingApi] Pass 3: retrying ' . count($retry3) . ' specs sequentially (timeout=30s)');
            $pass3Found = 0;
            foreach ($retry3 as $spec) {
                $items = $this->singleGetSpecExtended($spec, 30);
                if (!empty($items)) {
                    $fetched[$spec['key']] = $items;
                    $matchLevels[$spec['key']] = 3;
                    $pass3Found++;
                }
            }
            $this->logger->info('PricingAPI', 'Pass 3 concluido — ' . $pass3Found . '/' . count($retry3) . ' encontrados');
        }

        // Processa resultados: pontua TODOS os itens e escolhe o melhor match
        foreach ($toFetch as $spec) {
            $key   = $spec['key'];
            $level = $matchLevels[$key] ?? null;
            $items = $fetched[$key] ?? [];

            if (empty($items)) {
                // NÃO armazena null no cache — permite retry em análises futuras
                $results[$key] = null;
                continue;
            }

            // Seleciona o item com maior pontuação de match contra os campos do CSV
            $item      = $this->scoreBestItem($items, $spec);
            $score     = $item['_score'];
            $maxScore  = $item['_maxScore'];
            unset($item['_score'], $item['_maxScore']);

            $data = [
                'meterId'       => $item['meterId']       ?? '',
                'unitPrice'     => (float)($item['retailPrice'] ?? $item['unitPrice'] ?? 0),
                'currencyCode'  => $item['currencyCode']  ?? 'USD',
                'unitOfMeasure' => $item['unitOfMeasure'] ?? '',
                'productName'   => $item['productName']   ?? '',
                'productId'     => $item['productId']     ?? '',
                'meterName'     => $item['meterName']     ?? '',
                'serviceName'   => $item['serviceName']   ?? '',
                'serviceFamily' => $item['serviceFamily'] ?? '',
                'priceType'     => $item['priceType']     ?? '',
                'location'      => $item['location']      ?? '',
                'matchLevel'    => $level,
                'matchScore'    => $score,
                'matchMaxScore' => $maxScore,
                'filterUsed'    => $this->buildSpecFilter($spec, false, $level === 1),
            ];

            $_SESSION[self::CACHE_KEY][$key] = $data;
            $results[$key] = $data;
        }

        return $results;
    }

    /**
     * Pontua cada item retornado pela API contra os campos do spec do CSV.
     * Retorna o item com maior score, com campos extras _score e _maxScore injetados.
     *
     * Critérios (quanto mais específico, mais peso):
     *   meterId bate (confirmação) → +5
     *   location bate              → +4
     *   unitOfMeasure bate         → +3
     *   meterName bate             → +2
     *   priceType=Consumption      → +1
     *
     * Nota: skuId removido pois não está disponível no CSV de Cost Management.
     * Nota: Usa 'location' da API (não armRegionName) para comparação.
     */
    private function scoreBestItem(array $items, array $spec): array
    {
        $csvMeterId = strtolower(trim($spec['meterId'] ?? ''));
        $csvRegion  = $this->normalizeRegion($spec['resourceLocation'] ?? '');
        $csvUom     = $this->normalizeUom($spec['unitOfMeasure'] ?? '');
        $csvMeter   = strtolower(trim($spec['meterName'] ?? ''));

        $best      = null;
        $bestScore = -1;
        $maxScore  = 0;

        // Calcula máximo possível para indicar completude do match
        $maxScore += 5; // meterId sempre presente
        if ($csvRegion !== '') $maxScore += 4;
        if ($csvUom    !== '') $maxScore += 3;
        if ($csvMeter  !== '') $maxScore += 2;
        $maxScore += 1; // priceType

        foreach ($items as $item) {
            $score = 0;

            // MeterID: Comparação entre CSV e API (confirmação)
            $apiMeterId = strtolower(trim($item['meterId'] ?? ''));
            if ($csvMeterId !== '' && $apiMeterId === $csvMeterId) {
                $score += 5;
            }

            // Location: CSV resourceLocation vs API location (não armRegionName)
            $apiLocation = $this->normalizeRegion($item['location'] ?? '');
            if ($csvRegion !== '' && $apiLocation === $csvRegion) {
                $score += 4;
            }

            // UnitOfMeasure: normalizado (remove espaços ao redor de /)
            if ($csvUom !== '' && $this->normalizeUom($item['unitOfMeasure'] ?? '') === $csvUom) {
                $score += 3;
            }

            // MeterName
            if ($csvMeter !== '' && strtolower($item['meterName'] ?? '') === $csvMeter) {
                $score += 2;
            }

            // Preferência por Consumption
            if (($item['priceType'] ?? '') === 'Consumption') {
                $score += 1;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = $item;
            }
        }

        $best['_score']    = $bestScore;
        $best['_maxScore'] = $maxScore;
        return $best;
    }

    /**
     * Retrocompatível: aceita array de meterIds simples (sem campos extras).
     *
     * @param  string[] $meterIds
     * @return array<string, array|null>
     */
    public function getPricesBatch(array $meterIds): array
    {
        $specs = array_map(fn($id) => [
            'key'              => strtolower($id) . '|||',
            'meterId'          => $id,
            'productId'        => '',
            'resourceLocation' => '',
            'unitOfMeasure'    => '',
        ], $meterIds);

        $raw = $this->getPricesBySpecs($specs);

        $out = [];
        foreach ($specs as $s) {
            $out[$s['meterId']] = $raw[$s['key']] ?? null;
        }
        return $out;
    }

    /**
     * Dispara requisições HTTP simultâneas para uma lista de specs.
     *
     * @param  array[]  $specs
     * @param  bool     $withExtras      Inclui productId, armRegionName, unitOfMeasure no filtro
     * @param  bool     $withConsumption Inclui priceType eq 'Consumption'
     * @return array<string, array>      Indexado pela key do spec
     */
    private function multiGetSpecs(array $specs, bool $withExtras, bool $withConsumption): array
    {
        if (!function_exists('curl_multi_init')) {
            $out = [];
            foreach ($specs as $spec) {
                $out[$spec['key']] = $this->singleGetSpec($spec, $withExtras, $withConsumption);
            }
            return $out;
        }

        $out     = array_fill_keys(array_column($specs, 'key'), []);
        $batches = array_chunk($specs, self::BATCH);
        $batchNum = 0;

        foreach ($batches as $batch) {
            $batchNum++;
            $this->logger->debug('PricingAPI', "Batch {$batchNum}/" . count($batches) . ': ' . count($batch) . ' requests paralelas via curl_multi');
            $mh      = curl_multi_init();
            $handles = [];

            foreach ($batch as $spec) {
                $filter = $this->buildSpecFilter($spec, $withExtras, $withConsumption);
                $url    = self::API_URL . '?api-version=' . self::API_VER
                        . '&$filter=' . rawurlencode($filter);

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => self::TIMEOUT,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_USERAGENT      => 'TdSynnex-ArcCalculator/1.0',
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_ENCODING       => 'gzip',
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$spec['key']] = $ch;
            }

            $running = null;
            do {
                curl_multi_exec($mh, $running);
                if ($running) { curl_multi_select($mh, 0.5); }
            } while ($running > 0);

            foreach ($handles as $key => $ch) {
                $body = curl_multi_getcontent($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $totalTime = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
                $err  = curl_error($ch);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                if ($code === 200 && $body) {
                    $decoded = json_decode($body, true);
                    if (is_array($decoded) && !empty($decoded['Items'])) {
                        $out[$key] = $decoded['Items'];
                        $this->logger->success('PricingAPI', "HTTP {$code} ({$totalTime}ms) meterId=" . substr(explode('|', $key)[0], 0, 12) . '... → ' . count($decoded['Items']) . ' items');
                    } else {
                        $this->logger->debug('PricingAPI', "HTTP {$code} ({$totalTime}ms) meterId=" . substr(explode('|', $key)[0], 0, 12) . '... → 0 items');
                    }
                } elseif ($code !== 200 || $err) {
                    $this->logger->error('PricingAPI', "HTTP {$code} ({$totalTime}ms) meterId=" . substr(explode('|', $key)[0], 0, 12) . "... ERRO: {$err}");
                    error_log("[MicrosoftPricingApi] FAIL key={$key} HTTP={$code} err={$err}");
                }
            }

            curl_multi_close($mh);
        }

        return $out;
    }

    /** Fallback sequencial caso curl_multi não esteja disponível. */
    private function singleGetSpec(array $spec, bool $withExtras, bool $withConsumption): array
    {
        if (!function_exists('curl_init')) {
            return [];
        }
        $filter = $this->buildSpecFilter($spec, $withExtras, $withConsumption);
        $url    = self::API_URL . '?api-version=' . self::API_VER
                . '&$filter=' . rawurlencode($filter);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'TdSynnex-ArcCalculator/1.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => 'gzip',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code !== 200 || !$body) {
            if ($err) { error_log("[MicrosoftPricingApi] singleGetSpec FAIL HTTP={$code} err={$err}"); }
            return [];
        }
        $decoded = json_decode($body, true);
        return (is_array($decoded) && isset($decoded['Items'])) ? $decoded['Items'] : [];
    }

    /**
     * Retry estendido: só meterId, sem filtros extras, timeout aumentado.
     * Tenta primeiro com Consumption, depois sem.
     */
    private function singleGetSpecExtended(array $spec, int $timeout): array
    {
        if (!function_exists('curl_init')) {
            return [];
        }

        // Tenta com Consumption primeiro
        foreach ([true, false] as $withConsumption) {
            $filter = "meterId eq '{$spec['meterId']}'";
            if ($withConsumption) {
                $filter .= " and priceType eq 'Consumption'";
            }
            $url = self::API_URL . '?api-version=' . self::API_VER
                 . '&$filter=' . rawurlencode($filter);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'TdSynnex-ArcCalculator/1.0',
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING       => 'gzip',
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($code === 200 && $body) {
                $decoded = json_decode($body, true);
                if (is_array($decoded) && !empty($decoded['Items'])) {
                    error_log("[MicrosoftPricingApi] Pass3 OK meterId={$spec['meterId']} items=" . count($decoded['Items']));
                    return $decoded['Items'];
                }
            } else {
                error_log("[MicrosoftPricingApi] Pass3 FAIL meterId={$spec['meterId']} HTTP={$code} err={$err}");
            }

            // Pequeno delay entre tentativas para evitar throttling
            usleep(200000); // 200ms
        }

        return [];
    }

    /**
     * Constrói o filtro OData para uma spec.
     * Pass 1 ($withExtras=true,  $withConsumption=true):  meterId + skuId + armRegionName + unitOfMeasure + priceType=Consumption
     * Pass 2 ($withExtras=false, $withConsumption=true):  meterId + priceType=Consumption
     * Pass 3 ($withExtras=false, $withConsumption=false): meterId
     *
     * O productId do CSV vem como concatenação (ex: "DZH318Z0BXWC0006").
     * A API espera skuId no formato "DZH318Z0BXWC/0006" (últimos 4 chars = sku part).
     */
    private function buildSpecFilter(array $spec, bool $withExtras, bool $withConsumption): string
    {
        $parts = ["meterId eq '{$spec['meterId']}'"];

        if ($withExtras) {
            // Converte productId do CSV para formato skuId da API (XXXXXXXXXX/YYYY)
            $skuId = $this->csvProductIdToSkuId($spec['productId'] ?? '');
            if ($skuId !== '') {
                $parts[] = "skuId eq '{$skuId}'";
            }
            $region = $this->normalizeRegion($spec['resourceLocation']);
            if ($region !== '') {
                $parts[] = "armRegionName eq '{$region}'";
            }
            if (!empty($spec['unitOfMeasure'])) {
                $parts[] = "unitOfMeasure eq '{$spec['unitOfMeasure']}'";
            }
        }

        if ($withConsumption) {
            $parts[] = "priceType eq 'Consumption'";
        }

        return implode(' and ', $parts);
    }

    /**
     * Converte o productId do formato CSV (concatenado: "DZH318Z0BXWC0006")
     * para o formato skuId da API ("DZH318Z0BXWC/0006" — últimos 4 chars = SKU part).
     * Se já tiver "/" ou for curto demais, retorna como está.
     */
    private function csvProductIdToSkuId(string $csvId): string
    {
        if ($csvId === '') {
            return '';
        }
        if (str_contains($csvId, '/')) {
            return $csvId; // já está no formato da API
        }
        if (strlen($csvId) >= 5) {
            return substr($csvId, 0, -4) . '/' . substr($csvId, -4);
        }
        return $csvId;
    }

    /**
     * Normaliza nome de região Azure para o formato armRegionName.
     * "East US" → "eastus", "Brazil South" → "brazilsouth"
     */
    private function normalizeRegion(string $loc): string
    {
        return strtolower(str_replace([' ', '-', '_'], '', $loc));
    }

    /**
     * Normaliza UnitOfMeasure para comparação.
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
        return $uom;
    }

    public function getCacheCount(): int
    {
        return count($_SESSION[self::CACHE_KEY] ?? []);
    }

    public function clearCache(): void
    {
        $_SESSION[self::CACHE_KEY] = [];
    }
}
