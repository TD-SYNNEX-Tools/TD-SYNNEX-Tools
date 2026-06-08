<?php
/**
 * API de Chat para Análise de Recursos Azure
 * Assistente especializado em análise de recursos Azure, migração e custos
 */
header("Content-Type: application/json; charset=utf-8");
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use App\Shared\Services\ResultsCache;

// ============================================================================
// CONFIGURAÇÕES DO AZURE OPENAI
// ============================================================================
$foundryEndpoint = "https://azopenai002026.openai.azure.com/openai/deployments/gpt-4o/chat/completions?api-version=2024-02-15-preview"; 
$foundryApiKey   = "4IVuw4zEn9P9owIhcGqUux5o72MI3hYLk8BKa1dyyT9Bxfr096JEJQQJ99CCACYeBjFXJ3w3AAABACOG01vw";
$foundryModel    = "gpt-4o";
// ============================================================================

// Lê o corpo da requisição
$input = json_decode(file_get_contents("php://input"), true);
$userMessage = $input["message"] ?? "";
$history = $input["history"] ?? [];
$resourceContext = $input["resourceContext"] ?? [];

if (empty($userMessage)) {
    echo json_encode(["reply" => "Por favor, envie uma mensagem válida."]);
    exit;
}

// Carregar dados da análise da sessão (se disponível)
$financialResults = ResultsCache::get('financialResults') ?? ($_SESSION['financialResults'] ?? null);

// Construir contexto dos recursos
$resourceSummary = "Nenhuma análise carregada.";
$technicalSummary = "";
$byServiceText = "";
$byResourceGroupText = "";
$bySubscriptionText = "";
$byRegionText = "";
$detailsText = "";
$totalRowsActual = 0;

