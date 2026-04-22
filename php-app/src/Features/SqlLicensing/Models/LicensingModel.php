<?php
declare(strict_types=1);

namespace App\Features\SqlLicensing\Models;

/**
 * Modelo de Licenciamento
 * Representa um modelo de licenciamento SQL Server/Windows Server
 */
class LicensingModel {
    private string $id;
    private string $name;
    private string $billingType;
    private array $pricing;
    private array $terms;
    private array $features;
    
    public function __construct(array $config) {
        $this->id = $config['id'];
        $this->name = $config['name'];
        $this->billingType = $config['billing_type'];
        $this->pricing = $config['pricing'];
        $this->terms = $config['terms'] ?? [];
        $this->features = $config['features'] ?? [];
    }
    
    public function getId(): string {
        return $this->id;
    }
    
    public function getName(): string {
        return $this->name;
    }
    
    public function getBillingType(): string {
        return $this->billingType;
    }
    
    public function getPricing(): array {
        return $this->pricing;
    }
    
    public function getTerms(): array {
        return $this->terms;
    }
    
    public function getFeatures(): array {
        return $this->features;
    }
    
    /**
     * Calcula o custo total para um período específico
     */
    public function calculateCost(int $cores, int $months, float $exchangeRate = 1.0): array {
        $costs = [
            'total' => 0,
            'monthly_average' => 0,
            'yearly_average' => 0,
            'breakdown' => [],
            'currency' => 'USD'
        ];
        
        switch ($this->billingType) {
            case 'monthly':
                $costs = $this->calculateMonthlyCost($cores, $months);
                break;
                
            case 'perpetual':
                $costs = $this->calculatePerpetualCost($cores, $months);
                break;
                
            case 'contract':
                $costs = $this->calculateContractCost($cores, $months);
                break;
        }
        
        // Aplicar taxa de câmbio
        if ($exchangeRate > 0 && $exchangeRate != 1.0) {
            $costs['total'] *= $exchangeRate;
            $costs['monthly_average'] *= $exchangeRate;
            $costs['yearly_average'] *= $exchangeRate;
            $costs['currency'] = 'BRL';
        }
        
        return $costs;
    }
    
    private function calculateMonthlyCost(int $cores, int $months): array {
        $monthlyPerCore = $this->pricing['monthly_per_core'] ?? 0;
        $setupFee = $this->pricing['setup_fee'] ?? 0;
        $reportingFee = $this->pricing['reporting_fee_monthly'] ?? 0;
        
        $monthlyCost = ($monthlyPerCore * $cores) + $reportingFee;
        $total = ($monthlyCost * $months) + $setupFee;
        
        // Aplicar descontos por volume se existir
        if (isset($this->pricing['volume_discounts'])) {
            $discount = $this->getVolumeDiscount($cores);
            $total = $total * (1 - $discount);
            $monthlyCost = $monthlyCost * (1 - $discount);
        }
        
        return [
            'total' => round($total, 2),
            'monthly_average' => round($monthlyCost, 2),
            'yearly_average' => round($monthlyCost * 12, 2),
            'breakdown' => [
                'monthly_per_core' => $monthlyPerCore,
                'setup_fee' => $setupFee,
                'reporting_fee' => $reportingFee,
                'total_cores' => $cores,
                'months' => $months
            ]
        ];
    }
    
    private function calculatePerpetualCost(int $cores, int $months): array {
        $licenseCost = ($this->pricing['license_per_core'] ?? 0) * $cores;
        $years = ceil($months / 12);
        
        $saPercentage = $this->pricing['software_assurance_annual_percentage'] ?? 0.25;
        $saAnnual = $licenseCost * $saPercentage;
        
        // Considera SA nos anos subsequentes
        $totalSA = $saAnnual * max(0, $years - 1);
        $total = $licenseCost + $totalSA;
        
        return [
            'total' => round($total, 2),
            'monthly_average' => round($total / $months, 2),
            'yearly_average' => round($total / $years, 2),
            'breakdown' => [
                'license_cost' => $licenseCost,
                'sa_annual' => $saAnnual,
                'total_sa' => $totalSA,
                'years' => $years,
                'sa_included' => true
            ]
        ];
    }
    
    private function calculateContractCost(int $cores, int $months): array {
        $years = ceil($months / 12);
        
        if (isset($this->pricing['annual_per_core'])) {
            // Open Value Subscription
            $annualCost = $this->pricing['annual_per_core'] * $cores;
            $total = $annualCost * $years;
            
            return [
                'total' => round($total, 2),
                'monthly_average' => round($total / $months, 2),
                'yearly_average' => round($annualCost, 2),
                'breakdown' => [
                    'annual_per_core' => $this->pricing['annual_per_core'],
                    'total_cores' => $cores,
                    'years' => $years
                ]
            ];
        } else {
            // Open Value
            $totalLicense = ($this->pricing['license_per_core'] ?? 0) * $cores;
            $annualPayments = $this->pricing['annual_payments'] ?? 3;
            $annualPayment = $totalLicense / $annualPayments;
            
            $totalPaid = min($annualPayment * $years, $totalLicense);
            
            return [
                'total' => round($totalPaid, 2),
                'monthly_average' => round($totalPaid / $months, 2),
                'yearly_average' => round($annualPayment, 2),
                'breakdown' => [
                    'total_license' => $totalLicense,
                    'annual_payment' => $annualPayment,
                    'years_paid' => min($years, $annualPayments),
                    'annual_payments' => $annualPayments
                ]
            ];
        }
    }
    
    private function getVolumeDiscount(int $cores): float {
        if (!isset($this->pricing['volume_discounts'])) {
            return 0;
        }
        
        $discounts = $this->pricing['volume_discounts'];
        
        foreach ($discounts as $range => $discount) {
            if (strpos($range, '+') !== false) {
                $min = (int)str_replace('+', '', $range);
                if ($cores >= $min) {
                    return $discount;
                }
            } else {
                $parts = explode('-', $range);
                if (count($parts) === 2) {
                    $min = (int)$parts[0];
                    $max = (int)$parts[1];
                    if ($cores >= $min && $cores <= $max) {
                        return $discount;
                    }
                }
            }
        }
        
        return 0;
    }
}
