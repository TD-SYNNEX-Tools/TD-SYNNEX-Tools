<?php
/**
 * Partner Center Chat API
 * IA Especialista em Microsoft AI Cloud Partner Program (MAICPP)
 * 
 * @package     CloudPartnerHub
 * @author      TD SYNNEX Brasil
 * @version     2.0.0
 * 
 * Endpoints:
 *   POST /partner-chat-api.php
 *   
 * Request Body:
 *   {
 *     "message": "string",
 *     "history": [{ "role": "user|assistant", "content": "string" }],
 *     "partnerContext": { ... }
 *   }
 * 
 * Response:
 *   {
 *     "success": true,
 *     "reply": "string",
 *     "usage": { "prompt_tokens": int, "completion_tokens": int }
 *   }
 */

declare(strict_types=1);

// ============================================================================
// CONFIGURACAO
// ============================================================================

const AZURE_OPENAI_ENDPOINT = 'https://azopenai002026.openai.azure.com/openai/deployments/gpt-4o/chat/completions?api-version=2024-02-15-preview';
const AZURE_OPENAI_API_KEY  = '4IVuw4zEn9P9owIhcGqUux5o72MI3hYLk8BKa1dyyT9Bxfr096JEJQQJ99CCACYeBjFXJ3w3AAABACOG01vw';
const MAX_HISTORY_MESSAGES  = 10;
const REQUEST_TIMEOUT       = 60;

// ============================================================================
// HEADERS CORS E JSON
// ============================================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ============================================================================
// FUNCOES AUXILIARES
// ============================================================================

/**
 * Envia resposta JSON padronizada
 */
function jsonResponse(array $data, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Constroi o contexto do parceiro para o prompt
 */
function buildPartnerContext(?array $ctx): string
{
    if (!$ctx) {
        return '';
    }

    $totalPcs = ($ctx['pcsPerformance'] ?? 0) + ($ctx['pcsSkilling'] ?? 0) + ($ctx['pcsCustomerSuccess'] ?? 0);

    return "
=== CONTEXTO DO PARCEIRO ===
Empresa: {$ctx['companyName']} 
Solution Area: {$ctx['selectedSolutionArea']}
TD SYNNEX: " . ($ctx['isTdSynnexRegistered'] ? 'Sim' : 'Nao') . "
Microsoft Partner: " . ($ctx['isMicrosoftPartner'] ? 'Sim' : 'Nao') . "
MPN ID: {$ctx['mpnId']}

PCS ATUAL:
- Performance: {$ctx['pcsPerformance']} pts
- Skilling: {$ctx['pcsSkilling']} pts
- Customer Success: {$ctx['pcsCustomerSuccess']} pts
- TOTAL: {$totalPcs}/100

DADOS ADICIONAIS:
- Receita CSP: {$ctx['cspRevenue']}
- Clientes: {$ctx['clientCount']}
";
}

/**
 * Retorna o System Prompt completo
 */
function getSystemPrompt(string $partnerContext): string
{
    return <<<PROMPT
Voce e um Consultor Especialista em Microsoft AI Cloud Partner Program (MAICPP) da TD SYNNEX Brasil.
Sua missao e ajudar parceiros a alcancarem a designacao Solutions Partner.

{$partnerContext}

## REGRAS DE COMPORTAMENTO
1. Seja direto, pratico e orientado a acoes
2. Use dados do parceiro quando disponiveis
3. Base suas recomendacoes nas regras oficiais Microsoft
4. Responda em portugues do Brasil
5. Nao invente numeros - use apenas dados oficiais

## PARTNER CAPABILITY SCORE (PCS)

### Requisitos Solutions Partner
- Total >= 70 pontos
- TODOS os pilares > 0 (Performance, Skilling, Customer Success)
- Pilar zerado = nao qualifica

### Estrutura
| Metrica | Descricao |
|---------|-----------|
| Performance | Net customer adds com workloads qualificados |
| Skilling | Certificacoes do time (Intermediate + Advanced) |
| Customer Success | Usage growth + deployments ativos |

### Pontuacao Maxima por Area
| Area | Performance | Skilling | Customer Success |
|------|-------------|----------|------------------|
| Modern Work | 20 | 25 | 55 |
| Infrastructure | 30 | 40 | 30 |
| Security | 20 | 20 | 60 |
| Data & AI | 30 | 40 | 30 |
| Digital & App Innovation | 30 | 40 | 30 |
| Business Applications | 15 | 35 | 50 |

## CERTIFICACOES POR AREA

### Infrastructure (Azure)
- Intermediate: AZ-104, AZ-700, AZ-800
- Advanced: AZ-305, AZ-140, AZ-120

### Data & AI
- Intermediate: AZ-104, DP-300, AI-102, DP-100, DP-203, DP-600, DP-700
- Advanced: AZ-305

### Security
- Obrigatorio: AZ-500 > SC-200 > SC-100/SC-300/SC-400

### Modern Work
- Intermediate: MS-900, MD-102, MS-700, MS-721, SC-300
- Advanced: MS-102

### Digital & App Innovation
- Intermediate: AZ-104, AZ-204, PL-400
- Advanced: AZ-305, AZ-400, PL-600

### Business Applications
- Intermediate: MB-210, MB-220, MB-230, MB-800, PL-200, PL-300, PL-400
- Advanced: MB-335, MB-700, PL-600

## TRILHAS
- **Enterprise**: Clientes maiores, mais certificacoes, thresholds altos
- **SMB**: PMEs, menos certificacoes, thresholds acessiveis

## BENEFICIOS SOLUTIONS PARTNER
- Partner Cloud Support: 50 incidentes/ano
- Visual Studio Enterprise: 25 licencas
- GitHub Enterprise Cloud: 10 licencas
- Azure Bulk Credits anuais
- Co-sell Ready Status

## ESTRATEGIAS DE ACELERACAO
1. **Skilling**: Bootcamps TD SYNNEX, Microsoft Learn, foco em pre-requisitos
2. **Performance**: Campanhas TD SYNNEX, migracao de clientes existentes
3. **Customer Success**: Programa de adocao, monitoramento mensal de usage

## RECURSOS TD SYNNEX
- Cloud Week (treinamentos intensivos)
- Sales Bootcamp (capacitacao comercial)
- PDM (consultoria 1:1)
- Deal Registration

Seja encorajador mas realista. Crie planos factíveis.
PROMPT;
}

/**
 * Chama a API Azure OpenAI
 */
function callAzureOpenAI(array $messages): array
{
    $payload = [
        'messages'          => $messages,
        'temperature'       => 0.7,
        'max_tokens'        => 1500,
        'top_p'             => 0.95,
        'frequency_penalty' => 0,
        'presence_penalty'  => 0
    ];

    $ch = curl_init(AZURE_OPENAI_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'api-key: ' . AZURE_OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => REQUEST_TIMEOUT
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'response'  => $response,
        'httpCode'  => $httpCode,
        'curlError' => $curlError
    ];
}

// ============================================================================
// PROCESSAMENTO DA REQUISICAO
// ============================================================================

// Validar metodo
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success' => false,
        'error'   => 'Metodo nao permitido. Use POST.'
    ], 405);
}

