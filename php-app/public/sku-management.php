<?php
session_start();

// Initialize i18n
require_once __DIR__ . '/../src/Shared/Services/i18n-bootstrap.php';

$jsonFile = __DIR__ . '/skus.json';

// Definição de preços padrão (fallback em BRL baseado nos valores passados)
$defaultPrices = [
    'arcStandard' => 0.539,
    'arcEnterprise' => 2.021,
    'splaStandard' => 1131.90,
    'splaEnterprise' => 4611.08,
    'csp1yStandard' => 15226.33,
    'csp1yEnterprise' => 58377.01,
    'csp3yStandard' => 38245.50,
    'csp3yEnterprise' => 146564.40,
    'perpetualStandard' => 32240.30,
    'perpetualEnterprise' => 123602.72,
    'saPct' => 25,
    'ovsStandard' => 12651.28,
    'ovsEnterprise' => 48511.95,
    'exchangeRate' => 5.39
];

// Lê o arquivo JSON
$prices = $defaultPrices;
if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    $decoded = json_decode($jsonContent, true);
    if (is_array($decoded)) {
        $prices = array_merge($defaultPrices, $decoded);
    }
}

// Quantidade de casas decimais por campo (espelha o antigo `step`)
$decimalsMap = [
    'arcStandard'         => 3,
    'arcEnterprise'       => 3,
    'splaStandard'        => 2,
    'splaEnterprise'      => 2,
    'csp1yStandard'       => 2,
    'csp1yEnterprise'     => 2,
    'csp3yStandard'       => 2,
    'csp3yEnterprise'     => 2,
    'perpetualStandard'   => 2,
    'perpetualEnterprise' => 2,
    'saPct'               => 1,
    'ovsStandard'         => 2,
    'ovsEnterprise'       => 2,
    'exchangeRate'        => 2,
];

/** Formata um valor no padrão pt-BR (1.234,56). */
function fmtBR($value, int $decimals = 2): string {
    return number_format((float)$value, $decimals, ',', '.');
}

// Processa salvamento de preços
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prices'])) {
    $newPrices = [];
    foreach ($defaultPrices as $key => $val) {
        if (isset($_POST[$key])) {
            // Aceita 1.234,56 (BR) e 1234.56 (US): remove pontos (milhar) e troca vírgula por ponto
            $raw = trim((string)$_POST[$key]);
            $raw = str_replace(['R$', ' '], '', $raw);
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
            $newPrices[$key] = is_numeric($raw) ? (float)$raw : (float)$val;
        } else {
            $newPrices[$key] = $val;
        }
    }

    // Save to JSON
    file_put_contents($jsonFile, json_encode($newPrices, JSON_PRETTY_PRINT));
    $prices = $newPrices;

    $successMessage = __('sku_mgmt.save_success');
}

/** Helper para emitir um <input> de moeda já formatado em pt-BR. */
function priceInput(string $name, array $prices, array $decimalsMap): string {
    $dec = $decimalsMap[$name] ?? 2;
    $val = fmtBR($prices[$name] ?? 0, $dec);
    return '<input type="text" inputmode="decimal" class="price-input" '
         . 'name="' . htmlspecialchars($name) . '" '
         . 'data-decimals="' . $dec . '" '
         . 'value="' . htmlspecialchars($val) . '" '
         . 'autocomplete="off" spellcheck="false">';
}
?>

