<?php
/**
 * Partner Recommendation API
 * Gera recomendações personalizadas com IA para cada pilar do PCS
 * 
 * Analisa:
 * - Gaps de pontuação em cada pilar
 * - Certificações do time vs pontuação reportada (possíveis gaps)
 * - Ações priorizadas para alcançar Solutions Partner
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ============================================================================
// CONFIGURAÇÕES DO AZURE OPENAI
// ============================================================================
$foundryEndpoint = "https://azopenai002026.openai.azure.com/openai/deployments/gpt-4o/chat/completions?api-version=2024-02-15-preview"; 
$foundryApiKey   = "4IVuw4zEn9P9owIhcGqUux5o72MI3hYLk8BKa1dyyT9Bxfr096JEJQQJ99CCACYeBjFXJ3w3AAABACOG01vw";
// ============================================================================

$input = json_decode(file_get_contents("php://input"), true);

// Dados do parceiro
$partnerData = $input['partnerData'] ?? [];
$companyName = $partnerData['companyName'] ?? 'Parceiro';
$solutionArea = $partnerData['selectedSolutionArea'] ?? '';
$pcsPerformance = (float)($partnerData['pcsPerformance'] ?? 0);
$pcsSkilling = (float)($partnerData['pcsSkilling'] ?? 0);
$pcsCustomerSuccess = (float)($partnerData['pcsCustomerSuccess'] ?? 0);
$certifications = $partnerData['certifications'] ?? [];
$teamCertCount = (int)($partnerData['teamCertCount'] ?? 0);

// Calcular total e gaps
$totalScore = $pcsPerformance + $pcsSkilling + $pcsCustomerSuccess;
$gapToQualify = max(0, 70 - $totalScore);
$isQualified = $totalScore >= 70 && $pcsPerformance > 0 && $pcsSkilling > 0 && $pcsCustomerSuccess > 0;

// Máximos por área
$maxScores = [
    'Modern Work' => ['performance' => 20, 'skilling' => 25, 'customerSuccess' => 55],
    'Infrastructure' => ['performance' => 30, 'skilling' => 40, 'customerSuccess' => 30],
    'Security' => ['performance' => 20, 'skilling' => 20, 'customerSuccess' => 60],
    'Data & AI' => ['performance' => 30, 'skilling' => 40, 'customerSuccess' => 30],
    'Digital & App Innovation' => ['performance' => 30, 'skilling' => 40, 'customerSuccess' => 30],
    'Business Applications' => ['performance' => 15, 'skilling' => 35, 'customerSuccess' => 50]
];

$areaMax = $maxScores[$solutionArea] ?? $maxScores['Infrastructure'];

// Certificações por área
$certsByArea = [
    'Infrastructure' => [
        'intermediate' => ['AZ-104', 'AZ-700', 'AZ-800'],
        'advanced' => ['AZ-305', 'AZ-140', 'AZ-120'],
        'minForPoints' => 2
    ],
    'Data & AI' => [
        'intermediate' => ['AZ-104', 'AZ-305', 'DP-300', 'AI-102', 'DP-100', 'DP-203', 'PL-300', 'DP-600', 'DP-700'],
        'advanced' => [],
        'minForPoints' => 4
    ],
    'Security' => [
        'intermediate' => ['AZ-500', 'SC-200', 'SC-100', 'SC-300', 'SC-400'],
        'advanced' => [],
        'minForPoints' => 2
    ],
    'Modern Work' => [
        'intermediate' => ['MS-900', 'MD-102', 'MS-700', 'MS-721', 'SC-300'],
        'advanced' => ['MS-102'],
        'minForPoints' => 2
    ],
    'Digital & App Innovation' => [
        'intermediate' => ['AZ-104', 'AZ-204', 'PL-400'],
        'advanced' => ['AZ-305', 'AZ-400', 'PL-600'],
        'minForPoints' => 2
    ],
    'Business Applications' => [
        'intermediate' => ['MB-210', 'MB-220', 'MB-230', 'MB-800', 'PL-200', 'PL-400', 'PL-300'],
        'advanced' => ['MB-335', 'MB-700', 'PL-600', 'MB-820'],
        'minForPoints' => 5
    ]
];

$areaCerts = $certsByArea[$solutionArea] ?? $certsByArea['Infrastructure'];

// Detectar possível gap de certificações
$certGapAnalysis = "";
if ($teamCertCount > 0 && $pcsSkilling == 0) {
    $certGapAnalysis = "ALERTA: O parceiro reportou {$teamCertCount} pessoas certificadas, mas tem 0 pontos em Skilling. Possíveis causas: certificações não estão vinculadas ao Partner Center, certificações expiradas, ou certificações que não contam para esta Solution Area.";
} elseif ($teamCertCount > 0 && $pcsSkilling < ($areaMax['skilling'] * 0.3)) {
    $expectedSkilling = min($areaMax['skilling'], $teamCertCount * 5);
    if ($pcsSkilling < $expectedSkilling * 0.5) {
        $certGapAnalysis = "POSSIVEL GAP: O parceiro tem {$teamCertCount} pessoas certificadas mas apenas {$pcsSkilling} pontos em Skilling (esperado aprox. {$expectedSkilling}). Verificar se todas as certificações estão corretamente associadas no Partner Center.";
    }
}

// System prompt para a IA
$systemPrompt = "
Você é um Consultor Sênior de Partner Center da Microsoft, especializado em ajudar parceiros a alcançarem a designação Solutions Partner.

DADOS DO PARCEIRO:
- Empresa: {$companyName}
- Solution Area: {$solutionArea}
- PCS Performance: {$pcsPerformance}/{$areaMax['performance']} pontos
- PCS Skilling: {$pcsSkilling}/{$areaMax['skilling']} pontos  
- PCS Customer Success: {$pcsCustomerSuccess}/{$areaMax['customerSuccess']} pontos
- TOTAL: {$totalScore}/100 pontos
- Gap para qualificação: {$gapToQualify} pontos
- Pessoas certificadas reportadas: {$teamCertCount}
- Status: " . ($isQualified ? "QUALIFICADO" : "NÃO QUALIFICADO") . "

{$certGapAnalysis}

CERTIFICAÇÕES RELEVANTES PARA {$solutionArea}:
- Intermediate: " . implode(', ', $areaCerts['intermediate']) . "
- Advanced: " . implode(', ', $areaCerts['advanced'] ?: ['N/A']) . "
- Mínimo para pontuação: {$areaCerts['minForPoints']} certificações

REGRAS CRÍTICAS:
1. Para qualificar como Solutions Partner: Total >= 70 E TODOS os pilares > 0
2. Se qualquer pilar = 0, parceiro NÃO qualifica mesmo com 100 pontos total
3. Skilling é o pilar mais controlável pelo parceiro
4. Performance depende de net customer adds (novos clientes com workloads)
5. Customer Success depende de usage growth e deployments ativos

Gere recomendações em formato JSON estruturado conforme solicitado.
Seja específico, prático e orientado a ações.
Todas as respostas em português do Brasil.
";

$userPrompt = "
Gere um relatório de recomendações estruturado em JSON com o seguinte formato:

{
  \"summary\": {
    \"status\": \"qualified|not_qualified\",
    \"totalScore\": number,
    \"gapToQualify\": number,
    \"headline\": \"Frase resumo do status\",
    \"mainRecommendation\": \"Principal ação recomendada\"
  },
  \"pillarAnalysis\": {
    \"performance\": {
      \"score\": number,
      \"maxScore\": number,
      \"percentage\": number,
      \"status\": \"critical|warning|good|excellent\",
      \"gap\": number,
      \"recommendations\": [\"Recomendação 1\", \"Recomendação 2\", \"Recomendação 3\"],
      \"actions\": [
        {\"action\": \"Ação específica\", \"impact\": \"alto|médio|baixo\", \"timeframe\": \"X semanas\"}
      ]
    },
    \"skilling\": {
      \"score\": number,
      \"maxScore\": number,
      \"percentage\": number,
      \"status\": \"critical|warning|good|excellent\",
      \"gap\": number,
      \"certificationGap\": \"Análise de gap de certificações se houver\",
      \"recommendations\": [\"Recomendação 1\", \"Recomendação 2\", \"Recomendação 3\"],
      \"requiredCertifications\": [\"Cert 1\", \"Cert 2\"],
      \"actions\": [
        {\"action\": \"Ação específica\", \"impact\": \"alto|médio|baixo\", \"timeframe\": \"X semanas\"}
      ]
    },
    \"customerSuccess\": {
      \"score\": number,
      \"maxScore\": number,
      \"percentage\": number,
      \"status\": \"critical|warning|good|excellent\",
      \"gap\": number,
      \"recommendations\": [\"Recomendação 1\", \"Recomendação 2\", \"Recomendação 3\"],
      \"actions\": [
        {\"action\": \"Ação específica\", \"impact\": \"alto|médio|baixo\", \"timeframe\": \"X semanas\"}
      ]
    }
  },
  \"certificationAnalysis\": {
    \"hasGap\": boolean,
    \"gapDescription\": \"Descrição do gap se houver\",
    \"possibleCauses\": [\"Causa 1\", \"Causa 2\"],
    \"resolutionSteps\": [\"Passo 1\", \"Passo 2\"]
  },
  \"roadmap\": {
    \"estimatedMonths\": number,
    \"phases\": [
      {
        \"phase\": 1,
        \"title\": \"Título da fase\",
        \"duration\": \"X semanas\",
        \"focus\": \"Pilar de foco\",
        \"objectives\": [\"Objetivo 1\", \"Objetivo 2\"]
      }
    ]
  },
  \"quickWins\": [
    {\"title\": \"Ação rápida\", \"impact\": \"X pontos\", \"effort\": \"baixo|médio|alto\"}
  ]
}

Retorne APENAS o JSON válido, sem markdown ou texto adicional.
";

// Preparar mensagens
$messages = [
    ["role" => "system", "content" => $systemPrompt],
    ["role" => "user", "content" => $userPrompt]
];

// Chamar Azure OpenAI
$payload = [
    "messages" => $messages,
    "max_tokens" => 3000,
    "temperature" => 0.7
];

$ch = curl_init($foundryEndpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "api-key: " . $foundryApiKey
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode([
        "success" => false,
        "error" => "Erro de conexão: " . $curlError
    ]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode([
        "success" => false,
        "error" => "Erro da API: HTTP " . $httpCode,
        "details" => $response
    ]);
    exit;
}

$data = json_decode($response, true);
$aiResponse = $data["choices"][0]["message"]["content"] ?? "";

// Tentar parsear o JSON da resposta
$recommendations = null;
try {
    // Limpar possíveis marcadores de código
    $cleanJson = preg_replace('/^```json\s*/', '', $aiResponse);
    $cleanJson = preg_replace('/\s*```$/', '', $cleanJson);
    $cleanJson = trim($cleanJson);
    
    $recommendations = json_decode($cleanJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON inválido: " . json_last_error_msg());
    }
} catch (Exception $e) {
    // Se falhar, retornar a resposta raw
    echo json_encode([
        "success" => true,
        "parsed" => false,
        "rawResponse" => $aiResponse,
        "partnerData" => [
            "companyName" => $companyName,
            "solutionArea" => $solutionArea,
            "scores" => [
                "performance" => $pcsPerformance,
                "skilling" => $pcsSkilling,
                "customerSuccess" => $pcsCustomerSuccess,
                "total" => $totalScore
            ],
            "maxScores" => $areaMax,
            "isQualified" => $isQualified,
            "gapToQualify" => $gapToQualify
        ]
    ]);
    exit;
}

// Adicionar dados do parceiro à resposta
$recommendations['partnerData'] = [
    "companyName" => $companyName,
    "solutionArea" => $solutionArea,
    "scores" => [
        "performance" => $pcsPerformance,
        "skilling" => $pcsSkilling,
        "customerSuccess" => $pcsCustomerSuccess,
        "total" => $totalScore
    ],
    "maxScores" => $areaMax,
    "isQualified" => $isQualified,
    "gapToQualify" => $gapToQualify,
    "teamCertCount" => $teamCertCount
];

echo json_encode([
    "success" => true,
    "parsed" => true,
    "recommendations" => $recommendations
]);
