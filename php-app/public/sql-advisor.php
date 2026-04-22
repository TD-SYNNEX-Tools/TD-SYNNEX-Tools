<?php
// filepath: public/sql-advisor.php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
use App\Features\SqlLicensing\Services\LicensingAdvisor;

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
}

// Se já tem resultado em sessão e é GET, mostra
if (!$advisorResult && isset($_SESSION['advisorResult'])) {
    $advisorResult = $_SESSION['advisorResult'];
}

$adv = $_SESSION['advisor'];
$currencySymbol = ($adv['currency'] ?? 'USD') === 'BRL' ? 'R$' : '$';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SQL Licensing Advisor - TD SYNNEX</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===== CHAT PANEL ===== */
    /* Chat começa recolhido em TODAS as telas */
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
      transition: background 0.25s ease;
    }
    .chat-wrap.chat-open {
      display: flex;
      background: rgba(0,0,0,0.35);
      pointer-events: all;
      animation: fadeIn 0.2s ease;
    }
    /* Desktop: painel flutuante inferior-direito */
    .chat-wrap.chat-open .chat-card {
      position: fixed;
      bottom: 0;
      right: 24px;
      width: 440px;
      height: 640px;
      max-height: calc(100vh - 40px);
      border-radius: 16px 16px 0 0;
      animation: slideUp 0.3s cubic-bezier(0.34,1.15,0.64,1);
    }
    .chat-card { display: flex; flex-direction: column; background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 8px 40px rgba(0,0,0,0.15); overflow: hidden; }
    /* Botão fechar chat — visível sempre quando aberto */
    #chatMobileClose {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.2);
      cursor: pointer;
      color: #fff;
      flex-shrink: 0;
      transition: background 0.15s;
    }
    #chatMobileClose:hover { background: rgba(255,255,255,0.25); }
    /* FAB — visível por padrão */
    #chatFab {
      display: flex;
      position: fixed;
      bottom: 22px;
      right: 22px;
      width: 58px;
      height: 58px;
      border-radius: 50%;
      background: linear-gradient(135deg, #002829 0%, #005758 100%);
      border: none;
      cursor: pointer;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 20px rgba(0,87,88,0.45);
      z-index: 999;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    #chatFab:hover { box-shadow: 0 6px 28px rgba(0,87,88,0.6); transform: scale(1.05); }
    #chatFab:active { transform: scale(0.93); }
    #chatFab.chat-fab-hidden { display: none; }
    .chat-header { background: linear-gradient(135deg, #002829 0%, #005758 100%); padding: 16px 20px; flex-shrink: 0; }
    .chat-messages { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 16px; padding: 20px; background: #f8fafc; }
    .chat-messages::-webkit-scrollbar { width: 6px; }
    .chat-messages::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
    .chat-messages::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    .chat-messages::-webkit-scrollbar-track { background: transparent; }
    .msg-container { display: flex; flex-direction: column; max-width: 95%; animation: msgIn 0.25s ease-out; }
    @keyframes msgIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes slideUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .msg-user { align-self: flex-end; align-items: flex-end; }
    .msg-assistant { align-self: flex-start; align-items: flex-start; }
    .chat-bubble-assistant { background: #fff; border: 1px solid #e8edf3; border-radius: 4px 16px 16px 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); padding: 14px 18px; font-size: 0.875rem; color: #1e293b; line-height: 1.7; }
    .chat-bubble-user { background: linear-gradient(135deg, #005758, #007a7c); border-radius: 16px 4px 16px 16px; padding: 14px 18px; font-size: 0.875rem; color: #fff; line-height: 1.7; box-shadow: 0 2px 8px rgba(0,87,88,0.25); }
    .msg-meta { font-size: 0.68rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
    .msg-user .msg-meta { color: #94a3b8; justify-content: flex-end; }
    .msg-assistant .msg-meta { color: #64748b; }
    .ai-avatar { width: 22px; height: 22px; border-radius: 6px; background: #005758; display: flex; align-items: center; justify-content: center; font-size: 8px; font-weight: 800; color: white; letter-spacing: 0; flex-shrink: 0; }
    .chat-input-area { padding: 14px 16px 16px; background: #fff; border-top: 1px solid #e8edf3; flex-shrink: 0; }
    .chat-input-wrap { display: flex; gap: 8px; align-items: center; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 8px 8px 8px 14px; transition: border-color 0.2s, box-shadow 0.2s; }
    .chat-input-wrap:focus-within { border-color: #005758; box-shadow: 0 0 0 3px rgba(0,87,88,0.08); background: #fff; }
    #chatInput { flex: 1; background: transparent; border: none; outline: none; font-size: 0.875rem; color: #1e293b; resize: none; min-height: 20px; max-height: 80px; padding: 4px 0; }
    #chatInput::placeholder { color: #94a3b8; }
    .chat-send-btn { width: 36px; height: 36px; border-radius: 10px; background: #005758; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background 0.2s, transform 0.1s; }
    .chat-send-btn:hover { background: #007a7c; }
    .chat-send-btn:active { transform: scale(0.93); }
    .chat-send-btn.is-stop { background: #dc2626; }
    .chat-send-btn.is-stop:hover { background: #b91c1c; }
    .quick-btns { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
    .quick-btn { font-size: 0.7rem; font-weight: 500; padding: 6px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; color: #475569; border-radius: 8px; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
    .quick-btn:hover { background: #e0f2f1; border-color: #99f6e4; color: #005758; }
    /* Suggestion cards */
    .prompt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 14px; }
    .prompt-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; cursor: pointer; transition: all 0.15s ease; text-align: left; }
    .prompt-card:hover { background: #e0f2f1; border-color: #99f6e4; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,87,88,0.08); }
    .prompt-card-icon { font-size: 1.1rem; margin-bottom: 4px; }
    .prompt-card-text { font-size: 0.72rem; color: #334155; line-height: 1.35; font-weight: 500; }
    .prompt-card:hover .prompt-card-text { color: #005758; }
    .markdown-content code { background: #f1f5f9; padding: 2px 6px; border-radius: 5px; font-size: 0.78em; font-family: monospace; color: #0f766e; }
    .markdown-content strong { font-weight: 700; }
    .markdown-content a { color: #0078D4; text-decoration: underline; }
    .markdown-content ul, .markdown-content ol { padding-left: 1.2rem; margin-top: 8px; }
    .markdown-content li { margin-bottom: 4px; }
    .markdown-content h3 { font-size: 0.9rem; font-weight: 700; margin: 12px 0 6px; color: #005758; }
    .markdown-content h4 { font-size: 0.85rem; font-weight: 700; margin: 10px 0 4px; color: #334155; }
    .markdown-content hr { border: none; border-top: 1px solid #e2e8f0; margin: 12px 0; }
    .markdown-content table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 0.78rem; line-height: 1.4; }
    .markdown-content table th { background: #005758; color: #fff; font-weight: 600; padding: 8px 10px; text-align: left; white-space: nowrap; }
    .markdown-content table th:first-child { border-radius: 6px 0 0 0; }
    .markdown-content table th:last-child { border-radius: 0 6px 0 0; }
    .markdown-content table td { padding: 7px 10px; border-bottom: 1px solid #e8edf3; color: #334155; }
    .markdown-content table tr:nth-child(even) td { background: #f8fafc; }
    .markdown-content table tr:hover td { background: #e0f2f1; }
    .markdown-content table td:first-child { font-weight: 600; color: #1e293b; }
    @keyframes bounceTyping { 0%,80%,100%{transform:translateY(0)} 40%{transform:translateY(-6px)} }
    .typing-dot { width: 7px; height: 7px; background: #94a3b8; border-radius: 50%; display: inline-block; margin: 0 2px; animation: bounceTyping 1.2s infinite ease-in-out; }
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

    /* Mobile: painel full-width bottom sheet */
    @media (max-width: 768px) {
      .chat-wrap.chat-open .chat-card {
        right: 0 !important;
        width: 100% !important;
        height: 88vh !important;
        max-height: 88vh !important;
        border-radius: 20px 20px 0 0 !important;
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

      /* Chat internals (mobile adjustments) */
      .chat-header { padding: 12px 14px !important; }
      .chat-messages { padding: 14px !important; gap: 12px !important; }
      .chat-input-area { padding: 10px 12px 12px !important; }
      .chat-bubble-assistant,
      .chat-bubble-user {
        padding: 10px 14px !important;
        font-size: 0.82rem !important;
      }
      .prompt-grid {
        grid-template-columns: 1fr !important;
        gap: 6px !important;
      }
      .prompt-card { padding: 8px 10px !important; }
      .prompt-card-text { font-size: 0.68rem !important; }
      .prompt-card-icon { font-size: 0.95rem !important; }
      #chatFab { bottom: 18px !important; right: 18px !important; width: 52px !important; height: 52px !important; }

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
      .chat-wrap.chat-open .chat-card { height: 95vh !important; max-height: 95vh !important; }
      #chatFab { bottom: 14px !important; right: 14px !important; width: 48px !important; height: 48px !important; }
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
              <span class="adv-form-title">Parâmetros da Comparação</span>
            </div>
            <a href="sku-management.php" class="adv-sku-link">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              Editar SKUs
            </a>
          </div>

          <!-- Form Body -->
          <div class="adv-form-body">

            <!-- Row 1: Cliente + Vendedor -->
            <div class="adv-row">
              <div class="adv-field">
                <label class="adv-label">Cliente Final</label>
                <input type="text" name="clientName" value="<?php echo htmlspecialchars($adv['clientName']); ?>" placeholder="Nome da empresa" class="adv-input">
              </div>
              <div class="adv-field">
                <label class="adv-label">Consultor / Vendedor</label>
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
      </div>

      <!-- COLUNA DIREITA: Chat IA -->
      <div class="chat-wrap">
      <div class="chat-card">

        <!-- Header -->
        <div class="chat-header">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="ai-avatar" style="width:34px;height:34px;border-radius:10px;font-size:12px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" style="width:18px;height:18px;">
                  <path fill-rule="evenodd" d="M9 4.5a.75.75 0 0 1 .721.544l.813 2.846a3.75 3.75 0 0 0 2.576 2.576l2.846.813a.75.75 0 0 1 0 1.442l-2.846.813a3.75 3.75 0 0 0-2.576 2.576l-.813 2.846a.75.75 0 0 1-1.442 0l-.813-2.846a3.75 3.75 0 0 0-2.576-2.576l-2.846-.813a.75.75 0 0 1 0-1.442l2.846-.813A3.75 3.75 0 0 0 7.466 7.89l.813-2.846A.75.75 0 0 1 9 4.5Z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 style="color:#fff;font-weight:700;font-size:0.88rem;line-height:1.2;">Especialista em Licenciamento</h3>
                <p style="color:rgba(153,246,228,0.8);font-size:0.7rem;margin-top:2px;font-weight:500;">TD SYNNEX · SQL Server 2022</p>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="display:flex;align-items:center;gap:5px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:4px 10px;">
                <div style="width:6px;height:6px;border-radius:50%;background:#4ade80;box-shadow:0 0 6px #4ade80;"></div>
                <span style="color:rgba(209,250,229,0.9);font-size:0.68rem;font-weight:600;letter-spacing:0.03em;">GPT-4o</span>
              </div>
              <button id="chatMobileClose" aria-label="Fechar chat" title="Minimizar chat">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="white" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Mensagens -->
        <div class="chat-messages" id="chatMessages">
          <div class="msg-container msg-assistant">
            <div class="msg-meta"><div class="ai-avatar">IA</div>Especialista</div>
            <div class="chat-bubble-assistant markdown-content">
              <strong>Olá!</strong> Sou o especialista em licenciamento SQL Server 2022 da TD SYNNEX. Como posso ajudar na sua venda hoje?
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

        <!-- Input Area -->
        <div class="chat-input-area">
          <div class="chat-input-wrap">
            <input type="text" id="chatInput" placeholder="Pergunte sobre licenciamento SQL Server..."
                   onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();handleChatBtn();}">
            <button class="chat-send-btn" id="chatSendBtn" onclick="handleChatBtn()" title="Enviar">
              <svg id="sendIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="white" style="width:16px;height:16px;transform:rotate(45deg);">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
              </svg>
              <svg id="stopIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" style="width:16px;height:16px;display:none;">
                <rect x="6" y="6" width="12" height="12" rx="2" />
              </svg>
            </button>
          </div>
        </div>

      </div> <!-- /chat-card -->
      </div> <!-- /chat-wrap -->

    </div><!-- /grid -->

  <!-- Mobile floating chat button -->
  <button id="chatFab" aria-label="Abrir chat especialista" title="Especialista em Licenciamento">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="white" style="width:26px;height:26px;">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
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
