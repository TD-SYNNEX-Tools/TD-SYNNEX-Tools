<?php

declare(strict_types=1);

namespace App\Features\AzureMigration\Services;

/**
 * Classe responsável por analisar os recursos Azure e determinar a viabilidade de migração
 */
class AzureResourceAnalyzer
{
    private array $resourceDatabase;
    private string $databasePath;
    private array $analysisCache = [];

    // Constantes de status
    public const STATUS_MOVABLE = 'movable';
    public const STATUS_MOVABLE_WITH_RESTRICTIONS = 'movable-with-restrictions';
    public const STATUS_NOT_MOVABLE = 'not-movable';
    public const STATUS_UNKNOWN = 'unknown';

    // Labels para exibição
    public const STATUS_LABELS = [
        self::STATUS_MOVABLE => 'Migrável',
        self::STATUS_MOVABLE_WITH_RESTRICTIONS => 'Migrável com Restrições',
        self::STATUS_NOT_MOVABLE => 'Não Migrável',
        self::STATUS_UNKNOWN => 'Desconhecido'
    ];

    public const STATUS_COLORS = [
        self::STATUS_MOVABLE => '#82C341',
        self::STATUS_MOVABLE_WITH_RESTRICTIONS => '#FFD100',
        self::STATUS_NOT_MOVABLE => '#D9272E',
        self::STATUS_UNKNOWN => '#6c757d'
    ];

    /**
     * Construtor
     * 
     * @param string|null $databasePath Caminho para o arquivo JSON de recursos
     */
    public function __construct(?string $databasePath = null)
    {
        $this->databasePath = $databasePath ?? __DIR__ . '/../../../data/azure-move-support.json';
        $this->loadDatabase();
    }

    /**
     * Carrega a base de dados de recursos
     */
    private function loadDatabase(): void
    {
        if (!file_exists($this->databasePath)) {
            throw new \RuntimeException("Base de dados de recursos não encontrada: {$this->databasePath}");
        }

        $content = file_get_contents($this->databasePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Erro ao decodificar a base de dados JSON: " . json_last_error_msg());
        }

        $this->resourceDatabase = $data['resources'] ?? [];
    }

    /**
     * Analisa uma lista de recursos
     * 
     * @param array $resources Lista de recursos do arquivo importado
     * @return array Resultado da análise
     */
    public function analyzeResources(array $resources): array
    {
        $results = [];
        $aggregatedResources = [];
        $summary = [
            'total' => 0,
            'movable' => 0,
            'movableWithRestrictions' => 0,
            'notMovable' => 0,
            'unknown' => 0,
            'byProvider' => [],
            'analysisDate' => date('Y-m-d H:i:s'),
            'totalCost' => 0.0,
            'currency' => 'USD'
        ];

        // First pass: aggregate costs by unique resource
        foreach ($resources as $resource) {
            $resourceType = $resource['ResourceType'] ?? '';
            
            if (empty($resourceType)) {
                continue;
            }

            $resourceName = $resource['ResourceName'] ?? 'N/A';
            $resourceGroup = $resource['ResourceGroup'] ?? 'N/A';
            
            // Create unique key for the resource
            $uniqueKey = strtolower($resourceType . '|' . $resourceName . '|' . $resourceGroup);
            
            // Parse cost value
            $cost = $this->parseCostValue($resource['PreTaxCost'] ?? '0');
            $currency = $resource['Currency'] ?? 'USD';
            
            if (!isset($aggregatedResources[$uniqueKey])) {
                $aggregatedResources[$uniqueKey] = [
                    'resource' => $resource,
                    'cost' => 0.0,
                    'currency' => $currency
                ];
            }
            
            $aggregatedResources[$uniqueKey]['cost'] += $cost;
            
            // Keep currency from first entry
            if (empty($summary['currency']) && !empty($currency)) {
                $summary['currency'] = $currency;
            }
        }

        // Second pass: analyze aggregated resources
        foreach ($aggregatedResources as $aggregated) {
            $resource = $aggregated['resource'];
            $resourceType = $resource['ResourceType'] ?? '';

            $analysis = $this->analyzeResourceType($resourceType);
            
            $result = [
                'resourceName' => $resource['ResourceName'] ?? 'N/A',
                'resourceType' => $resourceType,
                'resourceGroup' => $resource['ResourceGroup'] ?? 'N/A',
                'location' => $resource['Location'] ?? 'N/A',
                'subscription' => $resource['Subscription'] ?? $resource['SubscriptionId'] ?? 'N/A',
                'status' => $analysis['status'],
                'statusLabel' => self::STATUS_LABELS[$analysis['status']],
                'statusColor' => self::STATUS_COLORS[$analysis['status']],
                'resourceGroupMove' => $analysis['resourceGroup'],
                'subscriptionMove' => $analysis['subscription'],
                'regionMove' => $analysis['region'],
                'notes' => $analysis['notes'],
                'provider' => $this->extractProvider($resourceType),
                'cost' => $aggregated['cost'],
                'currency' => $aggregated['currency']
            ];

            $results[] = $result;

            // Atualiza o resumo
            $summary['total']++;
            $summary['totalCost'] += $aggregated['cost'];
            
            switch ($analysis['status']) {
                case self::STATUS_MOVABLE:
                    $summary['movable']++;
                    break;
                case self::STATUS_MOVABLE_WITH_RESTRICTIONS:
                    $summary['movableWithRestrictions']++;
                    break;
                case self::STATUS_NOT_MOVABLE:
                    $summary['notMovable']++;
                    break;
                default:
                    $summary['unknown']++;
            }

            // Conta por provider
            $provider = $result['provider'];
            if (!isset($summary['byProvider'][$provider])) {
                $summary['byProvider'][$provider] = [
                    'total' => 0,
                    'movable' => 0,
                    'notMovable' => 0,
                    'cost' => 0.0
                ];
            }
            $summary['byProvider'][$provider]['total']++;
            $summary['byProvider'][$provider]['cost'] += $aggregated['cost'];
            if ($analysis['status'] === self::STATUS_MOVABLE || $analysis['status'] === self::STATUS_MOVABLE_WITH_RESTRICTIONS) {
                $summary['byProvider'][$provider]['movable']++;
            } else {
                $summary['byProvider'][$provider]['notMovable']++;
            }
        }

        // Calcula percentuais
        if ($summary['total'] > 0) {
            $summary['movablePercent'] = round(($summary['movable'] / $summary['total']) * 100, 1);
            $summary['movableWithRestrictionsPercent'] = round(($summary['movableWithRestrictions'] / $summary['total']) * 100, 1);
            $summary['notMovablePercent'] = round(($summary['notMovable'] / $summary['total']) * 100, 1);
            $summary['unknownPercent'] = round(($summary['unknown'] / $summary['total']) * 100, 1);
            $summary['totalMovablePercent'] = round((($summary['movable'] + $summary['movableWithRestrictions']) / $summary['total']) * 100, 1);
        } else {
            $summary['movablePercent'] = 0;
            $summary['movableWithRestrictionsPercent'] = 0;
            $summary['notMovablePercent'] = 0;
            $summary['unknownPercent'] = 0;
            $summary['totalMovablePercent'] = 0;
        }

        return [
            'results' => $results,
            'summary' => $summary
        ];
    }