if ($financialResults) {
    $summary = $financialResults['summary'] ?? [];
    $results = $financialResults['results'] ?? [];
    
    // Resumo geral
    $resourceSummary = "
=== RESUMO DA ANÁLISE ATUAL ===
- Total de linhas: " . number_format($summary['totalRows'] ?? 0) . "
- MeterIDs únicos: " . number_format($summary['uniqueMeterIds'] ?? 0) . "
- Custo MOSP Total: USD " . number_format($summary['totalMosp'] ?? 0, 2) . "
- Custo CSP Total (FOB): USD " . number_format($summary['totalCsp'] ?? 0, 2) . "
- Diferença: " . number_format($summary['differencePercent'] ?? 0, 2) . "%
- Itens não encontrados na API: " . ($summary['notFoundCount'] ?? 0) . "
";

    // Resumo por serviço (COMPLETO - sem cap top-N para evitar alucinação)
    if (!empty($summary['byService'])) {
        $svcTotal = count($summary['byService']);
        $byServiceText = "\n=== CUSTO POR SERVIÇO (TODOS os {$svcTotal} serviços, ordenados por custo CSP) ===\n";
        // Ordenar por costCsp desc para garantir consistência
        $svcSorted = $summary['byService'];
        uasort($svcSorted, fn($a,$b) => ($b['costCsp'] ?? 0) <=> ($a['costCsp'] ?? 0));
        $cap = 80; $i = 0; $truncated = 0;
        foreach ($svcSorted as $service => $data) {
            if ($i++ >= $cap) { $truncated++; continue; }
            $costCsp  = $data['costCsp']  ?? 0;
            $costMosp = $data['costMosp'] ?? 0;
            $count    = $data['count']    ?? 0;
            $byServiceText .= "- {$service}: USD " . number_format($costCsp, 2) . " (CSP) | USD " . number_format($costMosp, 2) . " (MOSP) | {$count} itens\n";
        }
        if ($truncated > 0) $byServiceText .= "(+{$truncated} serviços adicionais com valores menores)\n";
    }

    // Resumo por Resource Group (COMPLETO)
    if (!empty($summary['byResourceGroup'])) {
        $rgTotal = count($summary['byResourceGroup']);
        $byResourceGroupText = "\n=== CUSTO POR RESOURCE GROUP (TODOS os {$rgTotal} RGs, ordenados por custo CSP) ===\n";
        $rgSorted = $summary['byResourceGroup'];
        uasort($rgSorted, fn($a,$b) => ($b['costCsp'] ?? 0) <=> ($a['costCsp'] ?? 0));
        $cap = 80; $i = 0; $truncated = 0;
        foreach ($rgSorted as $rg => $data) {
            if ($i++ >= $cap) { $truncated++; continue; }
            $costCsp  = $data['costCsp']  ?? 0;
            $costMosp = $data['costMosp'] ?? 0;
            $count    = $data['count']    ?? 0;
            $byResourceGroupText .= "- {$rg}: USD " . number_format($costCsp, 2) . " (CSP) | USD " . number_format($costMosp, 2) . " (MOSP) | {$count} itens\n";
        }
        if ($truncated > 0) $byResourceGroupText .= "(+{$truncated} RGs adicionais com valores menores)\n";
    }

    // Resumo por Subscription (construir a partir dos resultados)
    $subscriptionTotals = [];
    foreach ($results as $row) {
        $subName = $row['subscriptionName'] ?? 'Sem assinatura';
        if (!isset($subscriptionTotals[$subName])) {
            $subscriptionTotals[$subName] = ['costCsp' => 0, 'costMosp' => 0, 'count' => 0];
        }
        $subscriptionTotals[$subName]['costCsp'] += $row['costCsp'] ?? 0;
        $subscriptionTotals[$subName]['costMosp'] += $row['costMosp'] ?? 0;
        $subscriptionTotals[$subName]['count']++;
    }
    arsort($subscriptionTotals);
    
    if (!empty($subscriptionTotals)) {
        $subTotal = count($subscriptionTotals);
        $bySubscriptionText = "\n=== CUSTO POR ASSINATURA (TODAS as {$subTotal} assinaturas, ordenadas por custo CSP) ===\n";
        $cap = 60; $i = 0; $truncated = 0;
        foreach ($subscriptionTotals as $sub => $data) {
            if ($i++ >= $cap) { $truncated++; continue; }
            $bySubscriptionText .= "- {$sub}: USD " . number_format($data['costCsp'], 2) . " (CSP) | USD " . number_format($data['costMosp'], 2) . " (MOSP) | {$data['count']} itens\n";
        }
        if ($truncated > 0) $bySubscriptionText .= "(+{$truncated} assinaturas adicionais com valores menores)\n";
    }

    // MeterIDs por Região
    $byRegionText = "";
    $regionData = [];
    foreach ($results as $row) {
        $region = $row['resourceLocation'] ?? 'Sem região';
        $meterId = $row['meterId'] ?? '';
        $meterName = $row['meterName'] ?? '';
        $meterCategory = $row['meterCategory'] ?? '';
        $resourceName = $row['resourceName'] ?? '';
        $costCsp = $row['costCsp'] ?? 0;
        
        if (!isset($regionData[$region])) {
            $regionData[$region] = ['totalCost' => 0, 'count' => 0, 'meters' => []];
        }
        $regionData[$region]['totalCost'] += $costCsp;
        $regionData[$region]['count']++;
        
        // Armazenar MeterIDs únicos por região (limitar a 5 por região para contexto)
        $meterKey = $meterId ?: $meterName;
        if ($meterKey && !isset($regionData[$region]['meters'][$meterKey]) && count($regionData[$region]['meters']) < 5) {
            $regionData[$region]['meters'][$meterKey] = [
                'meterId' => $meterId,
                'meterName' => $meterName,
                'meterCategory' => $meterCategory,
                'resourceName' => $resourceName,
                'cost' => $costCsp
            ];
        } elseif ($meterKey && isset($regionData[$region]['meters'][$meterKey])) {
            $regionData[$region]['meters'][$meterKey]['cost'] += $costCsp;
        }
    }
    
    // Ordenar regiões por custo
    uasort($regionData, function($a, $b) { return $b['totalCost'] <=> $a['totalCost']; });
    
    if (!empty($regionData)) {
        $regTotal = count($regionData);
        $byRegionText = "\n=== CUSTO POR REGIÃO (TODAS as {$regTotal} regiões, ordenadas por custo CSP) ===\n";
        $regCap = 30; $regI = 0; $regTrunc = 0;
        foreach ($regionData as $region => $data) {
            if ($regI++ >= $regCap) { $regTrunc++; continue; }
            $byRegionText .= "- {$region}: USD " . number_format($data['totalCost'], 2) . " ({$data['count']} linhas)\n";
        }
        if ($regTrunc > 0) $byRegionText .= "(+{$regTrunc} regiões adicionais com valores menores)\n";
    }

    // Detalhes dos recursos (amostra para exemplos - NÃO usar como fonte única)
    $sampleCap = 30;
    $totalRowsActual = count($results);
    $detailsText = "\n=== AMOSTRA DE LINHAS (apenas {$sampleCap} de {$totalRowsActual} - USE APENAS PARA EXEMPLOS, NÃO para totais) ===\n";
    $detailsText .= "Recurso | Serviço | Resource Group | Região | MeterID | Custo CSP\n";
    $detailsText .= str_repeat("-", 100) . "\n";
    $sampleCount = 0;
    foreach ($results as $row) {
        if ($sampleCount++ >= $sampleCap) break;
        $resourceName = substr($row['resourceName'] ?? '-', 0, 25);
        $service = substr($row['meterCategory'] ?? $row['serviceFamily'] ?? '-', 0, 20);
        $rg = substr($row['resourceGroup'] ?? '-', 0, 15);
        $region = $row['resourceLocation'] ?? '-';
        $meterId = $row['meterId'] ?? '-';
        $cost = number_format($row['costCsp'] ?? 0, 2);
        $detailsText .= "{$resourceName} | {$service} | {$rg} | {$region} | {$meterId} | USD {$cost}\n";
    }

    // Análise técnica de migração
    if (!empty($financialResults['technicalAnalysis'])) {
        $tech = $financialResults['technicalAnalysis'];
        $techSum = $tech['summary'] ?? [];
        $technicalSummary = "
=== ANÁLISE TÉCNICA DE MIGRAÇÃO ===
- Total de tipos de recursos: " . ($techSum['total'] ?? 0) . "
- Migráveis: " . ($techSum['movable'] ?? 0) . " (" . ($techSum['movablePercent'] ?? 0) . "%)
- Com restrições: " . ($techSum['movableWithRestrictions'] ?? 0) . " (" . ($techSum['movableWithRestrictionsPercent'] ?? 0) . "%)
- Não migráveis: " . ($techSum['notMovable'] ?? 0) . " (" . ($techSum['notMovablePercent'] ?? 0) . "%)
- Desconhecidos: " . ($techSum['unknown'] ?? 0) . " (" . ($techSum['unknownPercent'] ?? 0) . "%)
";

        // Listar recursos por status
        if (!empty($tech['results'])) {
            $movableList = [];
            $restrictedList = [];
            $notMovableList = [];
            
            foreach ($tech['results'] as $res) {
                $type = $res['resourceType'] ?? '';
                $status = $res['status'] ?? '';
                $notes = $res['notes'] ?? '';
                
                switch ($status) {
                    case 'movable':
                        $movableList[] = "  • {$type}";
                        break;
                    case 'movable-with-restrictions':
                        $restrictedList[] = "  • {$type}" . ($notes ? " ({$notes})" : "");
                        break;
                    case 'not-movable':
                        $notMovableList[] = "  • {$type}" . ($notes ? " ({$notes})" : "");
                        break;
                }
            }
            
            if ($notMovableList) {
                $technicalSummary .= "\nRecursos NÃO MIGRÁVEIS:\n" . implode("\n", array_slice($notMovableList, 0, 15));
            }
            if ($restrictedList) {
                $technicalSummary .= "\n\nRecursos COM RESTRIÇÕES:\n" . implode("\n", array_slice($restrictedList, 0, 15));
            }
        }
    }
}