// Ler e validar input
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse([
        'success' => false,
        'error'   => 'JSON invalido no corpo da requisicao.'
    ], 400);
}

$userMessage    = trim($input['message'] ?? '');
$history        = $input['history'] ?? [];
$partnerContext = $input['partnerContext'] ?? null;

if (empty($userMessage)) {
    jsonResponse([
        'success' => false,
        'error'   => 'Mensagem vazia. Envie uma pergunta.'
    ], 400);
}

// Montar mensagens
$messages = [
    [
        'role'    => 'system',
        'content' => getSystemPrompt(buildPartnerContext($partnerContext))
    ]
];

// Adicionar historico (limitado)
$recentHistory = array_slice($history, -MAX_HISTORY_MESSAGES);
foreach ($recentHistory as $msg) {
    if (isset($msg['role'], $msg['content'])) {
        $messages[] = [
            'role'    => $msg['role'],
            'content' => $msg['content']
        ];
    }
}

// Adicionar mensagem atual
$messages[] = ['role' => 'user', 'content' => $userMessage];

// Chamar API
$result = callAzureOpenAI($messages);

// Tratar erros de conexao
if ($result['curlError']) {
    jsonResponse([
        'success' => false,
        'error'   => 'Erro de conexao com o servico de IA.',
        'details' => $result['curlError']
    ], 503);
}

// Tratar erros HTTP
if ($result['httpCode'] !== 200) {
    $errorData = json_decode($result['response'], true);
    jsonResponse([
        'success' => false,
        'error'   => 'Servico temporariamente indisponivel.',
        'details' => $errorData['error']['message'] ?? "HTTP {$result['httpCode']}"
    ], $result['httpCode']);
}

// Processar resposta
$data = json_decode($result['response'], true);
$reply = $data['choices'][0]['message']['content'] ?? 'Nao foi possivel processar sua pergunta.';

// Retornar sucesso
jsonResponse([
    'success' => true,
    'reply'   => $reply,
    'usage'   => $data['usage'] ?? null
]);
