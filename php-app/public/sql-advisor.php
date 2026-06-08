<?php
// filepath: public/sql-advisor.php
session_start();

// Governança T2: valida token interno (no-op em desenvolvimento)
require_once __DIR__ . '/../src/Shared/Services/governance-guard.php';

// Initialize i18n
require_once __DIR__ . '/../src/Shared/Services/i18n-bootstrap.php';

require_once __DIR__ . '/../vendor/autoload.php';
use App\Features\SqlLicensing\Services\LicensingAdvisor;
use App\Shared\Services\GovernanceClient;

// Lê preços do skus.json
$jsonFile = __DIR__ . '/skus.json';
$prices = [];
if (file_exists($jsonFile)) {
    $prices = json_decode(file_get_contents($jsonFile), true) ?: [];
}
// Salva/Sincroniza na sessão
$_SESSION['prices'] = $prices;

// Inicializa sessão do advisor com defaults
if (!isset($_SESSION['advisor'])) {
    $_SESSION['advisor'] = [
        'clientName'        => '',
        'vendorName'        => '',
        'vCores'            => 4,
        'edition'           => 'Standard',
        'hoursPerMonth'     => 730,
        'currency'          => 'USD',
        'amortizationYears' => 3,
        'hasSA'             => false,
    ];
}

$advisorResult = null;

