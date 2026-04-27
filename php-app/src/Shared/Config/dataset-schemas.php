<?php
/**
 * Azure Cost Management Dataset Schemas
 * Referência: https://learn.microsoft.com/en-us/azure/cost-management-billing/dataset-schema/schema-index
 * 
 * Agreement Types:
 * - EA   = Enterprise Agreement
 * - MCA  = Microsoft Customer Agreement
 * - MPA  = Microsoft Partner Agreement
 * - CSP  = Cloud Solution Provider
 * - MOSA = Microsoft Online Services Agreement (Pay-as-you-go)
 */

return [
    'schemas' => [
        // ═══════════════════════════════════════════════════════════════════
        // LATEST VERSIONS (2023-12-01-preview)
        // ═══════════════════════════════════════════════════════════════════
        'ea_2023-12-01' => [
            'name'         => 'Enterprise Agreement (EA)',
            'version'      => '2023-12-01-preview',
            'apiVersion'   => '2023-12-01-preview',
            'agreement'    => 'EA',
            'isLatest'     => true,
            'quantityCol'  => 'quantity',
            'requiredCols' => [
                'meterid'       => 'MeterID',
                'metername'     => 'MeterName',
                'quantity'      => 'Quantity',
                'unitofmeasure' => 'UnitOfMeasure',
            ],
            'optionalCols' => [
                'resourcelocation' => 'ResourceLocation',
                'servicename'      => 'ServiceName',
                'metercategory'    => 'MeterCategory',
                'productname'      => 'ProductName',
                'servicefamily'    => 'ServiceFamily',
                'date'             => 'Date',
                'usagedatetime'    => 'UsageDateTime',
                'costinbillingcurrency' => 'CostInBillingCurrency',
                'subscriptionname' => 'SubscriptionName',
                'resourcegroup'    => 'ResourceGroup',
            ],
        ],
        
        'mca_2023-12-01' => [
            'name'         => 'Microsoft Customer Agreement (MCA)',
            'version'      => '2023-12-01-preview',
            'apiVersion'   => '2023-12-01-preview',
            'agreement'    => 'MCA',
            'isLatest'     => true,
            'quantityCol'  => 'quantity',
            'requiredCols' => [
                'meterid'       => 'MeterID',
                'metername'     => 'MeterName',
                'quantity'      => 'Quantity',
                'unitofmeasure' => 'UnitOfMeasure',
            ],
            'optionalCols' => [
                'resourcelocation' => 'ResourceLocation',
                'servicename'      => 'ServiceName',
                'metercategory'    => 'MeterCategory',
                'productname'      => 'ProductName',
                'servicefamily'    => 'ServiceFamily',
                'date'             => 'Date',
                'usagedatetime'    => 'UsageDateTime',
                'costinbillingcurrency' => 'CostInBillingCurrency',
                'subscriptionname' => 'SubscriptionName',
                'resourcegroup'    => 'ResourceGroup',
            ],
        ],
        
        'mpa_2023-12-01' => [
            'name'         => 'Microsoft Partner Agreement (MPA)',
            'version'      => '2023-12-01-preview',
            'apiVersion'   => '2023-12-01-preview',
            'agreement'    => 'MPA',
            'isLatest'     => true,
            'quantityCol'  => 'usagequantity',
            'requiredCols' => [
                'meterid'        => 'MeterID',
                'metername'      => 'MeterName',
                'usagequantity'  => 'UsageQuantity',
                'unitofmeasure'  => 'UnitOfMeasure',
            ],
            'optionalCols' => [
                'resourcelocation' => 'ResourceLocation',
                'servicename'      => 'ServiceName',
                'metercategory'    => 'MeterCategory',
                'productname'      => 'ProductName',
                'servicefamily'    => 'ServiceFamily',
                'date'             => 'Date',
                'usagedatetime'    => 'UsageDateTime',
                'costinbillingcurrency' => 'CostInBillingCurrency',
                'subscriptionname' => 'SubscriptionName',
                'resourcegroup'    => 'ResourceGroup',
            ],
        ],
        
        'csp_2023-12-01' => [
            'name'         => 'Cloud Solution Provider (CSP)',
            'version'      => '2023-12-01-preview',
            'apiVersion'   => '2023-12-01-preview',
            'agreement'    => 'CSP',
            'isLatest'     => true,
            'quantityCol'  => 'usagequantity',
            'requiredCols' => [
                'meterid'        => 'MeterID',
                'metername'      => 'MeterName',
                'usagequantity'  => 'UsageQuantity',
                'unitofmeasure'  => 'UnitOfMeasure',
            ],
            'optionalCols' => [
                'resourcelocation' => 'ResourceLocation',
                'servicename'      => 'ServiceName',
                'metercategory'    => 'MeterCategory',
                'productname'      => 'ProductName',
                'servicefamily'    => 'ServiceFamily',
                'date'             => 'Date',
                'usagedatetime'    => 'UsageDateTime',
                'costinbillingcurrency' => 'CostInBillingCurrency',
                'subscriptionname' => 'SubscriptionName',
                'resourcegroup'    => 'ResourceGroup',
            ],
        ],
        
        // ═══════════════════════════════════════════════════════════════════
        // MOSA / PAY-AS-YOU-GO (2019-11-01)
        // ═══════════════════════════════════════════════════════════════════
        'mosa_2019-11-01' => [
            'name'         => 'Pay-as-you-go (MOSA)',
            'version'      => '2019-11-01',
            'apiVersion'   => '2019-11-01',
            'agreement'    => 'MOSA',
            'isLatest'     => true,
            'quantityCol'  => 'quantity',
            'requiredCols' => [
                'meterid'       => 'MeterID',
                'metername'     => 'MeterName',
                'quantity'      => 'Quantity',
                'unitofmeasure' => 'UnitOfMeasure',
            ],
            'optionalCols' => [
                'resourcelocation' => 'ResourceLocation',
                'metercategory'    => 'MeterCategory',
                'metersubcategory' => 'MeterSubCategory',
                'productname'      => 'ProductName',
                'servicefamily'    => 'ServiceFamily',
                'date'             => 'Date',
                'costinbillingcurrency' => 'CostInBillingCurrency',
                'subscriptionname' => 'SubscriptionName',
                'resourcegroup'    => 'ResourceGroup',
                'consumedservice'  => 'ConsumedService',
            ],
        ],
        
        // ═══════════════════════════════════════════════════════════════════
        // OLDER VERSIONS (2021-01-01)
        // ═══════════════════════════════════════════════════════════════════
        'ea_2021-01-01' => [
            'name'         => 'Enterprise Agreement (EA)',
            'version'      => '2021-01-01',
            'apiVersion'   => '2021-01-01',
            'agreement'    => 'EA',
            'isLatest'     => false,
            'quantityCol'  => 'quantity',
            'requiredCols' => [
                'meterid'       => 'MeterID',
                'metername'     => 'MeterName',
                'quantity'      => 'Quantity',
                'unitofmeasure' => 'UnitOfMeasure',
            ],
            'optionalCols' => [
                'resourcelocation' => 'ResourceLocation',
                'metercategory'    => 'MeterCategory',
                'productname'      => 'ProductName',
                'date'             => 'Date',
                'usagedatetime'    => 'UsageDateTime',
                'costinbillingcurrency' => 'CostInBillingCurrency',
            ],
        ],
        
        'mca_2021-01-01' => [
            'name'         => 'Microsoft Customer Agreement (MCA)',
            'version'      => '2021-01-01',
            'apiVersion'   => '2021-01-01',
            'agreement'    => 'MCA',
            'isLatest'     => false,
            'quantityCol'  => 'quantity',
            'requiredCols' => [
                'meterid'       => 'MeterID',
                'metername'     => 'MeterName',
                'quantity'      => 'Quantity',
                'unitofmeasure' => 'UnitOfMeasure',
            ],
            'optionalCols' => [
                'resourcelocation' => 'ResourceLocation',
                'metercategory'    => 'MeterCategory',
                'productname'      => 'ProductName',
                'date'             => 'Date',
                'usagedatetime'    => 'UsageDateTime',
                'costinbillingcurrency' => 'CostInBillingCurrency',
            ],
        ],
        
        'csp_2021-01-01' => [
            'name'         => 'Cloud Solution Provider (CSP)',
            'version'      => '2021-01-01',
            'apiVersion'   => '2021-01-01',
            'agreement'    => 'CSP',
            'isLatest'     => false,
            'quantityCol'  => 'usagequantity',
            'requiredCols' => [
                'meterid'        => 'MeterID',
                'metername'      => 'MeterName',
                'usagequantity'  => 'UsageQuantity',
                'unitofmeasure'  => 'UnitOfMeasure',
            ],
            'optionalCols' => [
                'resourcelocation' => 'ResourceLocation',
                'metercategory'    => 'MeterCategory',
                'productname'      => 'ProductName',
                'date'             => 'Date',
                'usagedatetime'    => 'UsageDateTime',
                'costinbillingcurrency' => 'CostInBillingCurrency',
            ],
        ],
        
        'mpa_2021-01-01' => [
            'name'         => 'Microsoft Partner Agreement (MPA)',
            'version'      => '2021-01-01',
            'apiVersion'   => '2021-01-01',
            'agreement'    => 'MPA',
            'isLatest'     => false,
            'quantityCol'  => 'usagequantity',
            'requiredCols' => [
                'meterid'        => 'MeterID',
                'metername'      => 'MeterName',
                'usagequantity'  => 'UsageQuantity',
                'unitofmeasure'  => 'UnitOfMeasure',
            ],
            'optionalCols' => [
                'resourcelocation' => 'ResourceLocation',
                'metercategory'    => 'MeterCategory',
                'productname'      => 'ProductName',
                'date'             => 'Date',
                'usagedatetime'    => 'UsageDateTime',
                'costinbillingcurrency' => 'CostInBillingCurrency',
            ],
        ],
        
        // ═══════════════════════════════════════════════════════════════════
        // LEGACY VERSIONS (2019-11-01 / 2019-10-01)
        // ═══════════════════════════════════════════════════════════════════
        'mca_2019-11-01' => [
            'name'         => 'Microsoft Customer Agreement (MCA)',
            'version'      => '2019-11-01',
            'apiVersion'   => '2019-11-01',
            'agreement'    => 'MCA',
            'isLatest'     => false,
            'quantityCol'  => 'quantity',
            'requiredCols' => [
                'meterid'       => 'MeterID',
                'metername'     => 'MeterName',
                'quantity'      => 'Quantity',
                'unitofmeasure' => 'UnitOfMeasure',
            ],
            'optionalCols' => [
                'location'         => 'Location',           // Friendly name: "BR South" (preferido para comparação com API)
                'resourcelocation' => 'ResourceLocation',   // armRegionName: "brazilsouth"
                'metercategory'    => 'MeterCategory',
                'productname'      => 'ProductName',
                'date'             => 'Date',
                'costinbillingcurrency' => 'CostInBillingCurrency',
            ],
        ],
        
        'ea_2019-10-01' => [
            'name'         => 'Enterprise Agreement (EA)',
            'version'      => '2019-10-01',
            'apiVersion'   => '2019-10-01',
            'agreement'    => 'EA',
            'isLatest'     => false,
            'quantityCol'  => 'quantity',
            'requiredCols' => [
                'meterid'       => 'MeterID',
                'metername'     => 'MeterName',
                'quantity'      => 'Quantity',
                'unitofmeasure' => 'UnitOfMeasure',
            ],
            'optionalCols' => [
                'resourcelocation' => 'ResourceLocation',
                'metercategory'    => 'MeterCategory',
                'productname'      => 'ProductName',
                'date'             => 'Date',
                'costinbillingcurrency' => 'CostInBillingCurrency',
            ],
        ],
        
        'csp_2019-11-01' => [
            'name'         => 'Cloud Solution Provider (CSP)',
            'version'      => '2019-11-01',
            'apiVersion'   => '2019-11-01',
            'agreement'    => 'CSP',
            'isLatest'     => false,
            'quantityCol'  => 'usagequantity',
            'requiredCols' => [
                'meterid'        => 'MeterID',
                'metername'      => 'MeterName',
                'usagequantity'  => 'UsageQuantity',
                'unitofmeasure'  => 'UnitOfMeasure',
            ],
            'optionalCols' => [
                'resourcelocation' => 'ResourceLocation',
                'metercategory'    => 'MeterCategory',
                'productname'      => 'ProductName',
                'date'             => 'Date',
                'costinbillingcurrency' => 'CostInBillingCurrency',
            ],
        ],
        
        'mpa_2019-11-01' => [
            'name'         => 'Microsoft Partner Agreement (MPA)',
            'version'      => '2019-11-01',
            'apiVersion'   => '2019-11-01',
            'agreement'    => 'MPA',
            'isLatest'     => false,
            'quantityCol'  => 'usagequantity',
            'requiredCols' => [
                'meterid'        => 'MeterID',
                'metername'      => 'MeterName',
                'usagequantity'  => 'UsageQuantity',
                'unitofmeasure'  => 'UnitOfMeasure',
            ],
            'optionalCols' => [
                'resourcelocation' => 'ResourceLocation',
                'metercategory'    => 'MeterCategory',
                'productname'      => 'ProductName',
                'date'             => 'Date',
                'costinbillingcurrency' => 'CostInBillingCurrency',
            ],
        ],
    ],
    
    // Mapeamento de tipos de migração antigos para schemas
    'migrationMap' => [
        'mosp_csp' => 'mosa_2019-11-01',  // MOSP → CSP
        'mpn_csp'  => 'mpa_2021-01-01',   // MPN → CSP
    ],
    
    // Agreements resumidos para UI
    'agreements' => [
        'EA'   => 'Enterprise Agreement',
        'MCA'  => 'Microsoft Customer Agreement',
        'MPA'  => 'Microsoft Partner Agreement',
        'CSP'  => 'Cloud Solution Provider',
        'MOSA' => 'Pay-as-you-go (MOSP)',
    ],
];