<!DOCTYPE html>
<html lang="<?= getHtmlLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('pages.sku_management') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sku-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .sku-table thead th { background: #005758; color: #fff; font-size: 0.78rem; font-weight: 600; padding: 12px 16px; text-align: left; letter-spacing: 0.04em; text-transform: uppercase; position: sticky; top: 0; z-index: 10; }
        .sku-table thead th:first-child { border-radius: 12px 0 0 0; }
        .sku-table thead th:last-child { border-radius: 0 12px 0 0; }
        .sku-table tbody td { padding: 0; border-bottom: 1px solid #e8edf3; vertical-align: middle; }
        .sku-table tbody tr:hover td { background: #f0fdfa; }
        .sku-table tbody tr:last-child td:first-child { border-radius: 0 0 0 12px; }
        .sku-table tbody tr:last-child td:last-child { border-radius: 0 0 12px 0; }
        .sku-table input.price-input { width: 100%; padding: 10px 14px; border: none; background: transparent; font-size: 0.88rem; font-weight: 600; color: #1e293b; font-family: 'Inter', sans-serif; outline: none; transition: background 0.15s; text-align: right; letter-spacing: 0.01em; font-variant-numeric: tabular-nums; }
        .sku-table input.price-input:focus { background: #e0f2f1; }
        .sku-table input.price-input.is-invalid { background: #fef2f2; color: #b91c1c; }
        .model-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; }
        .badge-arc { background: #dbeafe; color: #1d4ed8; }
        .badge-spla { background: #ffedd5; color: #c2410c; }
        .badge-csp1 { background: #f3e8ff; color: #7c3aed; }
        .badge-csp3 { background: #e0e7ff; color: #4338ca; }
        .badge-perp { background: #ccfbf1; color: #0f766e; }
        .badge-ovs { background: #ffe4e6; color: #be123c; }
        .badge-fx { background: #f1f5f9; color: #475569; }
        .edition-std { color: #475569; font-weight: 500; }
        .edition-ent { color: #1e293b; font-weight: 600; }
        .unit-label { font-size: 0.72rem; color: #94a3b8; font-weight: 500; white-space: nowrap; }

        /* ===== MOBILE RESPONSIVE ===== */
        @media (max-width: 768px) {
          .container {
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
            max-width: 100% !important;
          }
          .sku-table { min-width: 520px; }
          .sku-table thead th { font-size: 0.7rem !important; padding: 10px 8px !important; }
          .sku-table input.price-input { padding: 8px 8px !important; font-size: 0.82rem !important; }
          .model-badge { font-size: 0.6rem !important; padding: 3px 6px !important; gap: 3px !important; }
          .model-badge svg { display: none; }
          .unit-label { font-size: 0.6rem !important; white-space: normal !important; }
          header h1 { font-size: 1.25rem !important; }
          header p { font-size: 0.75rem !important; }
          header .flex.items-center.gap-4 > div:first-child {
            width: 2.5rem !important;
            height: 2.5rem !important;
          }
          header .flex.items-center.gap-3 {
            width: 100% !important;
            justify-content: center !important;
          }
          .bg-white.rounded-2xl { border-radius: 12px !important; }
        }
        @media (max-width: 640px) {
          .container {
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
          }
          .save-btn-wrap {
            justify-content: stretch !important;
          }
          .save-btn-wrap button {
            width: 100% !important;
            justify-content: center !important;
          }
          header {
            gap: 0.75rem !important;
            padding-bottom: 1rem !important;
            margin-bottom: 1.25rem !important;
          }
          header .flex.items-center.gap-4 {
            gap: 0.5rem !important;
          }
        }
        @media (max-width: 400px) {
          .sku-table { min-width: 460px; }
          .sku-table thead th { font-size: 0.65rem !important; padding: 8px 6px !important; }
          .sku-table input.price-input { font-size: 0.78rem !important; padding: 6px !important; }
          header h1 { font-size: 1.1rem !important; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
    <div class="container mx-auto p-4 md:p-8 max-w-[1200px]">
        
        <!-- Header -->
        <header class="flex flex-col md:flex-row justify-between items-center mb-8 pb-6 border-b border-slate-200">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl flex items-center justify-center shadow-md" style="background:linear-gradient(135deg,#002829,#005758);">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-7 h-7 text-white">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight"><?= __('sku_mgmt.title') ?></h1>
                    <p class="text-sm text-slate-500 font-medium"><?= __('sku_mgmt.subtitle') ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-4 md:mt-0">
                <!-- Language switcher -->
                <div class="relative" id="langSwitcher">
                    <button type="button" onclick="document.getElementById('langMenu').classList.toggle('hidden')"
                            class="flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 px-3 py-2 rounded-lg hover:bg-slate-100 transition border border-slate-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802"/></svg>
                        <span id="currentLangLabel"><?php
                            $langNames = ['pt-BR' => 'Português', 'en' => 'English', 'es' => 'Español'];
                            echo $langNames[getLang()] ?? 'Português';
                        ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    </button>
                    <div id="langMenu" class="hidden absolute right-0 mt-1 w-40 bg-white rounded-lg shadow-lg border border-slate-200 py-1 z-50">
                        <?php
                        $curLang = getLang();
                        $redirect = 'sku-management.php';
                        $langOpts = ['pt-BR' => '🇧🇷 Português', 'en' => '🇺🇸 English', 'es' => '🇪🇸 Español'];
                        foreach ($langOpts as $code => $label):
                        ?>
                        <a href="set-language.php?lang=<?= urlencode($code) ?>&redirect=<?= urlencode($redirect) ?>"
                           class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-slate-100 transition <?= $curLang === $code ? 'font-bold text-teal-700 bg-teal-50' : 'text-slate-700' ?>">
                            <?= $label ?>
                            <?php if ($curLang === $code): ?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 ml-auto"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg><?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <a href="home.php" class="text-sm font-medium text-slate-600 hover:text-slate-900 px-3 py-2 rounded-lg hover:bg-slate-100 transition"><?= __('sku_mgmt.home') ?></a>
                <a href="sql-advisor.php" class="text-sm font-medium text-white px-4 py-2 rounded-lg shadow transition" style="background:#005758;"><?= __('sku_mgmt.back_advisor') ?></a>
            </div>
        </header>

        <?php if (isset($successMessage)): ?>
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-emerald-600 flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <span class="text-emerald-800 font-medium text-sm"><?php echo $successMessage; ?></span>
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="save_prices" value="1">

            <div class="bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
                <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table class="sku-table">
                    <thead>
                        <tr>
                            <th style="width:200px;"><?= __('sku_mgmt.col_model') ?></th>
                            <th style="width:100px;"><?= __('sku_mgmt.col_edition') ?></th>
                            <th><?= __('sku_mgmt.col_value') ?></th>
                            <th style="width:180px;"><?= __('sku_mgmt.col_unit') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Azure ARC -->
                        <tr>
                            <td rowspan="2" style="padding:12px 16px;">
                                <span class="model-badge badge-arc">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:12px;height:12px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 .75-7.425A4.502 4.502 0 0 0 14.25 9 4.5 4.5 0 0 0 6 10.5a4.5 4.5 0 0 0-3.75 4.5Z"/></svg>
                                    <?= __('sku_mgmt.badge_arc') ?>
                                </span>
                                <div class="text-xs text-slate-400 mt-1"><?= __('sku_mgmt.badge_arc_sub') ?></div>
                            </td>
                            <td style="padding:0 16px;"><span class="edition-std"><?= __('sku_mgmt.edition_standard') ?></span></td>
                            <td><?= priceInput('arcStandard', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_vcore_hour') ?></span></td>
                        </tr>
                        <tr>
                            <td style="padding:0 16px;"><span class="edition-ent"><?= __('sku_mgmt.edition_enterprise') ?></span></td>
                            <td><?= priceInput('arcEnterprise', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_vcore_hour') ?></span></td>
                        </tr>

                        <!-- SPLA -->
                        <tr>
                            <td rowspan="2" style="padding:12px 16px;">
                                <span class="model-badge badge-spla">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:12px;height:12px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                                    <?= __('sku_mgmt.badge_spla') ?>
                                </span>
                                <div class="text-xs text-slate-400 mt-1"><?= __('sku_mgmt.badge_spla_sub') ?></div>
                            </td>
                            <td style="padding:0 16px;"><span class="edition-std"><?= __('sku_mgmt.edition_standard') ?></span></td>
                            <td><?= priceInput('splaStandard', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_pack_month') ?></span></td>
                        </tr>
                        <tr>
                            <td style="padding:0 16px;"><span class="edition-ent"><?= __('sku_mgmt.edition_enterprise') ?></span></td>
                            <td><?= priceInput('splaEnterprise', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_pack_month') ?></span></td>
                        </tr>

                        <!-- CSP 1 Ano -->
                        <tr>
                            <td rowspan="2" style="padding:12px 16px;">
                                <span class="model-badge badge-csp1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:12px;height:12px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                                    <?= __('sku_mgmt.badge_csp1') ?>
                                </span>
                                <div class="text-xs text-slate-400 mt-1"><?= __('sku_mgmt.badge_csp1_sub') ?></div>
                            </td>
                            <td style="padding:0 16px;"><span class="edition-std"><?= __('sku_mgmt.edition_standard') ?></span></td>
                            <td><?= priceInput('csp1yStandard', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_pack_year') ?></span></td>
                        </tr>
                        <tr>
                            <td style="padding:0 16px;"><span class="edition-ent"><?= __('sku_mgmt.edition_enterprise') ?></span></td>
                            <td><?= priceInput('csp1yEnterprise', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_pack_year') ?></span></td>
                        </tr>

                        <!-- CSP 3 Anos -->
                        <tr>
                            <td rowspan="2" style="padding:12px 16px;">
                                <span class="model-badge badge-csp3">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:12px;height:12px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z"/></svg>
                                    <?= __('sku_mgmt.badge_csp3') ?>
                                </span>
                                <div class="text-xs text-slate-400 mt-1"><?= __('sku_mgmt.badge_csp3_sub') ?></div>
                            </td>
                            <td style="padding:0 16px;"><span class="edition-std"><?= __('sku_mgmt.edition_standard') ?></span></td>
                            <td><?= priceInput('csp3yStandard', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_pack_3year') ?></span></td>
                        </tr>
                        <tr>
                            <td style="padding:0 16px;"><span class="edition-ent"><?= __('sku_mgmt.edition_enterprise') ?></span></td>
                            <td><?= priceInput('csp3yEnterprise', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_pack_3year') ?></span></td>
                        </tr>

                        <!-- Perpétuo -->
                        <tr>
                            <td rowspan="3" style="padding:12px 16px;">
                                <span class="model-badge badge-perp">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:12px;height:12px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                                    <?= __('sku_mgmt.badge_perp') ?>
                                </span>
                                <div class="text-xs text-slate-400 mt-1"><?= __('sku_mgmt.badge_perp_sub') ?></div>
                            </td>
                            <td style="padding:0 16px;"><span class="edition-std"><?= __('sku_mgmt.edition_standard') ?></span></td>
                            <td><?= priceInput('perpetualStandard', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_pack_license') ?></span></td>
                        </tr>
                        <tr>
                            <td style="padding:0 16px;"><span class="edition-ent"><?= __('sku_mgmt.edition_enterprise') ?></span></td>
                            <td><?= priceInput('perpetualEnterprise', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_pack_license') ?></span></td>
                        </tr>
                        <tr>
                            <td style="padding:0 16px;"><span class="edition-std" style="color:#0f766e;font-weight:600;"><?= __('sku_mgmt.sa_pct') ?></span></td>
                            <td><?= priceInput('saPct', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_sa_pct') ?></span></td>
                        </tr>

                        <!-- OVS -->
                        <tr>
                            <td rowspan="2" style="padding:12px 16px;">
                                <span class="model-badge badge-ovs">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:12px;height:12px;"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3"/></svg>
                                    <?= __('sku_mgmt.badge_ovs') ?>
                                </span>
                                <div class="text-xs text-slate-400 mt-1"><?= __('sku_mgmt.badge_ovs_sub') ?></div>
                            </td>
                            <td style="padding:0 16px;"><span class="edition-std"><?= __('sku_mgmt.edition_standard') ?></span></td>
                            <td><?= priceInput('ovsStandard', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_pack_year') ?></span></td>
                        </tr>
                        <tr>
                            <td style="padding:0 16px;"><span class="edition-ent"><?= __('sku_mgmt.edition_enterprise') ?></span></td>
                            <td><?= priceInput('ovsEnterprise', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_pack_year') ?></span></td>
                        </tr>

                        <!-- Câmbio -->
                        <tr style="background:#f8fafc;">
                            <td style="padding:12px 16px;">
                                <span class="model-badge badge-fx">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:12px;height:12px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    <?= __('sku_mgmt.badge_fx') ?>
                                </span>
                                <div class="text-xs text-slate-400 mt-1"><?= __('sku_mgmt.badge_fx_sub') ?></div>
                            </td>
                            <td style="padding:0 16px;"><span class="edition-std" style="color:#475569;font-weight:600;"><?= __('sku_mgmt.fx_rate') ?></span></td>
                            <td><?= priceInput('exchangeRate', $prices, $decimalsMap) ?></td>
                            <td style="padding:0 16px;"><span class="unit-label"><?= __('sku_mgmt.unit_usd_brl') ?></span></td>
                        </tr>
                    </tbody>
                </table>
                </div><!-- /overflow-x-auto -->
            </div>

            <div class="save-btn-wrap flex justify-end pt-6 mt-6 mb-10">
                <button type="submit" class="px-8 py-3 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all" style="background:linear-gradient(135deg,#002829,#005758);">
                    <span class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3" />
                        </svg>
                        <?= __('sku_mgmt.save_button') ?>
                    </span>
                </button>
            </div>
            
        </form>
    </div>

    <script>
    // ============================================================
    // M\u00e1scara pt-BR para inputs de pre\u00e7o (1.234,56)
    // ============================================================
    (function () {
        const inputs = document.querySelectorAll('input.price-input');

        // Converte string BR ("1.234,56") ou US ("1234.56") para Number, ou null se inv\u00e1lido
        function parseBR(str) {
            if (str === null || str === undefined) return null;
            let s = String(str).trim().replace(/[R$\s]/g, '');
            if (s === '') return null;
            // Se tem v\u00edrgula -> formato BR: remove pontos (milhar) e troca v\u00edrgula por ponto
            if (s.indexOf(',') !== -1) {
                s = s.replace(/\./g, '').replace(',', '.');
            } else {
                // Sem v\u00edrgula: pode ser US "1234.56" ou BR sem decimal "1.234"
                // Heur\u00edstica: se tem mais de um ponto, ou ponto seguido por exatamente 3 d\u00edgitos no final, \u00e9 BR
                const dots = (s.match(/\./g) || []).length;
                if (dots > 1) {
                    s = s.replace(/\./g, '');
                } else if (dots === 1) {
                    const after = s.split('.')[1];
                    if (after && after.length === 3 && /^\d+$/.test(after)) {
                        // 1.234 -> milhar BR
                        s = s.replace('.', '');
                    }
                    // sen\u00e3o mant\u00e9m como decimal US
                }
            }
            const n = parseFloat(s);
            return isFinite(n) ? n : null;
        }

        function formatBR(value, decimals) {
            return Number(value).toLocaleString('pt-BR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        inputs.forEach(input => {
            const decimals = parseInt(input.dataset.decimals || '2', 10);

            // Permite apenas d\u00edgitos, ponto, v\u00edrgula e teclas de controle
            input.addEventListener('keydown', e => {
                const allowed = ['Backspace','Delete','Tab','Escape','Enter','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End'];
                if (allowed.includes(e.key)) return;
                if ((e.ctrlKey || e.metaKey) && ['a','c','v','x','z'].includes(e.key.toLowerCase())) return;
                if (!/^[\d.,]$/.test(e.key)) {
                    e.preventDefault();
                }
            });

            // Limpa caracteres inv\u00e1lidos colados
            input.addEventListener('input', () => {
                const cleaned = input.value.replace(/[^\d.,]/g, '');
                if (cleaned !== input.value) input.value = cleaned;
                input.classList.remove('is-invalid');
            });

            // Ao focar: mostra apenas o n\u00famero "cru" (mais f\u00e1cil de editar)
            input.addEventListener('focus', () => {
                const n = parseBR(input.value);
                if (n !== null) {
                    // Mostra com v\u00edrgula como decimal, sem pontos de milhar
                    input.value = n.toFixed(decimals).replace('.', ',');
                }
                // Seleciona tudo para permitir digita\u00e7\u00e3o r\u00e1pida
                requestAnimationFrame(() => input.select());
            });

            // Ao sair: formata em pt-BR completo
            input.addEventListener('blur', () => {
                const n = parseBR(input.value);
                if (n === null) {
                    input.classList.add('is-invalid');
                    return;
                }
                input.value = formatBR(n, decimals);
            });
        });

        // Antes do submit: garante que tudo est\u00e1 v\u00e1lido
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', e => {
                let invalid = false;
                inputs.forEach(input => {
                    if (parseBR(input.value) === null) {
                        input.classList.add('is-invalid');
                        invalid = true;
                    }
                });
                if (invalid) {
                    e.preventDefault();
                    alert(<?= json_encode(__('sku_mgmt.invalid_alert')) ?>);
                }
            });
        }
    })();

    // Fecha o menu de idioma ao clicar fora
    document.addEventListener('click', function (e) {
        const sw = document.getElementById('langSwitcher');
        const menu = document.getElementById('langMenu');
        if (sw && menu && !sw.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });
    </script>
</body>
</html>