// Contexto adicional enviado pelo cliente (filtros aplicados, seleções, etc.)
$additionalContext = "";
if (!empty($resourceContext)) {
    $additionalContext = "\n=== CONTEXTO DO USUÁRIO ===\n";
    if (!empty($resourceContext['selectedService'])) {
        $additionalContext .= "Serviço selecionado: {$resourceContext['selectedService']}\n";
    }
    if (!empty($resourceContext['selectedResourceGroup'])) {
        $additionalContext .= "Resource Group selecionado: {$resourceContext['selectedResourceGroup']}\n";
    }
    if (!empty($resourceContext['selectedSubscription'])) {
        $additionalContext .= "Assinatura selecionada: {$resourceContext['selectedSubscription']}\n";
    }
    if (!empty($resourceContext['visibleRows'])) {
        $additionalContext .= "Linhas visíveis após filtro: {$resourceContext['visibleRows']}\n";
    }
    if (!empty($resourceContext['totalFiltered'])) {
        $additionalContext .= "Valor total filtrado (CSP): USD " . number_format($resourceContext['totalFiltered'], 2) . "\n";
    }
}

// System prompt para o assistente
$systemPrompt = "
Você é um **Consultor Especialista em Azure** da TD SYNNEX, focado em análise de recursos, custos e migração de ambientes Microsoft Azure.

Sua missão é ajudar os usuários a:
1. **Entender os custos** dos recursos Azure (MOSP vs CSP)
2. **Analisar a viabilidade de migração** de recursos entre assinaturas, resource groups ou regiões
3. **Identificar oportunidades de otimização** de custos
4. **Explicar diferenças de preços** entre modelos de licenciamento
5. **Recomendar estratégias** de migração e organização de recursos