    /**
     * Analisa um tipo de recurso específico
     * 
     * @param string $resourceType Tipo do recurso (ex: Microsoft.Compute/virtualMachines)
     * @return array Informações sobre a viabilidade de migração
     */
    public function analyzeResourceType(string $resourceType): array
    {
        // Verifica cache
        if (isset($this->analysisCache[$resourceType])) {
            return $this->analysisCache[$resourceType];
        }

        // Normaliza o tipo de recurso
        $normalizedType = $this->normalizeResourceType($resourceType);
        
        // Busca na base de dados
        $resourceInfo = $this->findResourceInfo($normalizedType);

        if ($resourceInfo === null) {
            $result = [
                'status' => self::STATUS_UNKNOWN,
                'resourceGroup' => null,
                'subscription' => null,
                'region' => null,
                'notes' => 'Tipo de recurso não encontrado na base de dados. Verifique a documentação oficial da Microsoft.'
            ];
        } else {
            $result = [
                'status' => $resourceInfo['status'],
                'resourceGroup' => $resourceInfo['resourceGroup'] ?? false,
                'subscription' => $resourceInfo['subscription'] ?? false,
                'region' => $resourceInfo['region'] ?? false,
                'notes' => $resourceInfo['notes'] ?? ''
            ];
        }

        // Armazena no cache
        $this->analysisCache[$resourceType] = $result;

        return $result;
    }

    /**
     * Normaliza o tipo de recurso para busca
     */
    private function normalizeResourceType(string $resourceType): string
    {
        // Remove espaços
        $normalized = trim($resourceType);
        
        // Normaliza separadores
        $normalized = str_replace('\\', '/', $normalized);
        
        return $normalized;
    }

    /**
     * Busca informações do recurso na base de dados
     */
    private function findResourceInfo(string $resourceType): ?array
    {
        // Busca exata (case-insensitive)
        foreach ($this->resourceDatabase as $key => $info) {
            if (strtolower($key) === strtolower($resourceType)) {
                return $info;
            }
        }

        // Busca parcial - tenta encontrar o recurso pai
        $parts = explode('/', $resourceType);
        if (count($parts) > 2) {
            // Tenta buscar com menos níveis
            $parentType = $parts[0] . '/' . $parts[1];
            foreach ($this->resourceDatabase as $key => $info) {
                if (strtolower($key) === strtolower($parentType)) {
                    return $info;
                }
            }
        }

        return null;
    }

    /**
     * Extrai o provider do tipo de recurso
     */
    private function extractProvider(string $resourceType): string
    {
        $parts = explode('/', $resourceType);
        return $parts[0] ?? 'Unknown';
    }

