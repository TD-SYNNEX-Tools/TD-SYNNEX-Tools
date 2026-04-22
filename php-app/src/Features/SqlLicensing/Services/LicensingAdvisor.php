<?php
declare(strict_types=1);

namespace App\Features\SqlLicensing\Services;

/**
 * LicensingAdvisor.php
 * 
 * Classe responsável pela comparação dos 6 modelos de licenciamento SQL Server 2022.
 * Modelos: Azure ARC PAYG, SPLA, CSP 1 Ano, CSP 3 Anos, Software Perpétuo + SA, OVS
 * 
 * TD SYNNEX - SQL Licensing Advisor
 */

class LicensingAdvisor
{
    /** @var array Preços configuráveis */
    private array $prices;

    /** @var array Preços padrão em BRL — Tabela real TD SYNNEX SQL Server 2025 */
    private const DEFAULTS = [
        // Azure ARC (por vCore/hora) — USD × 5,39 = BRL
        // $0,10  × 5,39 = R$ 0,539   |  $0,375 × 5,39 = R$ 2,021
        'arcStandard'         => 0.539,
        'arcEnterprise'       => 2.021,

        // SPLA (por pack de 2 cores/mês) — USD × 5,39 = BRL
        // $210,00  × 5,39 = R$ 1.131,90  |  $855,47 × 5,39 = R$ 4.611,08
        'splaStandard'        => 1131.90,
        'splaEnterprise'      => 4611.08,

        // CSP 1 Ano Upfront (por pack de 2 cores/ano) — em BRL
        'csp1yStandard'       => 15226.33,
        'csp1yEnterprise'     => 58377.01,

        // CSP 3 Anos Upfront (por pack de 2 cores, total 3 anos) — em BRL
        'csp3yStandard'       => 38245.50,
        'csp3yEnterprise'     => 146564.40,

        // Software Perpétuo (por pack de 2 cores, licença única) — em BRL
        'perpetualStandard'   => 32240.30,
        'perpetualEnterprise' => 123602.72,
        'saPct'               => 25, // % do valor da licença/ano para SA

        // OVS — Open Value Subscription (por pack de 2 cores/ano) — em BRL
        'ovsStandard'         => 12651.28,
        'ovsEnterprise'       => 48511.95,

        // Câmbio
        'exchangeRate'        => 5.39,
    ];

    /**
     * @param array $prices Preços customizados (serão mesclados com os defaults)
     */
    public function __construct(array $prices = [])
    {
        $this->prices = array_merge(self::DEFAULTS, $prices);
    }

    /**
     * Calcula o número de packs de 2 cores.
     * REGRA CRÍTICA: mínimo de 2 packs (4 cores).
     * Aplica-se a todos os modelos EXCETO Azure ARC.
     */
    private function calcPacks(int $vCores): int
    {
        return max(2, (int) ceil($vCores / 2));
    }

    /**
     * Retorna o preço correto com base na edição.
     */
    private function editionPrice(string $key, string $edition): float
    {
        $suffix = ($edition === 'Enterprise') ? 'Enterprise' : 'Standard';
        return (float) ($this->prices[$key . $suffix] ?? 0);
    }

    private function formatUnitPrice(float $value, string $unit, string $currency): string
    {
        $symbol = $currency === 'USD' ? 'US$' : 'R$';
        $v = $this->applyRate($value, $currency);
        return sprintf("%s %s %s", $symbol, number_format($v, 2, ',', '.'), $unit);
    }

    /**
     * Aplica câmbio se moeda for USD (preços internos estão em BRL).
     */
    private function applyRate(float $value, string $currency): float
    {
        return $currency === 'USD' ? $value / $this->prices['exchangeRate'] : $value;
    }

