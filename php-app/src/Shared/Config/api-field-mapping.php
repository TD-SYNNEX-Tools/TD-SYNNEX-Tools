<?php
/**
 * Mapeamento de campos entre CSV Cost Management e API Pública de Preços Azure
 * 
 * Referências:
 * - CSV Schema: https://learn.microsoft.com/en-us/azure/cost-management-billing/dataset-schema/schema-index
 * - API Pricing: https://learn.microsoft.com/en-us/rest/api/cost-management/retail-prices/azure-retail-prices
 * 
 * Exemplo de resposta da API:
 * {
 *   "currencyCode": "USD",
 *   "tierMinimumUnits": 0,
 *   "retailPrice": 3.6864,
 *   "unitPrice": 3.6864,
 *   "armRegionName": "brazilsouth",
 *   "location": "BR South",
 *   "effectiveStartDate": "2017-10-01T00:00:00Z",
 *   "meterId": "bd5a77f6-93be-4dde-8822-be7f8f4df8d8",
 *   "meterName": "S4 LRS Disk",
 *   "productId": "DZH318Z0BP0L",
 *   "skuId": "DZH318Z0BP0L/005Q",
 *   "productName": "Standard HDD Managed Disks",
 *   "skuName": "S4 LRS",
 *   "serviceName": "Storage",
 *   "serviceId": "DZH317F1HKN0",
 *   "serviceFamily": "Storage",
 *   "unitOfMeasure": "1/Month",
 *   "type": "Consumption",
 *   "isPrimaryMeterRegion": true,
 *   "armSkuName": ""
 * }
 */