// ====== AJAX REQUEST: retorna JSON sem recarregar a página ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_advisor']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json; charset=utf-8');

    $advisorParams = [
        'clientName'        => filter_input(INPUT_POST, 'clientName', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '',
        'vendorName'        => filter_input(INPUT_POST, 'vendorName', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '',
        'vCores'            => filter_input(INPUT_POST, 'vCores', FILTER_VALIDATE_INT) ?: 4,
        'edition'           => filter_input(INPUT_POST, 'edition', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'Standard',
        'hoursPerMonth'     => filter_input(INPUT_POST, 'hoursPerMonth', FILTER_VALIDATE_INT) ?: 730,
        'currency'          => filter_input(INPUT_POST, 'currency', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'USD',
        'amortizationYears' => 3,
        'hasSA'             => isset($_POST['hasSA']),
    ];
    if ($advisorParams['vCores'] < 4) $advisorParams['vCores'] = 4;

    $_SESSION['advisor'] = $advisorParams;

    $advisor = new LicensingAdvisor($_SESSION['prices']);
    $result = $advisor->compareAll($advisorParams);
    $_SESSION['advisorResult'] = $result;

    // Governança T2: registra evento de uso (best-effort, no-op em DEV)
    GovernanceClient::fromGlobals()->recordUsage('sql');

    echo json_encode($result);
    exit;
}

// Processar formulário (fallback sem JS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_advisor'])) {
    $advisorParams = [
        'clientName'        => filter_input(INPUT_POST, 'clientName', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '',
        'vendorName'        => filter_input(INPUT_POST, 'vendorName', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '',
        'vCores'            => filter_input(INPUT_POST, 'vCores', FILTER_VALIDATE_INT) ?: 4,
        'edition'           => filter_input(INPUT_POST, 'edition', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'Standard',
        'hoursPerMonth'     => filter_input(INPUT_POST, 'hoursPerMonth', FILTER_VALIDATE_INT) ?: 730,
        'currency'          => filter_input(INPUT_POST, 'currency', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'USD',
        'amortizationYears' => 3,
        'hasSA'             => isset($_POST['hasSA']),
    ];

    if ($advisorParams['vCores'] < 4) {
        $advisorParams['vCores'] = 4;
    }

    $_SESSION['advisor'] = $advisorParams;

    $advisor = new LicensingAdvisor($_SESSION['prices']);
    $advisorResult = $advisor->compareAll($advisorParams);
    $_SESSION['advisorResult'] = $advisorResult;

    // Governança T2: registra evento de uso (best-effort, no-op em DEV)
    GovernanceClient::fromGlobals()->recordUsage('sql');
}

// Salvar como proposta (governança T2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_proposal'])) {
    $gov = GovernanceClient::fromGlobals();
    $res = $_SESSION['advisorResult'] ?? null;
    if ($res && $gov->isEnabled()) {
        $p = $res['params'] ?? [];
        $best = $res['bestModel'] ?? '';
        $bestMonthly = isset($res[$best]['monthly']) ? (float)$res[$best]['monthly'] : 0.0;
        $clientName = trim((string)($_POST['customerName'] ?? $p['clientName'] ?? ''));
        $title = trim((string)($_POST['proposalTitle'] ?? ''));
        if ($title === '') {
            $title = 'SQL Licensing Advisor' . ($clientName !== '' ? ' - ' . $clientName : '');
        }
        $proposalId = $gov->createProposal([
            'analysisType'  => 'sql',
            'title'         => $title,
            'customerName'  => $clientName,
            'customerTaxId' => trim((string)($_POST['customerTaxId'] ?? '')),
            'totalValue'    => $bestMonthly,
            'resultSummary' => [
                'bestModel'   => $best,
                'bestMonthly' => $bestMonthly,
                'vCores'      => $p['vCores']  ?? 0,
                'edition'     => $p['edition'] ?? '',
                'currency'    => $p['currency'] ?? 'USD',
                'hasSA'       => $p['hasSA']   ?? false,
            ],
        ]);
        if ($proposalId !== null) {
            $json = json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
            $gov->uploadProposalFile(
                $proposalId,
                'sql_advisor_' . date('Y-m-d') . '.json',
                'application/json',
                $json
            );
            $_SESSION['proposalSaved'] = true;
        }
    }
    header('Location: sql-advisor.php');
    exit;
}

// Se já tem resultado em sessão e é GET, mostra
if (!$advisorResult && isset($_SESSION['advisorResult'])) {
    $advisorResult = $_SESSION['advisorResult'];
}

$adv = $_SESSION['advisor'];
$currencySymbol = ($adv['currency'] ?? 'USD') === 'BRL' ? 'R$' : '$';
?>
<!DOCTYPE html>
<html lang="<?= getHtmlLang() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= __('pages.sql_advisor') ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===== CHAT PANEL - REDESIGN MODERNO ===== */
    .chat-wrap {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1000;
      background: rgba(0,0,0,0);
      align-items: flex-end;
      justify-content: flex-end;
      padding: 0;
      pointer-events: none;
      transition: background 0.3s ease;
    }
    .chat-wrap.chat-open {
      display: flex;
      background: rgba(15,23,42,0.4);
      backdrop-filter: blur(4px);
      pointer-events: all;
      animation: fadeIn 0.25s ease;
    }
    /* Desktop: painel flutuante com visual premium */
    .chat-wrap.chat-open .chat-card {
      position: fixed;
      bottom: 0;
      right: 24px;
      width: 460px;
      height: 680px;
      max-height: calc(100vh - 32px);
      border-radius: 24px 24px 0 0;
      animation: slideUp 0.35s cubic-bezier(0.34,1.15,0.64,1);
    }
    .chat-card {
      display: flex;
      flex-direction: column;
      background: #ffffff;
      border-radius: 24px;
      border: none;
      box-shadow: 0 25px 80px rgba(0,0,0,0.25), 0 10px 30px rgba(0,87,88,0.15);
      overflow: hidden;
    }
    /* Botão fechar chat - redesign */
    #chatMobileClose {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 12px;
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.18);
      cursor: pointer;
      color: #fff;
      flex-shrink: 0;
      transition: all 0.2s ease;
    }
    #chatMobileClose:hover {
      background: rgba(255,255,255,0.22);
      transform: scale(1.05);
    }
    /* FAB - botão flutuante premium */
    #chatFab {
      display: flex;
      position: fixed;
      bottom: 24px;
      right: 24px;
      width: 64px;
      height: 64px;
      border-radius: 20px;
      background: linear-gradient(145deg, #005758 0%, #008b8b 50%, #00a5a5 100%);
      border: none;
      cursor: pointer;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 32px rgba(0,87,88,0.4), 0 4px 12px rgba(0,87,88,0.3), inset 0 1px 0 rgba(255,255,255,0.2);
      z-index: 999;
      transition: all 0.3s cubic-bezier(0.34,1.15,0.64,1);
    }
    #chatFab::before {
      content: '';
      position: absolute;
      inset: -3px;
      border-radius: 23px;
      background: linear-gradient(145deg, rgba(0,165,165,0.5), rgba(0,87,88,0.3));
      z-index: -1;
      opacity: 0;
      transition: opacity 0.3s;
    }
    #chatFab:hover {
      box-shadow: 0 12px 40px rgba(0,87,88,0.5), 0 6px 16px rgba(0,87,88,0.4);
      transform: translateY(-3px) scale(1.02);
    }
    #chatFab:hover::before { opacity: 1; }
    #chatFab:active { transform: translateY(0) scale(0.95); }
    #chatFab.chat-fab-hidden { display: none; }
    
    /* Chat Header - visual premium com gradiente */
    .chat-header {
      background: linear-gradient(145deg, #003d3d 0%, #005758 40%, #006b6b 100%);
      padding: 20px 24px;
      flex-shrink: 0;
      position: relative;
      overflow: hidden;
    }
    .chat-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -20%;
      width: 200px;
      height: 200px;
      background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
      border-radius: 50%;
    }
    .chat-header::after {
      content: '';
      position: absolute;
      bottom: -30%;
      left: -10%;
      width: 120px;
      height: 120px;
      background: radial-gradient(circle, rgba(0,200,200,0.15) 0%, transparent 70%);
      border-radius: 50%;
    }
    
    /* Área de mensagens */
    .chat-messages {
      flex: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 18px;
      padding: 24px;
      background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    }
    .chat-messages::-webkit-scrollbar { width: 5px; }
    .chat-messages::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #94a3b8, #cbd5e1); border-radius: 10px; }
    .chat-messages::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, #64748b, #94a3b8); }
    .chat-messages::-webkit-scrollbar-track { background: transparent; }
    
    /* Containers de mensagem */
    .msg-container {
      display: flex;
      flex-direction: column;
      max-width: 92%;
      animation: msgIn 0.3s cubic-bezier(0.34,1.15,0.64,1);
    }
    @keyframes msgIn { from { opacity: 0; transform: translateY(12px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes slideUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
    
    .msg-user { align-self: flex-end; align-items: flex-end; }
    .msg-assistant { align-self: flex-start; align-items: flex-start; }
    
    /* Balões de chat redesign */
    .chat-bubble-assistant {
      background: #ffffff;
      border: none;
      border-radius: 6px 20px 20px 20px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06), 0 1px 3px rgba(0,0,0,0.04);
      padding: 16px 20px;
      font-size: 0.9rem;
      color: #1e293b;
      line-height: 1.7;
    }
    .chat-bubble-user {
      background: linear-gradient(145deg, #005758 0%, #007a7c 100%);
      border-radius: 20px 6px 20px 20px;
      padding: 16px 20px;
      font-size: 0.9rem;
      color: #fff;
      line-height: 1.7;
      box-shadow: 0 4px 16px rgba(0,87,88,0.3), 0 2px 6px rgba(0,87,88,0.2);
    }
    
    /* Meta info */
    .msg-meta {
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .msg-user .msg-meta { color: #94a3b8; justify-content: flex-end; }
    .msg-assistant .msg-meta { color: #64748b; }
    
    /* Avatar IA premium */
    .ai-avatar {
      width: 26px;
      height: 26px;
      border-radius: 8px;
      background: linear-gradient(145deg, #005758, #008b8b);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 9px;
      font-weight: 800;
      color: white;
      letter-spacing: 0;
      flex-shrink: 0;
      box-shadow: 0 2px 8px rgba(0,87,88,0.3);
    }
    
    /* Área de input redesign */
    .chat-input-area {
      padding: 16px 20px 20px;
      background: #ffffff;
      border-top: 1px solid rgba(226,232,240,0.8);
      flex-shrink: 0;
    }
    .chat-input-wrap {
      display: flex;
      gap: 10px;
      align-items: center;
      background: #f8fafc;
      border: 2px solid #e2e8f0;
      border-radius: 16px;
      padding: 10px 10px 10px 18px;
      transition: all 0.25s ease;
    }
    .chat-input-wrap:focus-within {
      border-color: #005758;
      box-shadow: 0 0 0 4px rgba(0,87,88,0.1), 0 4px 12px rgba(0,87,88,0.08);
      background: #fff;
    }
    #chatInput {
      flex: 1;
      background: transparent;
      border: none;
      outline: none;
      font-size: 0.9rem;
      color: #1e293b;
      resize: none;
      min-height: 22px;
      max-height: 80px;
      padding: 4px 0;
      font-family: 'Inter', sans-serif;
    }
    #chatInput::placeholder { color: #94a3b8; }
    
    /* Botão enviar premium */
    .chat-send-btn {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      background: linear-gradient(145deg, #005758, #007a7c);
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: all 0.25s ease;
      box-shadow: 0 2px 8px rgba(0,87,88,0.3);
    }
    .chat-send-btn:hover {
      background: linear-gradient(145deg, #006b6b, #008b8b);
      transform: scale(1.05);
      box-shadow: 0 4px 12px rgba(0,87,88,0.4);
    }
    .chat-send-btn:active { transform: scale(0.95); }
    .chat-send-btn.is-stop { background: linear-gradient(145deg, #dc2626, #ef4444); }
    .chat-send-btn.is-stop:hover { background: linear-gradient(145deg, #b91c1c, #dc2626); }
    
    /* Cards de sugestão premium */
    .prompt-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 16px;
    }
    .prompt-card {
      background: linear-gradient(145deg, #f8fafc, #f1f5f9);
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 14px 16px;
      cursor: pointer;
      transition: all 0.25s cubic-bezier(0.34,1.15,0.64,1);
      text-align: left;
      position: relative;
      overflow: hidden;
    }
    .prompt-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, #005758, #00a5a5);
      opacity: 0;
      transition: opacity 0.25s;
    }
    .prompt-card:hover {
      background: linear-gradient(145deg, #e0f2f1, #ccfbf1);
      border-color: #5eead4;
      transform: translateY(-3px);
      box-shadow: 0 8px 24px rgba(0,87,88,0.12);
    }
    .prompt-card:hover::before { opacity: 1; }
    .prompt-card-icon {
      font-size: 1.3rem;
      margin-bottom: 6px;
      filter: drop-shadow(0 1px 2px rgba(0,0,0,0.1));
    }
    .prompt-card-text {
      font-size: 0.75rem;
      color: #475569;
      line-height: 1.4;
      font-weight: 500;
    }
    .prompt-card:hover .prompt-card-text { color: #005758; font-weight: 600; }
    
    /* Markdown content */
    .markdown-content code {
      background: linear-gradient(145deg, #f1f5f9, #e2e8f0);
      padding: 3px 8px;
      border-radius: 6px;
      font-size: 0.8em;
      font-family: 'Monaco', 'Menlo', monospace;
      color: #0f766e;
      border: 1px solid #e2e8f0;
    }
    .markdown-content strong { font-weight: 700; color: #0f172a; }
    .markdown-content a { color: #0078D4; text-decoration: none; border-bottom: 1px solid rgba(0,120,212,0.3); transition: border-color 0.2s; }
    .markdown-content a:hover { border-bottom-color: #0078D4; }
    .markdown-content ul, .markdown-content ol { padding-left: 1.3rem; margin-top: 10px; }
    .markdown-content li { margin-bottom: 6px; }
    .markdown-content h3 { font-size: 0.95rem; font-weight: 700; margin: 14px 0 8px; color: #005758; }
    .markdown-content h4 { font-size: 0.88rem; font-weight: 700; margin: 12px 0 6px; color: #334155; }
    .markdown-content hr { border: none; border-top: 1px solid #e2e8f0; margin: 14px 0; }
    .markdown-content table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 0.8rem; line-height: 1.5; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
    .markdown-content table th { background: linear-gradient(145deg, #005758, #006b6b); color: #fff; font-weight: 600; padding: 10px 12px; text-align: left; white-space: nowrap; }
    .markdown-content table th:first-child { border-radius: 0; }
    .markdown-content table th:last-child { border-radius: 0; }
    .markdown-content table td { padding: 9px 12px; border-bottom: 1px solid #e8edf3; color: #334155; background: #fff; }
    .markdown-content table tr:nth-child(even) td { background: #f8fafc; }
    .markdown-content table tr:hover td { background: #e0f2f1; }
    .markdown-content table td:first-child { font-weight: 600; color: #1e293b; }
    
    /* Typing animation */
    @keyframes bounceTyping { 0%,80%,100%{transform:translateY(0)} 40%{transform:translateY(-8px)} }
    .typing-dot {
      width: 8px;
      height: 8px;
      background: linear-gradient(145deg, #94a3b8, #64748b);
      border-radius: 50%;
      display: inline-block;
      margin: 0 3px;
      animation: bounceTyping 1.4s infinite ease-in-out;
    }
    .typing-dot:nth-child(1) { animation-delay: 0s; }
    .typing-dot:nth-child(2) { animation-delay: 0.15s; }
    .typing-dot:nth-child(3) { animation-delay: 0.3s; }
    
    /* ===== TABLE ===== */
    .advisor-table th { padding: 12px 16px; text-align: left; font-size: 0.85rem; white-space: nowrap; }
    .advisor-table td { padding: 12px 16px; font-size: 0.9rem; white-space: nowrap; }
    .recommended-row { background-color: #005758 !important; color: white !important; }
    .recommended-row td { color: white !important; border:none; }
    .savings-positive { color: #059669; font-weight: 600; }
    .savings-negative { color: #dc2626; font-weight: 600; }

    /* ===== ADVISOR FORM DESIGN SYSTEM ===== */
    .adv-form {
      background: #fff;
      border-radius: 16px;
      border: 1px solid #e8ecf1;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 24px rgba(0,0,0,0.03);
      overflow: hidden;
      margin-bottom: 1.5rem;
    }
    .adv-form-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 24px;
      border-bottom: 1px solid #eef1f6;
      background: #fcfcfd;
    }
    .adv-form-header-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .adv-form-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: linear-gradient(135deg, #005758, #008080);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .adv-form-title {
      font-size: 15px;
      font-weight: 700;
      color: #1a1f36;
      letter-spacing: -0.01em;
    }
    .adv-sku-link {
      font-size: 12px;
      font-weight: 600;
      color: #64748b;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 5px;
      padding: 6px 12px;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      background: #fff;
      transition: all 0.15s ease;
    }
    .adv-sku-link:hover {
      color: #005758;
      border-color: #005758;
      background: #f0fdfa;
    }
    .adv-form-body {
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .adv-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .adv-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .adv-label {
      font-size: 12.5px;
      font-weight: 600;
      color: #475569;
      letter-spacing: 0.01em;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .adv-hint {
      font-size: 11px;
      font-weight: 500;
      color: #94a3b8;
    }
    .adv-input {
      width: 100%;
      height: 42px;
      padding: 0 14px;
      font-size: 14px;
      font-weight: 500;
      color: #1e293b;
      background: #f8fafc;
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      outline: none;
      transition: all 0.2s ease;
      font-family: 'Inter', sans-serif;
    }
    .adv-input::placeholder { color: #b0bec5; font-weight: 400; }
    .adv-input:hover { border-color: #cbd5e1; background: #f1f5f9; }
    .adv-input:focus { border-color: #005758; background: #fff; box-shadow: 0 0 0 3px rgba(0,87,88,0.08); }
    .adv-input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }
    .adv-input-icon {
      position: absolute;
      left: 12px;
      color: #94a3b8;
      pointer-events: none;
      z-index: 1;
    }
    .adv-input--icon { padding-left: 36px; }
    .adv-unit {
      position: absolute;
      right: 12px;
      font-size: 11px;
      font-weight: 600;
      color: #94a3b8;
      pointer-events: none;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    /* Segmented Control */
    .adv-segmented {
      display: grid;
      grid-template-columns: 1fr 1fr;
      position: relative;
      background: #f1f5f9;
      border-radius: 10px;
      padding: 3px;
      height: 42px;
      box-sizing: border-box;
    }
    .adv-segmented input[type="radio"] { display: none; }
    .adv-seg-option {
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      border-radius: 8px;
      position: relative;
      z-index: 2;
      transition: color 0.2s ease;
      user-select: none;
    }
    .adv-seg-text {
      font-size: 13px;
      font-weight: 600;
      color: #64748b;
      transition: color 0.2s ease;
    }
    .adv-segmented input[type="radio"]:checked + .adv-seg-option {
      background: #fff;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
    }
    .adv-segmented input[type="radio"]:checked + .adv-seg-option .adv-seg-text {
      color: #005758;
      font-weight: 700;
    }

    /* Actions Bar */
    .adv-form-actions {
      display: flex;
      gap: 10px;
      padding: 16px 24px 20px;
      border-top: 1px solid #eef1f6;
      background: #fcfcfd;
    }
    .adv-btn-submit {
      flex: 1;
      height: 46px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background: linear-gradient(135deg, #005758, #007a7c);
      color: #fff;
      font-size: 14px;
      font-weight: 700;
      font-family: 'Inter', sans-serif;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.2s ease;
      box-shadow: 0 2px 8px rgba(0,87,88,0.25);
      letter-spacing: -0.01em;
    }
    .adv-btn-submit:hover {
      background: linear-gradient(135deg, #004546, #006a6c);
      box-shadow: 0 4px 14px rgba(0,87,88,0.35);
      transform: translateY(-1px);
    }
    .adv-btn-submit:active { transform: translateY(0); box-shadow: 0 1px 4px rgba(0,87,88,0.2); }
    .adv-btn-pdf {
      height: 46px;
      padding: 0 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      background: #fff;
      color: #005758;
      font-size: 13px;
      font-weight: 700;
      font-family: 'Inter', sans-serif;
      border: 1.5px solid #d1d5db;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .adv-btn-pdf:hover {
      border-color: #005758;
      background: #f0fdfa;
      transform: translateY(-1px);
      box-shadow: 0 2px 6px rgba(0,87,88,0.12);
    }

    .markdown-content strong { font-weight: 700; }
    .markdown-content code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.85em; }

    /* ===== RESPONSIVE LAYOUT ===== */
    .adv-main-grid {
      display: block;
      max-width: 960px;
      margin: 0 auto;
    }

    /* Mobile: painel full-width bottom sheet premium */
    @media (max-width: 768px) {
      .chat-wrap.chat-open .chat-card {
        right: 0 !important;
        width: 100% !important;
        height: 92vh !important;
        max-height: 92vh !important;
        border-radius: 24px 24px 0 0 !important;
        box-shadow: 0 -10px 60px rgba(0,0,0,0.3) !important;
      }
      .chat-wrap.chat-open {
        background: rgba(15,23,42,0.5) !important;
      }
    }

    /* Mobile */
    @media (max-width: 768px) {
      #app {
        padding: 0.75rem !important;
        max-width: 100% !important;
      }
      header {
        padding-bottom: 1rem !important;
        margin-bottom: 1rem !important;
        gap: 0.75rem !important;
      }
      header h1 {
        font-size: 1.25rem !important;
      }
      header p {
        font-size: 0.75rem !important;
      }
      header .flex.items-center.gap-6 {
        width: 100%;
      }
      header nav {
        flex-wrap: wrap !important;
        gap: 8px !important;
        justify-content: center !important;
        width: 100%;
      }
      header nav a {
        font-size: 0.78rem !important;
      }
      header img {
        height: 2rem !important;
      }

      /* Form */
      .adv-form { border-radius: 12px; }
      .adv-row { grid-template-columns: 1fr !important; }
      .adv-form-body { padding: 14px !important; gap: 14px !important; }
      .adv-form-header { padding: 10px 14px !important; flex-wrap: wrap !important; gap: 8px !important; }
      .adv-form-actions { padding: 10px 14px 14px !important; flex-wrap: wrap; }
      .adv-form-title { font-size: 13px !important; }
      .adv-btn-submit { font-size: 13px !important; height: 42px !important; }
      .adv-btn-pdf { height: 42px !important; }
      .adv-input { height: 38px !important; font-size: 13px !important; }
      .adv-segmented { height: 38px !important; }
      .adv-seg-text { font-size: 12px !important; }
      .adv-label { font-size: 11.5px !important; }

      /* Results table */
      .bg-white.rounded-xl.shadow-lg.border.border-slate-200.p-6 {
        padding: 12px !important;
        border-radius: 12px !important;
      }
      .advisor-table { min-width: 600px; }
      .advisor-table th {
        padding: 8px 6px !important;
        font-size: 0.7rem !important;
      }
      .advisor-table td {
        padding: 8px 6px !important;
        font-size: 0.78rem !important;
      }

      /* Chat internals (mobile adjustments premium) */
      .chat-header { padding: 16px 18px !important; }
      .chat-header .ai-avatar { width: 38px !important; height: 38px !important; border-radius: 12px !important; }
      .chat-header h3 { font-size: 0.88rem !important; }
      .chat-messages { padding: 16px !important; gap: 14px !important; }
      .chat-input-area { padding: 12px 16px 16px !important; }
      .chat-bubble-assistant,
      .chat-bubble-user {
        padding: 14px 16px !important;
        font-size: 0.85rem !important;
        border-radius: 16px !important;
      }
      .chat-bubble-assistant { border-radius: 4px 16px 16px 16px !important; }
      .chat-bubble-user { border-radius: 16px 4px 16px 16px !important; }
      .chat-input-wrap {
        padding: 8px 8px 8px 14px !important;
        border-radius: 14px !important;
      }
      .chat-send-btn { width: 38px !important; height: 38px !important; border-radius: 12px !important; }
      .prompt-grid {
        grid-template-columns: 1fr !important;
        gap: 8px !important;
      }
      .prompt-card { 
        padding: 12px 14px !important; 
        border-radius: 12px !important;
      }
      .prompt-card-text { font-size: 0.72rem !important; }
      .prompt-card-icon { font-size: 1.1rem !important; }
      #chatFab { 
        bottom: 20px !important; 
        right: 20px !important; 
        width: 58px !important; 
        height: 58px !important; 
        border-radius: 18px !important;
      }

      /* Disclaimer */
      .mt-6.p-4.bg-slate-50 { padding: 10px !important; }
      .mt-6.p-4.bg-slate-50 h3 { font-size: 0.78rem !important; }
      .mt-6.p-4.bg-slate-50 li { font-size: 0.7rem !important; }
    }

    /* Small phones */
    @media (max-width: 400px) {
      #app { padding: 0.5rem !important; }
      header h1 { font-size: 1.1rem !important; }
      .adv-form-body { padding: 10px !important; }
      .adv-form-header { padding: 8px 10px !important; }
      .adv-btn-submit { font-size: 12px !important; gap: 4px !important; }
      .chat-wrap.chat-open .chat-card { height: 95vh !important; max-height: 95vh !important; border-radius: 20px 20px 0 0 !important; }
      #chatFab { bottom: 16px !important; right: 16px !important; width: 52px !important; height: 52px !important; }
      .chat-header { padding: 14px 16px !important; }
      .prompt-card { padding: 10px 12px !important; }
    }
  </style>
</head>
<body class="bg-slate-100 min-h-screen font-sans text-slate-900">
  <?php include __DIR__ . '/../templates/topbar.php'; ?>
  <div id="app" class="container mx-auto p-4 md:p-8 max-w-[1600px]">

    <!-- Layout 2 colunas -->
    <div class="adv-main-grid">

      <!-- COLUNA ESQUERDA: Formulário + Resultados -->
      <div>
        <!-- Formulário -->
        <form method="POST" id="advisorForm" class="adv-form" autocomplete="off">
          <input type="hidden" name="calculate_advisor" value="1">
          <input type="hidden" name="amortizationYears" value="3">

          <!-- Header -->
          <div class="adv-form-header">
            <div class="adv-form-header-left">
              <div class="adv-form-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
              </div>
              <span class="adv-form-title"><?= __('page_content.sql_title') ?></span>
            </div>
            <a href="sku-management.php" class="adv-sku-link">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              <?= __('menu.sku_management') ?>
            </a>
          </div>

          <!-- Form Body -->
          <div class="adv-form-body">

            <!-- Row 1: Cliente + Vendedor -->
            <div class="adv-row">
              <div class="adv-field">
                <label class="adv-label"><?= __('page_content.sql_client_name') ?></label>
                <input type="text" name="clientName" value="<?php echo htmlspecialchars($adv['clientName']); ?>" placeholder="<?= __('page_content.sql_client_name') ?>" class="adv-input">
              </div>
              <div class="adv-field">
                <label class="adv-label"><?= __('page_content.sql_vendor_name') ?></label>
                <input type="text" name="vendorName" value="<?php echo htmlspecialchars($adv['vendorName']); ?>" placeholder="Seu nome" class="adv-input">
              </div>
            </div>

            <!-- Row 2: vCores + Horas -->
            <div class="adv-row">
              <div class="adv-field">
                <label class="adv-label">vCores <span class="adv-hint">mín. 4</span></label>
                <div class="adv-input-wrap">
                  <svg class="adv-input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>
                  <input type="number" name="vCores" value="<?php echo (int) $adv['vCores']; ?>" min="4" step="1" class="adv-input adv-input--icon">
                  <span class="adv-unit">cores</span>
                </div>
              </div>
              <div class="adv-field">
                <label class="adv-label">Horas / Mês <span class="adv-hint">máx. 744</span></label>
                <div class="adv-input-wrap">
                  <svg class="adv-input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  <input type="number" name="hoursPerMonth" value="<?php echo (int) $adv['hoursPerMonth']; ?>" min="1" max="744" class="adv-input adv-input--icon">
                  <span class="adv-unit">h/mês</span>
                </div>
              </div>
            </div>

            <!-- Row 3: Edição (segmented) + Moeda (segmented) -->
            <div class="adv-row">
              <div class="adv-field">
                <label class="adv-label">Edição SQL Server</label>
                <div class="adv-segmented">
                  <input type="radio" name="edition" value="Standard" id="ed-std" <?php echo $adv['edition'] === 'Standard' ? 'checked' : ''; ?>>
                  <label for="ed-std" class="adv-seg-option">
                    <span class="adv-seg-text">Standard</span>
                  </label>
                  <input type="radio" name="edition" value="Enterprise" id="ed-ent" <?php echo $adv['edition'] === 'Enterprise' ? 'checked' : ''; ?>>
                  <label for="ed-ent" class="adv-seg-option">
                    <span class="adv-seg-text">Enterprise</span>
                  </label>
                  <div class="adv-seg-slider"></div>
                </div>
              </div>
              <div class="adv-field">
                <label class="adv-label">Moeda</label>
                <div class="adv-segmented">
                  <input type="radio" name="currency" value="USD" id="cur-usd" <?php echo $adv['currency'] === 'USD' ? 'checked' : ''; ?>>
                  <label for="cur-usd" class="adv-seg-option">
                    <span class="adv-seg-text">$ USD</span>
                  </label>
                  <input type="radio" name="currency" value="BRL" id="cur-brl" <?php echo $adv['currency'] === 'BRL' ? 'checked' : ''; ?>>
                  <label for="cur-brl" class="adv-seg-option">
                    <span class="adv-seg-text">R$ BRL</span>
                  </label>
                  <div class="adv-seg-slider"></div>
                </div>
              </div>
            </div>

          </div>

          <!-- Actions -->
          <div class="adv-form-actions">
            <input type="hidden" name="calculate_advisor" value="1">
            <button type="submit" class="adv-btn-submit" id="btnSubmitAdvisor">
              <svg class="adv-btn-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
              <span class="adv-btn-label">Executar Comparativo</span>
            </button>
            <button type="button" id="btn-generate-advisor-pdf" class="adv-btn-pdf" style="display:none;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
              PDF
            </button>
          </div>
        </form>

        <!-- Container para Resultados (renderizado via JS) -->
        <div id="advisorResults"></div>

        <?php if (!empty($GLOBALS['gov_user']['token'])): ?>
        <!-- Governança T2: salvar resultado como proposta -->
        <div id="saveProposalWrap" style="margin-top:16px;<?php echo isset($_SESSION['advisorResult']) ? '' : 'display:none;'; ?>">
          <?php if (!empty($_SESSION['proposalSaved'])): ?>
          <div style="background:#dcfce7;border:1px solid #86efac;color:#166534;padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:10px;">
            <i class="bi bi-check-circle me-1"></i>Proposta salva no portal! Voce pode revisita-la a qualquer momento.
          </div>
          <?php unset($_SESSION['proposalSaved']); endif; ?>
          <form method="POST" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
            <input type="hidden" name="save_proposal" value="1">
            <div style="flex:1;min-width:160px;">
              <label class="adv-label">Cliente final</label>
              <input type="text" name="customerName" class="adv-input" value="<?php echo htmlspecialchars((string)($adv['clientName'] ?? '')); ?>">
            </div>
            <div style="flex:1;min-width:160px;">
              <label class="adv-label">CNPJ (opcional)</label>
              <input type="text" name="customerTaxId" class="adv-input" placeholder="00.000.000/0000-00">
            </div>
            <button type="submit" class="btn btn-sm" style="background:#0d6efd;color:#fff;border:none;padding:8px 16px;border-radius:8px;">
              <i class="bi bi-cloud-arrow-up me-1"></i>Salvar como Proposta
            </button>
          </form>
        </div>
        <?php endif; ?>
      </div>

      <!-- COLUNA DIREITA: Chat IA -->
      <div class="chat-wrap">
      <div class="chat-card">

        <!-- Header Premium -->
        <div class="chat-header">
          <div class="flex items-center justify-between" style="position:relative;z-index:1;">
            <div class="flex items-center gap-3">
              <div class="ai-avatar" style="width:44px;height:44px;border-radius:14px;font-size:14px;background:linear-gradient(145deg, rgba(255,255,255,0.2), rgba(255,255,255,0.05));border:1px solid rgba(255,255,255,0.15);box-shadow:0 4px 16px rgba(0,0,0,0.2);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" style="width:22px;height:22px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
                  <path fill-rule="evenodd" d="M9 4.5a.75.75 0 0 1 .721.544l.813 2.846a3.75 3.75 0 0 0 2.576 2.576l2.846.813a.75.75 0 0 1 0 1.442l-2.846.813a3.75 3.75 0 0 0-2.576 2.576l-.813 2.846a.75.75 0 0 1-1.442 0l-.813-2.846a3.75 3.75 0 0 0-2.576-2.576l-2.846-.813a.75.75 0 0 1 0-1.442l2.846-.813A3.75 3.75 0 0 0 7.466 7.89l.813-2.846A.75.75 0 0 1 9 4.5Z" clip-rule="evenodd" />
                  <path fill-rule="evenodd" d="M15 15a.75.75 0 0 1 .728.568l.258.902a2.25 2.25 0 0 0 1.544 1.544l.902.258a.75.75 0 0 1 0 1.456l-.902.258a2.25 2.25 0 0 0-1.544 1.544l-.258.902a.75.75 0 0 1-1.456 0l-.258-.902a2.25 2.25 0 0 0-1.544-1.544l-.902-.258a.75.75 0 0 1 0-1.456l.902-.258a2.25 2.25 0 0 0 1.544-1.544l.258-.902A.75.75 0 0 1 15 15Z" clip-rule="evenodd" opacity="0.6"/>
                </svg>
              </div>
              <div>
                <h3 style="color:#fff;font-weight:700;font-size:0.95rem;line-height:1.2;text-shadow:0 1px 2px rgba(0,0,0,0.2);">Especialista em Licenciamento</h3>
                <p style="color:rgba(153,246,228,0.9);font-size:0.72rem;margin-top:4px;font-weight:500;display:flex;align-items:center;gap:6px;">
                  <span style="display:inline-flex;align-items:center;gap:4px;">
                    <svg style="width:12px;height:12px;" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    TD SYNNEX
                  </span>
                  <span style="opacity:0.6;">·</span>
                  <span>SQL Server 2022</span>
                </p>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="display:flex;align-items:center;gap:6px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.18);border-radius:10px;padding:6px 12px;backdrop-filter:blur(8px);">
                <div style="width:8px;height:8px;border-radius:50%;background:#4ade80;box-shadow:0 0 8px #4ade80,0 0 16px rgba(74,222,128,0.4);animation:pulse 2s infinite;"></div>
                <span style="color:rgba(255,255,255,0.95);font-size:0.72rem;font-weight:600;letter-spacing:0.04em;">GPT-4o</span>
              </div>
              <button id="chatMobileClose" aria-label="Fechar chat" title="Minimizar chat">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="white" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Mensagens -->
        <div class="chat-messages" id="chatMessages">
          <div class="msg-container msg-assistant">
            <div class="msg-meta">
              <div class="ai-avatar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" style="width:14px;height:14px;">
                  <path fill-rule="evenodd" d="M9 4.5a.75.75 0 0 1 .721.544l.813 2.846a3.75 3.75 0 0 0 2.576 2.576l2.846.813a.75.75 0 0 1 0 1.442l-2.846.813a3.75 3.75 0 0 0-2.576 2.576l-.813 2.846a.75.75 0 0 1-1.442 0l-.813-2.846a3.75 3.75 0 0 0-2.576-2.576l-2.846-.813a.75.75 0 0 1 0-1.442l2.846-.813A3.75 3.75 0 0 0 7.466 7.89l.813-2.846A.75.75 0 0 1 9 4.5Z" clip-rule="evenodd" />
                </svg>
              </div>
              Especialista
            </div>
            <div class="chat-bubble-assistant markdown-content">
              <strong style="color:#005758;">Olá!</strong> Sou o especialista em licenciamento SQL Server 2022 da TD SYNNEX. Como posso ajudar na sua venda hoje?
              <div class="prompt-grid" id="promptSuggestions">
                <div class="prompt-card" onclick="askQuickQuestion('Meu cliente tem 2 servidores com 16 cores cada e 5 VMs rodando SQL. Quero apresentar a melhor opção de licenciamento. Monte uma comparação com os modelos disponíveis e destaque a economia do ARC.')">
                  <div class="prompt-card-icon">💼</div>
                  <div class="prompt-card-text">Montar proposta: 2 hosts, 16 cores, 5 VMs</div>
                </div>
                <div class="prompt-card" onclick="askQuickQuestion('O cliente já usa SPLA e quer entender se vale migrar para Azure ARC. Quais argumentos de venda uso para convencê-lo? Me dê uma comparação de custos e benefícios.')">
                  <div class="prompt-card-icon">🔄</div>
                  <div class="prompt-card-text">Como vender migração de SPLA para ARC?</div>
                </div>
                <div class="prompt-card" onclick="askQuickQuestion('O cliente quer saber a diferença entre Standard e Enterprise no SQL Server 2022. Preciso explicar de forma simples quando vale pagar mais pelo Enterprise. Me dê uma tabela comparativa focada em vendas.')">
                  <div class="prompt-card-icon">⚖️</div>
                  <div class="prompt-card-text">Standard vs Enterprise: como explicar ao cliente</div>
                </div>
                <div class="prompt-card" onclick="askQuickQuestion('O cliente perguntou sobre Software Assurance. Como justifico o investimento em SA? Quais benefícios concretos posso apresentar para fechar a venda?')">
                  <div class="prompt-card-icon">🛡️</div>
                  <div class="prompt-card-text">Como justificar Software Assurance na venda</div>
                </div>
                <div class="prompt-card" onclick="askQuickQuestion('O cliente tem ambiente virtualizado com muitas VMs SQL. Quando devo recomendar licenciar o host inteiro (Virtualização Máxima) vs licenciar cada VM? Me ajude a calcular o breakeven.')">
                  <div class="prompt-card-icon">🖥️</div>
                  <div class="prompt-card-text">Licenciar host vs VMs: qual vende melhor?</div>
                </div>
                <div class="prompt-card" onclick="askQuickQuestion('O cliente está entre CSP 1 ano, CSP 3 anos e OVS. Preciso de argumentos claros para cada cenário: quando recomendar compromisso curto vs longo prazo? Foque no impacto financeiro.')">
                  <div class="prompt-card-icon">📊</div>
                  <div class="prompt-card-text">CSP 1 ano vs 3 anos vs OVS: qual indicar?</div>
                </div>
                <div class="prompt-card" onclick="askQuickQuestion('O cliente quer migrar SQL Server para Azure. Explique as opções: Azure SQL, SQL em VM com Pay-As-You-Go, Reserved Instances e Azure Hybrid Benefit. Qual gera mais economia?')">
                  <div class="prompt-card-icon">☁️</div>
                  <div class="prompt-card-text">Cliente quer ir para Azure: como orientar?</div>
                </div>
                <div class="prompt-card" onclick="askQuickQuestion('O cliente está preocupado com compliance de licenciamento. Quais riscos de sub-licenciamento existem? Como posso usar isso como argumento de venda para regularizar com a solução certa?')">
                  <div class="prompt-card-icon">⚠️</div>
                  <div class="prompt-card-text">Compliance: riscos e argumentos de venda</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Input Area Premium -->
        <div class="chat-input-area">
          <div class="chat-input-wrap">
            <input type="text" id="chatInput" placeholder="Pergunte sobre licenciamento SQL Server..."
                   onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();handleChatBtn();}">
            <button class="chat-send-btn" id="chatSendBtn" onclick="handleChatBtn()" title="Enviar mensagem">
              <svg id="sendIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="white" style="width:18px;height:18px;transform:rotate(45deg);filter:drop-shadow(0 1px 2px rgba(0,0,0,0.2));">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
              </svg>
              <svg id="stopIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" style="width:18px;height:18px;display:none;">
                <rect x="6" y="6" width="12" height="12" rx="3" />
              </svg>
            </button>
          </div>
          <p style="text-align:center;font-size:0.68rem;color:#94a3b8;margin-top:10px;font-weight:500;">
            Powered by <strong style="color:#005758;">GPT-4o</strong> · Respostas em tempo real
          </p>
        </div>

      </div> <!-- /chat-card -->
      </div> <!-- /chat-wrap -->

    </div><!-- /grid -->

  <!-- Mobile floating chat button - Premium Design -->
  <button id="chatFab" aria-label="Abrir chat especialista" title="Especialista em Licenciamento SQL">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="white" style="width:28px;height:28px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
    </svg>
    </svg>
  </button>

  <!-- jsPDF -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>

  <script>
    // ==================== ADVISOR AJAX ====================
    function fmtNum(v, decimals) {
      return Number(v).toFixed(decimals).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function renderAdvisorResults(data) {
      const cs = data.params.currency === 'BRL' ? 'R$' : '$';
      const arc = data.arc;

      // Sort others by monthly cost
      const otherKeys = ['spla','csp1y','csp3y','perpetual','ovs'];
      const savingsMap = { spla:'vsSpla', csp1y:'vsCsp1y', csp3y:'vsCsp3y', perpetual:'vsPerpetual', ovs:'vsOvs' };
      const sorted = otherKeys.map(k => ({ key: k, ...data[k] })).sort((a,b) => a.monthly - b.monthly);

      let rows = '';
      // ARC row
      rows += `<tr class="recommended-row" style="border-radius:8px;">
        <td class="font-bold">${arc.label}</td>
        <td class="text-center">—</td>
        <td class="text-right text-xs opacity-90">${cs} ${fmtNum(arc.unitPrice, 4)} /hora</td>
        <td class="text-right font-semibold">${cs} ${fmtNum(arc.monthly, 2)}</td>
        <td class="text-right">${cs} ${fmtNum(arc.annual, 2)}</td>
        <td class="text-right">${cs} ${fmtNum(arc.total3y, 2)}</td>
        <td class="text-right">—</td>
      </tr>`;

      sorted.forEach((m, i) => {
        const sav = data.savings[savingsMap[m.key]] || { monthly: 0, pct: 0 };
        const bg = i % 2 === 0 ? 'background:#f8fafc;' : '';
        let savCell;
        if (sav.pct > 0) {
          savCell = `<span class="savings-positive">+${cs} ${fmtNum(sav.monthly, 2)}/mês (${fmtNum(sav.pct, 1)}%)</span>`;
        } else if (sav.pct < 0) {
          savCell = `<span class="savings-negative">${cs} ${fmtNum(sav.monthly, 2)}/mês (${fmtNum(sav.pct, 1)}%)</span>`;
        } else {
          savCell = '<span class="text-slate-400">0%</span>';
        }
        rows += `<tr style="${bg} border-bottom:1px solid #e2e8f0;">
          <td class="font-medium text-slate-800">${m.label}</td>
          <td class="text-center">${m.packs}</td>
          <td class="text-right text-xs text-slate-500">${cs} ${fmtNum(m.unitPrice, 2)} /pack</td>
          <td class="text-right">${cs} ${fmtNum(m.monthly, 2)}</td>
          <td class="text-right">${cs} ${fmtNum(m.annual, 2)}</td>
          <td class="text-right">${cs} ${fmtNum(m.total3y, 2)}</td>
          <td class="text-right">${savCell}</td>
        </tr>`;
      });

      let noteHtml = '';
      if (data.bestModel !== 'arc') {
        const bestLabel = data[data.bestModel]?.label || data.bestModel;
        noteHtml = `<div class="mt-4 p-3 rounded-lg" style="background:#fef3c7;border:1px solid #fcd34d;">
          <p class="text-sm text-amber-800"><strong>Nota:</strong> Para este cenário, o modelo mais econômico é <strong>${bestLabel}</strong>. Contudo, o Azure ARC oferece benefícios adicionais como virtualização ilimitada, sem compromisso e gestão via portal Azure.</p>
        </div>`;
      }

      const html = `
        <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6" style="animation:msgIn .3s ease-out;">
          <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5" style="color:#005758;">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6"/>
            </svg>
            Comparativo de Custos — ${data.params.edition} (${data.params.vCores} vCores)
          </h2>
          <div class="overflow-x-auto">
            <table class="w-full advisor-table">
              <thead><tr class="border-b-2 border-slate-200">
                <th class="text-slate-600">Modelo</th>
                <th class="text-slate-600 text-center">Packs</th>
                <th class="text-slate-600 text-right">Preço Unit.</th>
                <th class="text-slate-600 text-right">Custo Mensal</th>
                <th class="text-slate-600 text-right">Custo Anual</th>
                <th class="text-slate-600 text-right">Total 3 Anos</th>
                <th class="text-slate-600 text-right">Economia vs ARC</th>
              </tr></thead>
              <tbody>${rows}</tbody>
            </table>
          </div>
          ${noteHtml}
          <div class="mt-6 p-4 bg-slate-50 border border-slate-200 rounded-lg shadow-sm">
            <h3 class="text-sm font-bold text-teal-800 mb-2 border-b border-teal-100 pb-1">Dinâmica de Faturamento por Modelo (Atenção Comercial)</h3>
            <ul class="text-xs text-slate-600 space-y-1.5 list-disc list-inside">
              <li><strong class="text-slate-800">Azure ARC (Recomendado):</strong> Cobrado mensalmente (Pay-As-You-Go) apenas pelos vCores consumidos por hora.</li>
              <li><strong class="text-slate-800">SPLA:</strong> Faturamento pós-pago mensal recorrente. Licenciamento mínimo exigido em pacotes de 2 cores.</li>
              <li><strong class="text-slate-800">CSP (1 e 3 Anos):</strong> Faturamento total antecipado (upfront) pelo período de contrato. Requer pacotes de 2 cores.</li>
              <li><strong class="text-slate-800">Perpétuo + SA:</strong> Aquisição de licença definitiva (CapEx) com renovação anual do contrato de Software Assurance.</li>
              <li><strong class="text-slate-800">OVS:</strong> Assinatura padronizada de valor anualizado (OpEx) que já inclui benefícios de Software Assurance.</li>
            </ul>
          </div>
        </div>`;

      document.getElementById('advisorResults').innerHTML = html;
      document.getElementById('advisorResults').scrollIntoView({ behavior: 'smooth', block: 'nearest' });

      // Atualizar dados para PDF e mostrar botão
      window.advisorData = data;
      document.getElementById('btn-generate-advisor-pdf').style.display = 'flex';
    }

    // Submit via AJAX
    document.getElementById('advisorForm').addEventListener('submit', function(e) {
      e.preventDefault();

      const btn = document.getElementById('btnSubmitAdvisor');
      const icon = btn.querySelector('.adv-btn-icon');
      const label = btn.querySelector('.adv-btn-label');
      const origLabel = label.textContent;

      // Loading state
      btn.disabled = true;
      btn.style.opacity = '0.7';
      label.textContent = 'Calculando...';
      icon.style.animation = 'spin 1s linear infinite';

      const formData = new FormData(this);

      fetch('sql-advisor.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        renderAdvisorResults(data);
        var wrap = document.getElementById('saveProposalWrap');
        if (wrap) { wrap.style.display = ''; }
      })
      .catch(err => {
        console.error('Erro:', err);
        document.getElementById('advisorResults').innerHTML =
          '<div class="p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">Erro ao calcular. Tente novamente.</div>';
      })
      .finally(() => {
        btn.disabled = false;
        btn.style.opacity = '1';
        label.textContent = origLabel;
        icon.style.animation = '';
      });
    });

    // Renderizar resultado da sessão se existir
    <?php if ($advisorResult): ?>
    window.advisorData = <?php echo json_encode($advisorResult); ?>;
    renderAdvisorResults(window.advisorData);
    <?php endif; ?>

    // ==================== CHAT TOGGLE (ALL SCREENS) ====================
    (function () {
      const fab = document.getElementById('chatFab');
      const wrap = document.querySelector('.chat-wrap');
      const closeBtn = document.getElementById('chatMobileClose');

      function openChat() {
        wrap.classList.add('chat-open');
        fab.classList.add('chat-fab-hidden');
        document.body.style.overflow = 'hidden';
        const msgs = document.getElementById('chatMessages');
        if (msgs) setTimeout(() => { msgs.scrollTop = msgs.scrollHeight; }, 50);
      }

      function closeChat() {
        wrap.classList.remove('chat-open');
        fab.classList.remove('chat-fab-hidden');
        document.body.style.overflow = '';
      }

      if (fab) fab.addEventListener('click', openChat);
      if (closeBtn) closeBtn.addEventListener('click', closeChat);

      // Clique no backdrop (fora do card) fecha
      if (wrap) {
        wrap.addEventListener('click', function (e) {
          if (e.target === wrap) closeChat();
        });
      }

      // Esc fecha o chat
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && wrap && wrap.classList.contains('chat-open')) closeChat();
      });
    })();

    // ==================== PDF ADVISOR ====================
    document.getElementById('btn-generate-advisor-pdf').addEventListener('click', function () {
      if (!window.advisorData) {
        alert('Dados não disponíveis. Execute o cálculo primeiro.');
        return;
      }
      if (typeof window.generateAdvisorPDF === 'function') {
        window.generateAdvisorPDF(window.advisorData);
      } else if (typeof generateAdvisorPDF === 'function') {
        generateAdvisorPDF(window.advisorData);
      } else {
        // Tentar carregar o script novamente
        var s = document.createElement('script');
        s.src = 'assets/js/app.js?v=' + Date.now();
        s.onload = function() {
          if (typeof generateAdvisorPDF === 'function') {
            generateAdvisorPDF(window.advisorData);
          } else {
            alert('Erro: Função de geração de PDF não foi carregada. Recarregue a página (Ctrl+F5).');
          }
        };
        s.onerror = function() {
          alert('Erro ao carregar o script de PDF. Verifique a conexão e recarregue a página.');
        };
        document.head.appendChild(s);
      }
    });
    let chatHistory = [];
    let chatAbortController = null;
    let isChatLoading = false;

    function setChatBtnState(loading) {
      isChatLoading = loading;
      const btn = document.getElementById('chatSendBtn');
      const sendIcon = document.getElementById('sendIcon');
      const stopIcon = document.getElementById('stopIcon');
      if (loading) {
        btn.classList.add('is-stop');
        btn.title = 'Parar';
        sendIcon.style.display = 'none';
        stopIcon.style.display = 'block';
      } else {
        btn.classList.remove('is-stop');
        btn.title = 'Enviar';
        sendIcon.style.display = 'block';
        stopIcon.style.display = 'none';
      }
    }

    function handleChatBtn() {
      if (isChatLoading) {
        if (chatAbortController) chatAbortController.abort();
      } else {
        sendChatMessage();
      }
    }

    function renderMarkdown(text) {
      // 1) Tabelas markdown
      text = text.replace(/(\|.+\|\n)(\|[-| :]+\|\n)((\|.+\|\n?)+)/g, function(match, headerLine, separatorLine, bodyBlock) {
        const headers = headerLine.trim().split('|').filter(c => c.trim() !== '').map(c => c.trim());
        const rows = bodyBlock.trim().split('\n').map(row =>
          row.trim().split('|').filter(c => c.trim() !== '').map(c => c.trim())
        );
        let html = '<div style="overflow-x:auto;"><table>';
        html += '<thead><tr>' + headers.map(h => '<th>' + h + '</th>').join('') + '</tr></thead>';
        html += '<tbody>' + rows.map(r => '<tr>' + r.map(c => '<td>' + c + '</td>').join('') + '</tr>').join('') + '</tbody>';
        html += '</table></div>';
        return html;
      });

      // 2) Headers ### e ####
      text = text.replace(/^#### (.+)$/gm, '<h4>$1</h4>');
      text = text.replace(/^### (.+)$/gm, '<h3>$1</h3>');

      // 3) Horizontal rules ---
      text = text.replace(/^---$/gm, '<hr>');

      // 4) Bold, italic, code
      text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
      text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
      text = text.replace(/`(.*?)`/g, '<code>$1</code>');

      // 5) Listas com -
      text = text.replace(/^(- .+(?:\n- .+)*)/gm, function(block) {
        const items = block.split('\n').map(line => '<li>' + line.replace(/^- /, '') + '</li>').join('');
        return '<ul>' + items + '</ul>';
      });

      // 6) Listas numeradas
      text = text.replace(/^(\d+\. .+(?:\n\d+\. .+)*)/gm, function(block) {
        const items = block.split('\n').map(line => '<li>' + line.replace(/^\d+\. /, '') + '</li>').join('');
        return '<ol>' + items + '</ol>';
      });

      // 7) Quebras de linha restantes
      text = text.replace(/\n/g, '<br>');

      // 8) Limpar <br> extras ao redor de blocos
      text = text.replace(/<br>(<\/?(?:table|thead|tbody|tr|th|td|ul|ol|li|h[34]|hr|div))/g, '$1');
      text = text.replace(/(<\/(?:table|thead|tbody|tr|th|td|ul|ol|li|h[34]|hr|div)>)<br>/g, '$1');

      return text;
    }

    function addChatMessage(role, text) {
      const container = document.getElementById('chatMessages');
      
      // Esconder sugestões na primeira interação
      const suggestions = document.getElementById('promptSuggestions');
      if (suggestions) suggestions.style.display = 'none';
      
      const div = document.createElement('div');
      
      const isUser = role === 'user';
      div.className = `msg-container ${isUser ? 'msg-user' : 'msg-assistant'}`;

      const label = isUser
        ? '<div class="msg-meta">Você</div>'
        : '<div class="msg-meta"><div class="ai-avatar">IA</div>Especialista</div>';

      const bubbleClass = isUser ? 'chat-bubble-user' : 'chat-bubble-assistant';

      div.innerHTML = `
        ${label}
        <div class="${bubbleClass} markdown-content">${renderMarkdown(text)}</div>
      `;
      container.appendChild(div);
      requestAnimationFrame(() => { container.scrollTop = container.scrollHeight; });
    }

    function showTypingIndicator() {
      const container = document.getElementById('chatMessages');
      const div = document.createElement('div');
      div.id = 'typing-indicator';
      div.className = 'msg-container msg-assistant';
      div.innerHTML = `
        <div class="msg-meta"><div class="ai-avatar">IA</div>Especialista</div>
        <div class="chat-bubble-assistant" style="display:flex;align-items:center;gap:4px;padding:14px 18px;min-width:60px;">
          <span class="typing-dot" style="animation-delay:0ms"></span>
          <span class="typing-dot" style="animation-delay:180ms"></span>
          <span class="typing-dot" style="animation-delay:360ms"></span>
        </div>
      `;
      container.appendChild(div);
      requestAnimationFrame(() => { container.scrollTop = container.scrollHeight; });
    }

    function removeTypingIndicator() {
      const el = document.getElementById('typing-indicator');
      if (el) el.remove();
    }

    async function sendChatMessage() {
      const input = document.getElementById('chatInput');
      const message = input.value.trim();
      if (!message || isChatLoading) return;

      input.value = '';
      addChatMessage('user', message);
      chatHistory.push({ role: 'user', content: message });

      showTypingIndicator();
      chatAbortController = new AbortController();
      setChatBtnState(true);

      try {
        const response = await fetch('chat-api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message, history: chatHistory }),
          signal: chatAbortController.signal
        });
        const data = await response.json();
        removeTypingIndicator();

        const reply = data.reply || 'Desculpe, não consegui processar sua pergunta.';
        addChatMessage('assistant', reply);
        chatHistory.push({ role: 'assistant', content: reply });
      } catch (err) {
        removeTypingIndicator();
        if (err.name === 'AbortError') {
          addChatMessage('assistant', 'Resposta interrompida pelo usuário.');
          chatHistory.pop();
        } else {
          addChatMessage('assistant', 'Erro de conexão. Tente novamente.');
        }
      } finally {
        chatAbortController = null;
        setChatBtnState(false);
      }
    }

    function askQuickQuestion(q) {
      document.getElementById('chatInput').value = q;
      sendChatMessage();
    }

  </script>
</body>
</html>