    /**
     * Compara todos os 6 modelos de licenciamento.
     *
     * @param array $params {
     *   vCores: int,
     *   edition: string ('Standard'|'Enterprise'),
     *   hoursPerMonth: int,
     *   currency: string ('USD'|'BRL'),
     *   amortizationYears: int,
     *   hasSA: bool,
     *   clientName: string,
     *   vendorName: string,
     * }
     * @return array Resultado comparativo completo
     */
    public function compareAll(array $params): array
    {
        // Sanitizar / defaults
        $vCores            = max(1, (int) ($params['vCores'] ?? 4));
        $edition           = in_array($params['edition'] ?? '', ['Standard', 'Enterprise']) ? $params['edition'] : 'Standard';
        $hoursPerMonth     = max(1, (int) ($params['hoursPerMonth'] ?? 730));
        $currency          = in_array($params['currency'] ?? '', ['USD', 'BRL']) ? $params['currency'] : 'USD';
        $amortizationYears = max(1, (int) ($params['amortizationYears'] ?? 5));
        $hasSA             = (bool) ($params['hasSA'] ?? false);
        $clientName        = $params['clientName'] ?? '';
        $vendorName        = $params['vendorName'] ?? '';

        $packs = $this->calcPacks($vCores);

        // ===== 1. AZURE ARC (sem packs) =====
        $arcPricePerHour = $this->editionPrice('arc', $edition);
        $arcMonthlyUSD   = $vCores * $arcPricePerHour * $hoursPerMonth;
        $arcMonthly      = $this->applyRate($arcMonthlyUSD, $currency);
        $arcAnnual       = $arcMonthly * 12;
        $arcTotal3y      = $arcMonthly * 36;

        $arc = [
            'monthly'   => round($arcMonthly, 2),
            'annual'    => round($arcAnnual, 2),
            'total3y'   => round($arcTotal3y, 2),
            'unitPrice' => round($this->applyRate($arcPricePerHour, $currency), 4),
            'label'     => 'Azure ARC PAYG',
        ];

        // ===== 2. SPLA =====
        $splaPricePerPack = $this->editionPrice('spla', $edition);
        $splaMonthlyUSD   = $packs * $splaPricePerPack;
        $splaMonthly      = $this->applyRate($splaMonthlyUSD, $currency);
        $splaAnnual       = $splaMonthly * 12;
        $splaTotal3y      = $splaMonthly * 36;

        $spla = [
            'monthly'   => round($splaMonthly, 2),
            'annual'    => round($splaAnnual, 2),
            'total3y'   => round($splaTotal3y, 2),
            'packs'     => $packs,
            'unitPrice' => round($this->applyRate($splaPricePerPack, $currency), 2),
            'label'     => 'SPLA',
        ];

        // ===== 3. CSP 1 Ano Upfront =====
        $csp1yPricePerPack = $this->editionPrice('csp1y', $edition); // preço por pack/ano
        $csp1yUpfront1y    = $packs * $csp1yPricePerPack;
        $csp1yUpfront1y    = $this->applyRate($csp1yUpfront1y, $currency);
        $csp1yMonthly      = $csp1yUpfront1y / 12;
        $csp1yAnnual       = $csp1yUpfront1y;
        $csp1yTotal3y      = $csp1yUpfront1y * 3;

        $csp1y = [
            'monthly'    => round($csp1yMonthly, 2),
            'annual'     => round($csp1yAnnual, 2),
            'total3y'    => round($csp1yTotal3y, 2),
            'packs'      => $packs,
            'unitPrice'  => round($this->applyRate($csp1yPricePerPack, $currency), 2),
            'upfront1y'  => round($csp1yUpfront1y, 2),
            'label'      => 'CSP 1 Ano (Upfront)',
        ];

        // ===== 4. CSP 3 Anos Upfront =====
        $csp3yPricePerPack = $this->editionPrice('csp3y', $edition); // preço por pack, total 3 anos
        $csp3yUpfront3y    = $packs * $csp3yPricePerPack;
        $csp3yUpfront3y    = $this->applyRate($csp3yUpfront3y, $currency);
        $csp3yMonthly      = $csp3yUpfront3y / 36;
        $csp3yAnnual       = $csp3yUpfront3y / 3;
        $csp3yTotal3y      = $csp3yUpfront3y;

        $csp3y = [
            'monthly'    => round($csp3yMonthly, 2),
            'annual'     => round($csp3yAnnual, 2),
            'total3y'    => round($csp3yTotal3y, 2),
            'packs'      => $packs,
            'unitPrice'  => round($this->applyRate($csp3yPricePerPack, $currency), 2),
            'upfront3y'  => round($csp3yUpfront3y, 2),
            'label'      => 'CSP 3 Anos (Upfront)',
        ];

        // ===== 5. Software Perpétuo + SA =====
        $perpPricePerPack = $this->editionPrice('perpetual', $edition);
        $licenseCostUSD   = $packs * $perpPricePerPack;
        $licenseCost      = $this->applyRate($licenseCostUSD, $currency);

        $saPct      = (float) ($this->prices['saPct'] ?? 25);
        $saAnnualUSD = $licenseCostUSD * ($saPct / 100);
        $saAnnual    = $this->applyRate($saAnnualUSD, $currency);

        // Se o cliente já possui SA, o custo anual de SA é zero
        $saAnnualEffective = $hasSA ? 0 : $saAnnual;

        // Amortização da licença (divide o custo da licença pelo período de amortização)
        $perpAmortAnnual = $licenseCost / $amortizationYears;

        // Total anual = amortização da licença + SA anual
        $perpAnnual  = $perpAmortAnnual + $saAnnualEffective;
        $perpMonthly = $perpAnnual / 12;

        // Total em 3 anos: se amortização >= 3, custo da licença proporcional + 3 anos de SA
        // Custo real em 3 anos = licença total + 3 anos de SA
        $perpTotal3y = $licenseCost + ($saAnnualEffective * 3);

        $perpetual = [
            'monthly'     => round($perpMonthly, 2),
            'annual'      => round($perpAnnual, 2),
            'total3y'     => round($perpTotal3y, 2),
            'packs'       => $packs,
            'unitPrice'   => round($this->applyRate($perpPricePerPack, $currency), 2),
            'licenseCost' => round($licenseCost, 2),
            'saAnnual'    => round($saAnnual, 2),
            'label'       => 'Software Perpétuo + SA',
        ];

        // ===== 6. OVS (Open Value Subscription) =====
        $ovsPricePerPack = $this->editionPrice('ovs', $edition); // preço por pack/ano
        $ovsAnnualUSD    = $packs * $ovsPricePerPack;
        $ovsAnnual       = $this->applyRate($ovsAnnualUSD, $currency);
        $ovsMonthly      = $ovsAnnual / 12;
        $ovsTotal3y      = $ovsAnnual * 3;

        $ovs = [
            'monthly'   => round($ovsMonthly, 2),
            'annual'    => round($ovsAnnual, 2),
            'total3y'   => round($ovsTotal3y, 2),
            'packs'     => $packs,
            'unitPrice' => round($this->applyRate($ovsPricePerPack, $currency), 2),
            'label'     => 'Open Value Subscription',
        ];

        // ===== Determinar melhor modelo (menor custo mensal) =====
        $models = [
            'arc'       => $arc,
            'spla'      => $spla,
            'csp1y'     => $csp1y,
            'csp3y'     => $csp3y,
            'perpetual' => $perpetual,
            'ovs'       => $ovs,
        ];

        $bestKey     = 'arc';
        $bestMonthly = $arc['monthly'];
        foreach ($models as $key => $model) {
            if ($model['monthly'] < $bestMonthly) {
                $bestMonthly = $model['monthly'];
                $bestKey     = $key;
            }
        }

        // ===== Savings (economia comparada ao ARC) =====
        $savings = [];
        $comparisons = [
            'vsSpla'      => 'spla',
            'vsCsp1y'     => 'csp1y',
            'vsCsp3y'     => 'csp3y',
            'vsPerpetual' => 'perpetual',
            'vsOvs'       => 'ovs',
        ];

        foreach ($comparisons as $savingsKey => $modelKey) {
            $otherMonthly    = $models[$modelKey]['monthly'];
            $savingsMonthly  = $otherMonthly - $arcMonthly;
            $savingsPct      = $otherMonthly > 0
                ? round((($otherMonthly - $arcMonthly) / $otherMonthly) * 100, 1)
                : 0;

            $savings[$savingsKey] = [
                'monthly' => round($savingsMonthly, 2),
                'pct'     => $savingsPct,
            ];
        }

        return [
            'arc'       => $arc,
            'spla'      => $spla,
            'csp1y'     => $csp1y,
            'csp3y'     => $csp3y,
            'perpetual' => $perpetual,
            'ovs'       => $ovs,
            'bestModel' => $bestKey,
            'savings'   => $savings,
            'params'    => [
                'vCores'            => $vCores,
                'edition'           => $edition,
                'hoursPerMonth'     => $hoursPerMonth,
                'currency'          => $currency,
                'amortizationYears' => $amortizationYears,
                'hasSA'             => $hasSA,
                'clientName'        => $clientName,
                'vendorName'        => $vendorName,
            ],
        ];
    }
}
