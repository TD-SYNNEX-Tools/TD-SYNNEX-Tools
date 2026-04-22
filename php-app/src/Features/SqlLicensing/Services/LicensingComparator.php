<?php
declare(strict_types=1);

namespace App\Features\SqlLicensing\Services;

use App\Features\SqlLicensing\Models\LicensingModel;

/**
 * Serviço de Comparação de Licenciamento
 * Compara múltiplos modelos de licenciamento
 */
class LicensingComparator {
    private array $models = [];
    private array $comparisonPeriods = [12, 36, 60]; // meses
    
    public function __construct() {
        $this->loadModels();
    }
    
    /**
     * Carrega todos os modelos de licenciamento disponíveis
     */
    private function loadModels(): void {
        $config = require __DIR__ . '/../config/licensing-models.php';
        
        foreach ($config as $modelConfig) {
            $this->models[$modelConfig['id']] = new LicensingModel($modelConfig);
        }
    }
    
    /**
     * Obtém todos os modelos disponíveis
     */
    public function getAvailableModels(): array {
        return $this->models;
    }
    
    /**
     * Obtém um modelo específico
     */
    public function getModel(string $modelId): ?LicensingModel {
        return $this->models[$modelId] ?? null;
    }
    
    /**
     * Compara modelos selecionados
     * 
     * @param array $modelIds IDs dos modelos para comparar
     * @param int $cores Número de cores
     * @param float $exchangeRate Taxa de câmbio USD -> BRL
     * @return array Resultados comparativos
     */
    public function compare(array $modelIds, int $cores, float $exchangeRate = 5.39): array {
        if (count($modelIds) < 2) {
            throw new Exception("É necessário selecionar pelo menos 2 modelos para comparação");
        }
        
        $results = [];
        
        foreach ($modelIds as $modelId) {
            if (!isset($this->models[$modelId])) {
                continue;
            }
            
            $model = $this->models[$modelId];
            $config = require __DIR__ . '/../config/licensing-models.php';
            $modelConfig = $config[$modelId];
            
            $results[$modelId] = [
                'id' => $modelId,
                'name' => $model->getName(),
                'billing_type' => $model->getBillingType(),
                'costs' => $this->calculateNormalizedCosts($model, $cores, $exchangeRate),
                'best_for' => $modelConfig['best_for'] ?? 'Não especificado',
                'features' => $model->getFeatures(),
                'terms' => $model->getTerms()
            ];
        }
        
        // Adicionar rankings
        $results = $this->rankResults($results);
        
        return $results;
    }
    
    /**
     * Calcula custos normalizados por período
     */
    private function calculateNormalizedCosts(LicensingModel $model, int $cores, float $exchangeRate): array {
        $costs = [];
        
        foreach ($this->comparisonPeriods as $months) {
            $calculation = $model->calculateCost($cores, $months, $exchangeRate);
            
            $costs[$months] = [
                'total' => $calculation['total'],
                'monthly_average' => $calculation['monthly_average'],
                'yearly_average' => $calculation['yearly_average'],
                'breakdown' => $calculation['breakdown'] ?? [],
                'currency' => $calculation['currency'] ?? 'BRL'
            ];
        }
        
        return $costs;
    }
    
    /**
     * Ranqueia resultados por melhor custo-benefício em cada período
     */
    private function rankResults(array $results): array {
        foreach ($this->comparisonPeriods as $period) {
            $costs = [];
            
            foreach ($results as $id => $result) {
                $costs[$id] = $result['costs'][$period]['total'];
            }
            
            asort($costs);
            
            $rank = 1;
            foreach ($costs as $id => $cost) {
                $results[$id]['ranking'][$period] = $rank++;
            }
        }
        
        return $results;
    }
    
    /**
     * Obtém o melhor modelo para um período específico
     */
    public function getBestModel(array $results, int $months = 36): array {
        $bestModel = null;
        $lowestCost = PHP_FLOAT_MAX;
        
        foreach ($results as $result) {
            $cost = $result['costs'][$months]['total'];
            if ($cost < $lowestCost) {
                $lowestCost = $cost;
                $bestModel = $result;
            }
        }
        
        return $bestModel ?? [];
    }
    
    /**
     * Gera relatório de comparação formatado
     */
    public function generateReport(array $results): array {
        $report = [
            'summary' => [],
            'detailed' => $results,
            'recommendations' => []
        ];
        
        // Resumo por período
        foreach ($this->comparisonPeriods as $months) {
            $best = $this->getBestModel($results, $months);
            $report['summary'][$months] = [
                'period_months' => $months,
                'period_label' => $this->getPeriodLabel($months),
                'best_model' => $best['name'] ?? 'N/A',
                'best_cost' => $best['costs'][$months]['total'] ?? 0,
                'currency' => $best['costs'][$months]['currency'] ?? 'BRL'
            ];
        }
        
        // Recomendações
        $report['recommendations'] = $this->generateRecommendations($results);
        
        return $report;
    }
    
    /**
     * Gera recomendações baseadas nos resultados
     */
    private function generateRecommendations(array $results): array {
        $recommendations = [];
        
        // Melhor para curto prazo (12 meses)
        $shortTerm = $this->getBestModel($results, 12);
        if ($shortTerm) {
            $recommendations['short_term'] = [
                'period' => '12 meses',
                'model' => $shortTerm['name'],
                'reason' => 'Menor custo para período de 1 ano'
            ];
        }
        
        // Melhor para médio prazo (36 meses)
        $mediumTerm = $this->getBestModel($results, 36);
        if ($mediumTerm) {
            $recommendations['medium_term'] = [
                'period' => '36 meses (3 anos)',
                'model' => $mediumTerm['name'],
                'reason' => 'Melhor custo-benefício para 3 anos'
            ];
        }
        
        // Melhor para longo prazo (60 meses)
        $longTerm = $this->getBestModel($results, 60);
        if ($longTerm) {
            $recommendations['long_term'] = [
                'period' => '60 meses (5 anos)',
                'model' => $longTerm['name'],
                'reason' => 'Mais econômico para investimento de longo prazo'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Formata label do período
     */
    private function getPeriodLabel(int $months): string {
        $years = $months / 12;
        if ($years == 1) {
            return "1 ano";
        } elseif ($months < 12) {
            return "$months meses";
        } else {
            return "$years anos";
        }
    }
    
    /**
     * Calcula economia comparativa
     */
    public function calculateSavings(array $results, string $baseModelId, int $months = 36): array {
        $savings = [];
        
        if (!isset($results[$baseModelId])) {
            return $savings;
        }
        
        $baseCost = $results[$baseModelId]['costs'][$months]['total'];
        
        foreach ($results as $modelId => $result) {
            if ($modelId === $baseModelId) {
                continue;
            }
            
            $modelCost = $result['costs'][$months]['total'];
            $difference = $baseCost - $modelCost;
            $percentage = ($baseCost > 0) ? ($difference / $baseCost) * 100 : 0;
            
            $savings[$modelId] = [
                'model' => $result['name'],
                'difference' => $difference,
                'percentage' => round($percentage, 2),
                'cheaper' => $difference > 0,
                'currency' => $result['costs'][$months]['currency']
            ];
        }
        
        return $savings;
    }
}
