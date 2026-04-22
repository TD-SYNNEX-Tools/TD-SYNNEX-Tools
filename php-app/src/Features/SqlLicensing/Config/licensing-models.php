<?php

/**
 * Configuração dos Modelos de Licenciamento
 * Define os parâmetros e preços de cada modelo
 */

return [
    'azure_arc' => [
        'id' => 'azure_arc',
        'name' => 'Azure ARC',
        'billing_type' => 'monthly',
        'pricing' => [
            'monthly_per_core' => 65.00, // USD por vCore/mês
            'setup_fee' => 0
        ],
        'terms' => [
            'minimum_commitment_months' => 0,
            'cancellation_notice_days' => 30,
            'flexible' => true
        ],
        'features' => [
            'pay_as_you_go' => true,
            'azure_hybrid_benefit' => true,
            'included_services' => ['Extended Security Updates', 'Azure Management', 'Azure Policy']
        ],
        'best_for' => 'Curto prazo, flexibilidade, workloads variáveis'
    ],
    
    'spla' => [
        'id' => 'spla',
        'name' => 'SPLA',
        'billing_type' => 'monthly',
        'pricing' => [
            'monthly_per_core' => 70.00, // USD por core/mês (pack de 2 cores = 140)
            'setup_fee' => 0,
            'reporting_fee_monthly' => 0 // Custo administrativo pode ser adicionado
        ],
        'terms' => [
            'minimum_commitment_months' => 0,
            'monthly_reporting_required' => true,
            'service_provider_only' => true
        ],
        'features' => [
            'pay_as_you_go' => true,
            'flexible_licensing' => true,
            'monthly_reporting' => true
        ],
        'best_for' => 'Service Providers, cobrança baseada em uso real'
    ],
    
    'csp_subscription' => [
        'id' => 'csp_subscription',
        'name' => 'CSP Subscription',
        'billing_type' => 'monthly',
        'pricing' => [
            'monthly_per_core' => 62.00, // USD por core/mês
            'setup_fee' => 0,
            'volume_discounts' => [
                '1-10' => 0,
                '11-50' => 0.05,
                '51-100' => 0.10,
                '100+' => 0.15
            ]
        ],
        'terms' => [
            'minimum_commitment_months' => 12,
            'managed_by_partner' => true,
            'annual_commitment_recommended' => true
        ],
        'features' => [
            'partner_support' => true,
            'flexible_billing' => true,
            'volume_discounts' => true
        ],
        'best_for' => 'Empresas com parceiro Microsoft, suporte gerenciado'
    ],
    
    'perpetual' => [
        'id' => 'perpetual',
        'name' => 'Software Perpétuo',
        'billing_type' => 'perpetual',
        'pricing' => [
            'license_per_core' => 3717.00, // USD - SQL Server Standard (2-core pack base)
            'software_assurance_annual_percentage' => 0.25, // 25% ao ano
            'sa_optional' => true
        ],
        'terms' => [
            'lifetime_ownership' => true,
            'sa_recommended' => true,
            'one_time_purchase' => true
        ],
        'features' => [
            'permanent_license' => true,
            'one_time_payment' => true,
            'upgrade_rights_with_sa' => true,
            'perpetual_use' => true
        ],
        'best_for' => 'Longo prazo (5+ anos), workloads estáveis, CAPEX disponível'
    ],
    
    'open_value' => [
        'id' => 'open_value',
        'name' => 'Open Value',
        'billing_type' => 'contract',
        'pricing' => [
            'license_per_core' => 3200.00, // USD por core (desconto vs perpétuo)
            'contract_years' => 3,
            'payment_frequency' => 'annual',
            'annual_payments' => 3
        ],
        'terms' => [
            'minimum_licenses' => 5,
            'contract_duration_years' => 3,
            'sa_included' => true,
            'spread_payments' => true
        ],
        'features' => [
            'spread_payments' => true,
            'software_assurance_included' => true,
            'volume_licensing' => true,
            'upgrade_rights' => true
        ],
        'best_for' => 'Empresas com múltiplas licenças, pagamento parcelado em 3 anos'
    ],
    
    'open_value_subscription' => [
        'id' => 'open_value_subscription',
        'name' => 'Open Value Subscription',
        'billing_type' => 'contract',
        'pricing' => [
            'annual_per_core' => 1100.00, // USD por core/ano
            'contract_years' => 3,
            'payment_frequency' => 'annual'
        ],
        'terms' => [
            'minimum_licenses' => 5,
            'contract_duration_years' => 3,
            'rights_expire_at_end' => true,
            'no_perpetual_rights' => true
        ],
        'features' => [
            'subscription_model' => true,
            'predictable_costs' => true,
            'no_ownership' => true,
            'sa_included' => true
        ],
        'best_for' => 'Previsibilidade de custos, contrato de 3 anos, sem propriedade'
    ]
];