return [
    // ═══════════════════════════════════════════════════════════════════════════
    // MAPEAMENTO: CSV Cost Management → API Azure Pricing
    // ═══════════════════════════════════════════════════════════════════════════
    'fieldMapping' => [
        // ── Identificadores Primários ──
        'meterid' => [
            'apiField'      => 'meterId',
            'description'   => 'GUID único do medidor Azure',
            'matchWeight'   => 10,      // Peso alto - é o identificador principal
            'required'      => true,
            'exactMatch'    => true,
            'caseSensitive' => false,
        ],
        
        // ── Nomes e Descrições ──
        'metername' => [
            'apiField'      => 'meterName',
            'description'   => 'Nome do medidor (ex: S4 LRS Disk)',
            'matchWeight'   => 4,
            'required'      => true,
            'exactMatch'    => false,   // Permite fuzzy matching
            'caseSensitive' => false,
        ],
        
        'productname' => [
            'apiField'      => 'productName',
            'description'   => 'Nome do produto (ex: Standard HDD Managed Disks)',
            'matchWeight'   => 3,
            'required'      => false,
            'exactMatch'    => false,   // Permite fuzzy matching
            'caseSensitive' => false,
        ],
        
        // ── Identificadores de Produto/SKU ──
        'productid' => [
            'apiField'      => 'productId',
            'description'   => 'ID do produto (ex: DZH318Z0BP0L)',
            'matchWeight'   => 5,
            'required'      => false,
            'exactMatch'    => true,
            'caseSensitive' => false,
        ],
        
        // Nota: skuId da API é "productId/código" (ex: DZH318Z0BP0L/005Q)
        // O CSV geralmente não tem skuId diretamente
        
        // ── Serviço e Categoria ──
        'servicename' => [
            'apiField'      => 'serviceName',
            'description'   => 'Nome do serviço (ex: Storage, Compute)',
            'matchWeight'   => 3,
            'required'      => true,
            'exactMatch'    => false,
            'caseSensitive' => false,
            'csvAliases'    => ['metercategory', 'consumedservice'], // Fallbacks no CSV
        ],
        
        'servicefamily' => [
            'apiField'      => 'serviceFamily',
            'description'   => 'Família do serviço (ex: Storage, Compute)',
            'matchWeight'   => 2,
            'required'      => false,
            'exactMatch'    => false,
            'caseSensitive' => false,
        ],
        
        // ── Unidade de Medida ──
        'unitofmeasure' => [
            'apiField'      => 'unitOfMeasure',
            'description'   => 'Unidade de medida (ex: 1/Month, 1 Hour)',
            'matchWeight'   => 4,
            'required'      => true,
            'exactMatch'    => false,   // Requer normalização (ex: "1 / Hour" vs "1/Hour")
            'caseSensitive' => false,
            'normalizer'    => 'normalizeUom',
        ],
        
        // ── Localização/Região ──
        'resourcelocation' => [
            'apiField'      => 'location',          // Usar 'location' (ex: "BR South"), NÃO 'armRegionName'
            'apiAltField'   => 'armRegionName',     // Alternativa: "brazilsouth"
            'description'   => 'Região Azure do recurso',
            'matchWeight'   => 4,
            'required'      => false,
            'exactMatch'    => false,
            'caseSensitive' => false,
            'csvAliases'    => ['location'],        // Fallback no CSV
            'normalizer'    => 'normalizeRegion',
        ],
        
        // ── Preços ──
        'effectiveprice' => [
            'apiField'      => 'retailPrice',       // Preço público retail
            'apiAltField'   => 'unitPrice',         // Preço unitário (geralmente igual)
            'description'   => 'Preço efetivo por unidade',
            'matchWeight'   => 0,                   // Não usado para matching, só comparação
            'required'      => false,
            'numeric'       => true,
            'csvAliases'    => ['unitprice', 'retailprice'],
        ],
        
        // ── Moeda ──
        'billingcurrencycode' => [
            'apiField'      => 'currencyCode',
            'description'   => 'Código da moeda (USD, BRL, etc.)',
            'matchWeight'   => 1,
            'required'      => false,
            'exactMatch'    => true,
            'caseSensitive' => true,
            'csvAliases'    => ['currency', 'billingcurrency'],
        ],
        
        // ── Tipo de Consumo ──
        // Nota: API retorna "type": "Consumption" ou "Reservation"
        // CSV pode ter "ChargeType" ou similar
        'chargetype' => [
            'apiField'      => 'type',
            'description'   => 'Tipo de cobrança (Consumption, Reservation)',
            'matchWeight'   => 2,
            'required'      => false,
            'exactMatch'    => false,
            'caseSensitive' => false,
            'valueMapping'  => [
                'usage'       => 'Consumption',
                'purchase'    => 'Reservation',
                'consumption' => 'Consumption',
            ],
        ],
    ],
    
    // ═══════════════════════════════════════════════════════════════════════════
    // CAMPOS DA API NÃO PRESENTES NO CSV (informativos)
    // ═══════════════════════════════════════════════════════════════════════════
    'apiOnlyFields' => [
        'tierMinimumUnits'    => 'Quantidade mínima para tier de preço (volume discount)',
        'skuId'               => 'ID completo do SKU (productId/código) - NÃO presente no CSV padrão',
        'skuName'             => 'Nome do SKU (ex: S4 LRS)',
        'serviceId'           => 'ID interno do serviço Azure',
        'effectiveStartDate'  => 'Data de início da vigência do preço',
        'effectiveEndDate'    => 'Data de fim da vigência do preço (se houver)',
        'isPrimaryMeterRegion'=> 'Se é a região primária do meter',
        'armSkuName'          => 'Nome ARM do SKU (pode ser vazio)',
        'reservationTerm'     => 'Termo de reserva (1Year, 3Years) - só para Reserved',
        'savingsPlan'         => 'Informações de Savings Plan',
    ],
    
    // ═══════════════════════════════════════════════════════════════════════════
    // MAPEAMENTO DE REGIÕES: CSV → API
    // ═══════════════════════════════════════════════════════════════════════════
    'regionMapping' => [
        // Brasil
        'brazil south'      => ['armRegionName' => 'brazilsouth',     'location' => 'BR South'],
        'brazilsouth'       => ['armRegionName' => 'brazilsouth',     'location' => 'BR South'],
        'br south'          => ['armRegionName' => 'brazilsouth',     'location' => 'BR South'],
        'brazil southeast'  => ['armRegionName' => 'brazilsoutheast', 'location' => 'BR Southeast'],
        
        // Estados Unidos
        'east us'           => ['armRegionName' => 'eastus',          'location' => 'East US'],
        'eastus'            => ['armRegionName' => 'eastus',          'location' => 'East US'],
        'east us 2'         => ['armRegionName' => 'eastus2',         'location' => 'East US 2'],
        'westus'            => ['armRegionName' => 'westus',          'location' => 'West US'],
        'west us'           => ['armRegionName' => 'westus',          'location' => 'West US'],
        'west us 2'         => ['armRegionName' => 'westus2',         'location' => 'West US 2'],
        'west us 3'         => ['armRegionName' => 'westus3',         'location' => 'West US 3'],
        'central us'        => ['armRegionName' => 'centralus',       'location' => 'Central US'],
        'north central us'  => ['armRegionName' => 'northcentralus',  'location' => 'North Central US'],
        'south central us'  => ['armRegionName' => 'southcentralus',  'location' => 'South Central US'],
        
        // Europa
        'west europe'       => ['armRegionName' => 'westeurope',      'location' => 'West Europe'],
        'north europe'      => ['armRegionName' => 'northeurope',     'location' => 'North Europe'],
        'uk south'          => ['armRegionName' => 'uksouth',         'location' => 'UK South'],
        'uk west'           => ['armRegionName' => 'ukwest',          'location' => 'UK West'],
        'france central'    => ['armRegionName' => 'francecentral',   'location' => 'France Central'],
        'germany west'      => ['armRegionName' => 'germanywestcentral', 'location' => 'Germany West Central'],
        
        // Ásia
        'southeast asia'    => ['armRegionName' => 'southeastasia',   'location' => 'Southeast Asia'],
        'east asia'         => ['armRegionName' => 'eastasia',        'location' => 'East Asia'],
        'japan east'        => ['armRegionName' => 'japaneast',       'location' => 'Japan East'],
        'japan west'        => ['armRegionName' => 'japanwest',       'location' => 'Japan West'],
        'australia east'    => ['armRegionName' => 'australiaeast',   'location' => 'Australia East'],
        
        // Global
        'global'            => ['armRegionName' => 'global',          'location' => 'Global'],
        'unassigned'        => ['armRegionName' => '',                'location' => 'Unassigned'],
    ],
    
    // ═══════════════════════════════════════════════════════════════════════════
    // NORMALIZAÇÃO DE UNIT OF MEASURE
    // ═══════════════════════════════════════════════════════════════════════════
    'uomNormalization' => [
        // Padrões de espaço
        '1 / hour'    => '1/hour',
        '1 / month'   => '1/month',
        '1 / day'     => '1/day',
        '1 gb / month'=> '1 gb/month',
        '10 gb / month'=> '10 gb/month',
        '100 gb / month'=> '100 gb/month',
        
        // Abreviações
        'hours'       => 'hour',
        'hrs'         => 'hour',
        'months'      => 'month',
        'days'        => 'day',
        
        // Unidades compostas
        'gb-month'    => 'gb/month',
        'tb-month'    => 'tb/month',
    ],
    
    // ═══════════════════════════════════════════════════════════════════════════
    // CAMPOS PARA COMPARAÇÃO (ordenados por prioridade)
    // ═══════════════════════════════════════════════════════════════════════════
    'comparisonFields' => [
        // Campos obrigatórios para match (peso alto)
        'primary' => [
            ['csv' => 'meterid',       'api' => 'meterId',       'weight' => 10, 'exact' => true],
        ],
        
        // Campos de confirmação (peso médio)
        'secondary' => [
            ['csv' => 'unitofmeasure', 'api' => 'unitOfMeasure', 'weight' => 4, 'normalizer' => 'uom'],
            ['csv' => 'resourcelocation', 'api' => 'location',   'weight' => 4, 'normalizer' => 'region'],
            ['csv' => 'metername',     'api' => 'meterName',     'weight' => 4, 'fuzzy' => true],
            ['csv' => 'productname',   'api' => 'productName',   'weight' => 3, 'fuzzy' => true],
            ['csv' => 'servicename',   'api' => 'serviceName',   'weight' => 3, 'fuzzy' => true],
        ],
        
        // Campos informativos (peso baixo)
        'tertiary' => [
            ['csv' => 'servicefamily', 'api' => 'serviceFamily', 'weight' => 2, 'fuzzy' => true],
            ['csv' => 'productid',     'api' => 'productId',     'weight' => 2, 'exact' => true],
            ['csv' => 'billingcurrencycode', 'api' => 'currencyCode', 'weight' => 1, 'exact' => true],
        ],
    ],
    
    // ═══════════════════════════════════════════════════════════════════════════
    // SCORE THRESHOLDS
    // ═══════════════════════════════════════════════════════════════════════════
    'scoreThresholds' => [
        'maxScore'       => 33,   // Soma de todos os weights
        'matchThreshold' => 28,   // Score mínimo para MATCH (85%)
        'partialThreshold' => 20, // Score mínimo para PARTIAL_MATCH (60%)
    ],
];