=== DADOS DA ANÁLISE ATUAL (FONTE DE VERDADE) ===
{$resourceSummary}
{$byServiceText}
{$byResourceGroupText}
{$bySubscriptionText}
{$byRegionText}
{$detailsText}
{$technicalSummary}
{$additionalContext}

=== REGRAS CRÍTICAS DE EXATIDÃO (OBRIGATÓRIAS) ===
⛔ NÃO INVENTE valores, nomes de recursos, MeterIDs, regiões ou números. Use APENAS o que está no contexto acima.
⛔ Os blocos rotulados 'TODOS os X' são COMPLETOS — se um serviço/RG/assinatura não está listado lá, ele NÃO existe nesta análise.
⛔ A seção 'AMOSTRA DE LINHAS' é apenas uma amostra (30 de {$totalRowsActual}). NÃO use ela para calcular totais nem para afirmar que algo não existe.
⛔ Para totais, use SEMPRE os valores do bloco 'RESUMO DA ANÁLISE ATUAL' (totalRows, totalMosp, totalCsp).
⛔ Se a pergunta exigir um dado que NÃO está no contexto, responda explicitamente: 'Essa informação não está disponível na análise carregada.' — NÃO chute, NÃO preencha com conhecimento genérico do Azure como se fosse o ambiente do cliente.
⛔ Ao citar números, copie EXATAMENTE o valor mostrado no contexto (mesmo formato, mesma quantidade de casas).
⛔ Quando listar 'os top N' de algo, ordene pelos valores do contexto e cite literalmente os primeiros N — nada além disso.

=== CONHECIMENTO TÉCNICO (genérico Azure - use SÓ para EXPLICAÇÕES conceituais) ===

**Modelos de Licenciamento Azure:**
- **MOSP (Microsoft Online Subscription Program)**: Preços de varejo (pay-as-you-go direto com Microsoft)
- **CSP (Cloud Solution Provider)**: Preços através de parceiros, geralmente com desconto em relação ao MOSP
- **EA (Enterprise Agreement)**: Contratos corporativos com descontos por volume
- **MPA (Microsoft Partner Agreement)**: Modelo para parceiros CSP

**Migração de Recursos Azure:**
- Nem todos os recursos podem ser movidos entre Resource Groups ou Assinaturas
- Alguns recursos têm restrições (ex: dependências, configurações regionais)
- A movimentação entre regiões geralmente requer recriação do recurso

=== INSTRUÇÕES DE FORMATO ===
- Responda SEMPRE em português brasileiro.
- Responda APENAS sobre Azure, custos, migração e recursos relacionados. Para temas fora disso, redirecione educadamente.
- Use tabelas Markdown ao comparar valores ou listar recursos. Inclua sempre cabeçalho.
- Seja conciso e objetivo. Use bullets quando listar mais de 3 itens.
- Valores monetários: formato USD 1.234,56 (ou conforme aparecem no contexto).
- Se não houver dados carregados (totalRows = 0), informe que o usuário precisa fazer upload de um arquivo CSV primeiro.
- Você está ajudando um parceiro/revenda a entender os custos para seus clientes finais.
";

// Construir array de mensagens
$messages = [
    ["role" => "system", "content" => $systemPrompt]
];

// Adicionar histórico
foreach ($history as $msg) {
    $role = isset($msg['role']) ? $msg['role'] : (isset($msg['isUser']) && $msg['isUser'] ? 'user' : 'assistant');
    $content = $msg['content'] ?? $msg['text'] ?? '';
    if ($content) {
        $messages[] = ["role" => $role, "content" => $content];
    }
}

// Adicionar mensagem atual
$messages[] = ["role" => "user", "content" => $userMessage];

// Enviar para Azure OpenAI
$payload = [
    "messages"    => $messages,
    "temperature" => 0.2,   // baixa para reduzir alucinação
    "max_tokens"  => 2000,
    "top_p"       => 0.9,
];

$ch = curl_init($foundryEndpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "api-key: {$foundryApiKey}"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 60,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(["reply" => "Erro de conexão: " . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    $errorData = json_decode($response, true);
    $errorMsg = $errorData['error']['message'] ?? "Erro HTTP {$httpCode}";
    echo json_encode(["reply" => "Erro na API: " . $errorMsg]);
    exit;
}

$data = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? "Desculpe, não consegui processar sua solicitação.";

echo json_encode([
    "reply" => $reply,
    "hasData" => $financialResults !== null,
    "summary" => $financialResults ? [
        "totalRows" => $financialResults['summary']['totalRows'] ?? 0,
        "totalCsp" => $financialResults['summary']['totalCsp'] ?? 0,
    ] : null
]);