    /**
     * Parse cost value from various formats
     * Handles: 0.12, 3.686.400.000.000.000, 0,12 (Brazilian format)
     */
    private function parseCostValue(string|float|int $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        
        if (empty($value)) {
            return 0.0;
        }

        // Check if it's a large number with dots as thousand separators (e.g., 3.686.400.000.000.000)
        // This pattern has multiple dots and no comma, typical of pt-BR large numbers
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
            // It's a whole number with thousand separators
            $value = str_replace('.', '', $value);
            return (float) $value;
        }

        // Check for numbers with both dots and decimal (e.g., 3.686.400,50)
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $value)) {
            // Brazilian format with thousand separators and decimal
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
            return (float) $value;
        }

        // Simple Brazilian decimal format (e.g., 0,12)
        if (strpos($value, ',') !== false && strpos($value, '.') === false) {
            $value = str_replace(',', '.', $value);
            return (float) $value;
        }

        // Standard format (e.g., 0.12)
        return (float) $value;
    }

    /**
     * Retorna todos os recursos da base de dados
     */
    public function getAllKnownResources(): array
    {
        return $this->resourceDatabase;
    }

    /**
     * Retorna a data da última atualização da base de dados
     */
    public function getDatabaseMetadata(): array
    {
        $content = file_get_contents($this->databasePath);
        $data = json_decode($content, true);
        return $data['metadata'] ?? [];
    }

    /**
     * Busca recursos na base de dados
     * 
     * @param string $query Termo de busca
     * @return array Recursos encontrados
     */
    public function searchResources(string $query): array
    {
        $results = [];
        $query = strtolower($query);

        foreach ($this->resourceDatabase as $key => $info) {
            if (strpos(strtolower($key), $query) !== false) {
                $results[$key] = $info;
            }
        }

        return $results;
    }

    /**
     * Retorna estatísticas da base de dados
     */
    public function getDatabaseStats(): array
    {
        $stats = [
            'total' => count($this->resourceDatabase),
            'movable' => 0,
            'movableWithRestrictions' => 0,
            'notMovable' => 0,
            'providers' => []
        ];

        foreach ($this->resourceDatabase as $key => $info) {
            $provider = $this->extractProvider($key);
            
            if (!isset($stats['providers'][$provider])) {
                $stats['providers'][$provider] = 0;
            }
            $stats['providers'][$provider]++;

            switch ($info['status'] ?? '') {
                case self::STATUS_MOVABLE:
                    $stats['movable']++;
                    break;
                case self::STATUS_MOVABLE_WITH_RESTRICTIONS:
                    $stats['movableWithRestrictions']++;
                    break;
                case self::STATUS_NOT_MOVABLE:
                    $stats['notMovable']++;
                    break;
            }
        }

        return $stats;
    }

    /**
     * Gera recomendações baseadas na análise
     */
    public function generateRecommendations(array $analysisResults): array
    {
        $recommendations = [];
        $summary = $analysisResults['summary'];
        $results = $analysisResults['results'];

        // Recomendação geral baseada no percentual
        if ($summary['totalMovablePercent'] >= 80) {
            $recommendations[] = [
                'type' => 'success',
                'title' => 'Alta Viabilidade de Migração',
                'message' => "A maioria dos recursos ({$summary['totalMovablePercent']}%) pode ser migrada. A migração é viável com planejamento adequado."
            ];
        } elseif ($summary['totalMovablePercent'] >= 50) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Viabilidade Moderada',
                'message' => "Cerca de {$summary['totalMovablePercent']}% dos recursos podem ser migrados. Avalie alternativas para os recursos não migráveis."
            ];
        } else {
            $recommendations[] = [
                'type' => 'danger',
                'title' => 'Baixa Viabilidade de Migração',
                'message' => "Apenas {$summary['totalMovablePercent']}% dos recursos podem ser migrados. Considere redesenhar a arquitetura."
            ];
        }

        // Identifica recursos críticos não migráveis
        $criticalNonMovable = [];
        foreach ($results as $result) {
            if ($result['status'] === self::STATUS_NOT_MOVABLE) {
                $type = $result['resourceType'];
                if (!in_array($type, $criticalNonMovable)) {
                    $criticalNonMovable[] = $type;
                }
            }
        }

        if (!empty($criticalNonMovable)) {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'Recursos Não Migráveis Identificados',
                'message' => 'Os seguintes tipos de recursos precisarão ser recriados na nova assinatura: ' . 
                            implode(', ', array_slice($criticalNonMovable, 0, 5)) .
                            (count($criticalNonMovable) > 5 ? ' e mais ' . (count($criticalNonMovable) - 5) . ' tipos.' : '.')
            ];
        }

        // Recursos com restrições
        $withRestrictions = array_filter($results, fn($r) => $r['status'] === self::STATUS_MOVABLE_WITH_RESTRICTIONS);
        if (!empty($withRestrictions)) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Atenção às Restrições',
                'message' => count($withRestrictions) . ' recursos podem ser migrados mas possuem restrições. Revise as observações de cada recurso.'
            ];
        }

        return $recommendations;
    }
}
