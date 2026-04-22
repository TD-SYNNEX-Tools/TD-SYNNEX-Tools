<?php
declare(strict_types=1);
session_start();
// Helpers de tipo de migracao
function migrationLabel(string $type): string {
    return $type === 'mpn_csp' ? 'MPN &rarr; CSP' : 'MOSP &rarr; CSP';
}
function migrationQtyLabel(string $type): string {
    return $type === 'mpn_csp' ? 'UsageQuantity' : 'Quantity';
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Features\AzureMigration\Services\FinancialAnalyzer;
use App\Features\AzureMigration\Services\MicrosoftPricingApi;
use App\Features\AzureMigration\Services\AzureResourceAnalyzer;
use App\Shared\Services\ServerLogger;

$uploadDir = __DIR__ . '/../uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$error   = null;
$success = null;
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'analyze';

    if ($action === 'analyze') {
        // Determinar a origem: upload direto (file) ou chunked (uploadId na sessão)
        $tmpPath  = null;
        $origName = '';
        $fileOk   = false;

        $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['uploadId'] ?? ''));

        if ($uploadId !== '') {
            // ── CHUNKED UPLOAD ──
            // 1) Tentar sessão
            if (!empty($_SESSION['chunkedUpload']) && $_SESSION['chunkedUpload']['uploadId'] === $uploadId) {
                $chunked  = $_SESSION['chunkedUpload'];
                $tmpPath  = $chunked['filePath'];
                $origName = $chunked['fileName'];
                unset($_SESSION['chunkedUpload']);
            } else {
                // 2) Fallback: procurar arquivo pelo uploadId no disco (sessão pode ter sido perdida)
                $candidatePath = $uploadDir . '/cost_' . $uploadId . '.csv';
                if (file_exists($candidatePath)) {
                    $tmpPath  = $candidatePath;
                    $origName = (string)($_POST['fileName'] ?? 'upload.csv');
                }
            }

            if ($tmpPath && file_exists($tmpPath)) {
                if (strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'csv') {
                    $error = 'Apenas arquivos CSV sao suportados.';
                    @unlink($tmpPath);
                } else {
                    $fileOk = true;
                }
            } else {
                $error = 'Arquivo do upload chunked nao encontrado (uploadId: ' . htmlspecialchars($uploadId) . '). Tente novamente.';
            }
        } elseif (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
            // ── UPLOAD DIRETO (arquivo pequeno, sem chunks) ──
            $file = $_FILES['file'];
            $ext  = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Erro no upload: codigo ' . (int)$file['error'];
            } elseif ($ext !== 'csv') {
                $error = 'Apenas arquivos CSV sao suportados.';
            } elseif ($file['size'] > 200 * 1024 * 1024) {
                $error = 'Arquivo muito grande (maximo 200 MB).';
            } else {
                $tmpPath  = $uploadDir . '/cost_' . uniqid() . '.csv';
                $origName = (string)$file['name'];
                if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
                    $error = 'Falha ao salvar arquivo temporario.';
                } else {
                    $fileOk = true;
                }
            }
        } else {
            $error = 'Nenhum arquivo recebido. Selecione um CSV.';
        }

        if ($fileOk && $tmpPath) {
            set_time_limit(300);
            $exchangeRate  = max(0.01, (float)($_POST['exchangeRate'] ?? 5.39));
            $clientName    = htmlspecialchars(trim((string)($_POST['clientName']     ?? '')), ENT_QUOTES);
            $refMonth      = htmlspecialchars(trim((string)($_POST['referenceMonth'] ?? '')), ENT_QUOTES);
            
            // Carregar schemas e determinar coluna de quantidade
            $schemaConfig = require __DIR__ . '/../src/Shared/Config/dataset-schemas.php';
            $schemaKey    = preg_replace('/[^a-z0-9_-]/', '', (string)($_POST['schemaKey'] ?? 'mosa_2019-11-01'));
            $selectedSchema = $schemaConfig['schemas'][$schemaKey] ?? $schemaConfig['schemas']['mosa_2019-11-01'];
            $qtyColumn     = $selectedSchema['quantityCol'] ?? 'quantity';
            
            // Determinar migrationType baseado no agreement (para compatibilidade)
            $agreement = $selectedSchema['agreement'] ?? 'MOSA';
            $migrationType = in_array($agreement, ['MPA', 'CSP']) ? 'mpn_csp' : 'mosp_csp';

            $analyzer    = new FinancialAnalyzer();
            // Limpa cache de preços da API para forçar re-consultas (evita null persistente)
            (new MicrosoftPricingApi())->clearCache();

            $srvLog = ServerLogger::getInstance();
            $srvLog->clear();
            $srvLog->info('Sistema', 'Iniciando analise financeira', [
                'arquivo' => $origName,
                'schema'  => $schemaKey,
                'cambio'  => $exchangeRate,
                'cliente' => $clientName ?: '(nao informado)',
            ]);

            $parseResult = $analyzer->parseFile($tmpPath, $qtyColumn);

            if (!$parseResult['success']) {
                $error = $parseResult['error'];
            } else {
                $results = $analyzer->analyze($parseResult['data'], $exchangeRate);
                $results['clientName']     = $clientName;
                $results['referenceMonth'] = $refMonth;
                $results['schemaKey']      = $schemaKey;
                $results['schemaVersion']  = $selectedSchema['version'] ?? '';
                $results['schemaAgreement']= $agreement;
                $results['migrationType']  = $migrationType;
                $results['filename']       = htmlspecialchars($origName, ENT_QUOTES);

                // --- Análise técnica: extrair ResourceType do ResourceId ---
                $uniqueResources = [];
                foreach ($results['results'] as $row) {
                    $resId = $row['resourceId'] ?? '';
                    $resType = '';
                    if (preg_match('#/providers/([^/]+/[^/]+)#i', $resId, $m)) {
                        $resType = $m[1];
                    }
                    if ($resType !== '' && !isset($uniqueResources[strtolower($resType)])) {
                        $uniqueResources[strtolower($resType)] = [
                            'ResourceType'    => $resType,
                            'ResourceName'    => $row['resourceName'] ?? '',
                            'ResourceGroup'   => $row['resourceGroup'] ?? '',
                            'Location'        => $row['resourceLocation'] ?? '',
                            'Subscription'    => $row['subscriptionName'] ?? '',
                            'SubscriptionId'  => $row['subscriptionId'] ?? '',
                        ];
                    }
                }
                if (!empty($uniqueResources)) {
                    $migAnalyzer = new AzureResourceAnalyzer();
                    $techResults = $migAnalyzer->analyzeResources(array_values($uniqueResources));
                    $results['technicalAnalysis'] = $techResults;
                    $migLookup = [];
                    foreach ($techResults['results'] as $mr) {
                        $migLookup[strtolower($mr['resourceType'])] = $mr;
                    }
                    $results['migrationLookup'] = $migLookup;
                }

                $_SESSION['financialResults'] = $results;
                $cnt = $results['summary']['totalRows'];
                $uid = $results['summary']['uniqueMeterIds'];
                $success = "Analise concluida! {$cnt} linhas processadas, {$uid} MeterIDs unicos consultados na API.";
            }

            @unlink($tmpPath);
        }
    } elseif ($action === 'export_csv') {
        $results = $_SESSION['financialResults'] ?? null;
        if ($results) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="analise_financeira_' . date('Y-m-d') . '.csv"');
            header('Cache-Control: no-cache, no-store');
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Recurso','Servico','Resource Group','MeterID','Quantidade','Unidade','Preco MOSP (USD)','Custo MOSP (USD)','Preco CSP (USD)','Custo CSP (USD)','Diferenca (USD)','Variacao %','Status API']);
            foreach ($results['results'] as $r) {
                fputcsv($out, [
                    $r['resourceName'],
                    $r['meterCategory'] ?: $r['serviceFamily'],
                    $r['resourceGroup'],
                    $r['meterId'],
                    $r['quantity'],
                    $r['unitOfMeasure'],
                    number_format($r['unitPriceMosp'],  6, '.', ''),
                    number_format($r['costMosp'],       4, '.', ''),
                    $r['unitPriceCsp'] !== null ? number_format($r['unitPriceCsp'], 6, '.', '') : 'N/D',
                    $r['costCsp']      !== null ? number_format($r['costCsp'],      4, '.', '') : 'N/D',
                    $r['difference']   !== null ? number_format($r['difference'],   4, '.', '') : 'N/D',
                    $r['differencePercent'] !== null ? number_format($r['differencePercent'], 2) . '%' : 'N/D',
                    $r['priceFound'] ? 'Encontrado' : 'Nao encontrado na API',
                ]);
            }
            fclose($out);
            exit;
        }
        $results = null;
    } elseif ($action === 'new_analysis') {
        unset($_SESSION['financialResults']);
        header('Location: analise-financeira.php');
        exit;
    }
} else {
    $results = $_SESSION['financialResults'] ?? null;
}

// ---- Helpers de formatacao ----
function fmtUsd(float $v): string { return '$' . number_format($v, 2, ',', '.'); }
function fmtBrl(float $v): string { return "R$\u{00A0}" . number_format($v, 2, ',', '.'); }
function fmtPct(float $v, bool $sign = true): string {
    $s = $sign && $v > 0 ? '+' : '';
    return $s . number_format($v, 1, ',', '.') . '%';
}
function diffClass(float $v): string { return $v < -0.001 ? 'text-success' : ($v > 0.001 ? 'text-danger' : 'text-muted'); }
function diffBg(float $v): string    { return $v < -0.001 ? 'var(--td-green-light)' : ($v > 0.001 ? 'var(--td-red-light)' : '#f8fafc'); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analise Financeira para Migracao Azure - TD SYNNEX Tools</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --td-teal:        #005758;
            --td-blue:        #0097D7;
            --td-green:       #82C341;
            --td-yellow:      #FFD100;
            --td-red:         #D9272E;
            --td-dark:        #1e293b;
            --td-gray:        #64748b;
            --td-green-light: #f0fdf4;
            --td-red-light:   #fff1f2;
        }
        body { background: #f8fafc; font-family: 'Inter','Segoe UI',sans-serif; }
        .app-header {
            background:#fff; border-bottom:1px solid #e2e8f0;
            padding:.875rem 2rem; display:flex;
            justify-content:space-between; align-items:center; margin-bottom:2rem;
        }
        .fin-card {
            background:#fff; border:none; border-radius:10px;
            box-shadow:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
            margin-bottom:1.25rem;
        }
        .fin-card .card-header {
            background:#fff; border-bottom:1px solid #f1f5f9;
            padding:1rem 1.25rem; font-weight:600; font-size:.9rem;
            color:var(--td-dark); border-radius:10px 10px 0 0 !important;
            display:flex; align-items:center;
        }
        .fin-card .card-header i { color:var(--td-teal); font-size:1rem; }
        .upload-zone {
            border:2px dashed #cbd5e1; border-radius:8px; padding:36px 16px;
            text-align:center; cursor:pointer; transition:all .2s; background:#f8fafc;
        }
        .upload-zone:hover,.upload-zone.dragover {
            border-color:var(--td-teal); background:rgba(0,87,88,.04);
        }
        .btn-teal { background:var(--td-teal); color:#fff; border:none; }
        .btn-teal:hover { background:#003031; color:#fff; }
        .stat-box {
            background:#fff; border-radius:10px; padding:1.25rem 1rem;
            box-shadow:0 1px 3px rgba(0,0,0,.06); border-top:4px solid #e2e8f0;
            text-align:center; height:100%;
        }
        .stat-box.mosp   { border-top-color:#64748b; }
        .stat-box.csp    { border-top-color:var(--td-teal); }
        .stat-box.tax    { border-top-color:#0097D7; }
        .stat-box.diff   { border-top-color:var(--td-green); }
        .stat-box.diff.negative { border-top-color:var(--td-red); }
        .stat-box .stat-value { font-size:1.5rem; font-weight:800; line-height:1.1; color:var(--td-dark); }
        .stat-box .stat-brl   { font-size:.85rem; color:var(--td-gray); font-weight:500; margin-top:2px; }
        .stat-box .stat-label { font-size:.72rem; text-transform:uppercase; color:var(--td-gray);
            letter-spacing:.5px; margin-top:.5rem; border-top:1px solid #f1f5f9;
            padding-top:.5rem; font-weight:600; }
        .breakdown-bar {
            height:10px; border-radius:4px; background:#e2e8f0;
            overflow:hidden; margin-top:4px;
        }
        .breakdown-bar-fill { height:100%; border-radius:4px; background:var(--td-teal); transition:width .4s; }
        .table-fin th { background:var(--td-teal); color:#fff; font-size:.78rem;
            text-transform:uppercase; letter-spacing:.4px; white-space:nowrap; }
        .table-fin td { font-size:.82rem; vertical-align:middle; }
        .badge-found     { background:#d1fae5; color:#065f46; font-size:.7rem; padding:2px 7px; border-radius:20px; font-weight:600; }
        .badge-partial   { background:#fef9c3; color:#854d0e; }
        .badge-fallback  { background:#ffedd5; color:#9a3412; }
        .badge-not-found { background:#fee2e2; color:#991b1b; font-size:.7rem; padding:2px 7px; border-radius:20px; font-weight:600; }
        .vchk { display:inline-flex;align-items:center;gap:2px;font-size:.68rem;font-weight:600;
                border-radius:4px;padding:1px 5px;white-space:nowrap; }
        .vchk-ok  { background:#dcfce7;color:#15803d; }
        .vchk-err { background:#fee2e2;color:#b91c1c; }
        .vchk-na  { background:#f1f5f9;color:#94a3b8; }
        .sub-id   { font-family:monospace;font-size:.68rem;color:#94a3b8; }
        .meter-id { font-family:monospace; font-size:.72rem; color:var(--td-gray); word-break:break-all; }
        .diff-positive { color:var(--td-red)!important;   font-weight:600; }
        .diff-negative { color:#16a34a!important; font-weight:600; }
        /* ── Resizable columns ── */
        .table-fin { table-layout:fixed; width:100%; }
        .table-fin thead th { position:relative; overflow:hidden; user-select:none; }
        .col-resize-handle {
            position:absolute; right:0; top:0; bottom:0; width:6px;
            cursor:col-resize; background:transparent; z-index:2;
        }
        .col-resize-handle:hover, .col-resize-handle.active {
            background:rgba(255,255,255,0.35);
        }
        @keyframes spin-anim { to { transform:rotate(360deg); } }
        .spin-icon { animation:spin-anim 1s linear infinite; display:inline-block; }
        /* ── Analysis Tabs ── */
        .analysis-tabs {
            border-bottom:2px solid #e2e8f0; padding:0; gap:0; list-style:none;
            display:flex; margin:0;
        }
        .analysis-tabs .nav-item { margin:0; }
        .analysis-tabs .nav-link {
            border:none; background:none; padding:10px 20px; font-size:.85rem;
            font-weight:600; color:#94a3b8; border-bottom:2px solid transparent;
            margin-bottom:-2px; cursor:pointer; transition:all .15s;
            display:inline-flex; align-items:center;
        }
        .analysis-tabs .nav-link:hover { color:#005758; }
        .analysis-tabs .nav-link.active { color:#005758; border-bottom-color:#005758; }
        .mig-status-badge {
            display:inline-flex;align-items:center;gap:3px;font-size:.75rem;
            font-weight:600;padding:2px 8px;border-radius:12px;white-space:nowrap;
        }
        .mig-bar { display:flex;gap:2px;height:20px;border-radius:6px;overflow:hidden; }
        .mig-bar > div { transition:width .3s; min-width:2px; }
        .stat-box[style*="cursor:pointer"]:hover { transform:translateY(-2px);transition:transform .15s; }
        .stat-box.tech-active { box-shadow:0 0 0 2px #005758; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../templates/topbar.php'; ?>

<div class="container-fluid px-4" style="max-width:1400px;margin:0 auto;">

    <nav aria-label="breadcrumb" class="mb-3" style="margin-top:16px;">
        <ol class="breadcrumb" style="font-size:.82rem;">
            <li class="breadcrumb-item"><a href="home.php" style="color:var(--td-teal);text-decoration:none;" data-i18n="breadcrumb.home">Home</a></li>
            <li class="breadcrumb-item active" data-i18n="breadcrumb.financial">Analise Financeira</li>
        </ol>
    </nav>

    <?php
        $activeMigType = $results['migrationType'] ?? 'mosp_csp';
        $activeMigLabel = migrationLabel($activeMigType);
        $activeQtyLabel = migrationQtyLabel($activeMigType);
        // Schema info
        $schemaConfig = require __DIR__ . '/../src/Shared/Config/dataset-schemas.php';
        $activeSchemaKey = $results['schemaKey'] ?? 'mosa_2019-11-01';
        $activeSchema = $schemaConfig['schemas'][$activeSchemaKey] ?? $schemaConfig['schemas']['mosa_2019-11-01'];
        $activeSchemaLabel = $activeSchema['agreement'] . ' ' . $activeSchema['version'];
        $activeQtyLabel = ucfirst($activeSchema['quantityCol']);
    ?>
    <div class="mb-4 d-flex align-items-center gap-3">
        <div style="background:#ccfbf1;padding:10px;border-radius:8px;">
            <i class="bi bi-cash-coin" style="color:var(--td-teal);font-size:1.4rem;"></i>
        </div>
        <div>
            <h2 class="mb-0" style="font-size:1.5rem;font-weight:700;color:#1e293b;">
                <span data-i18n="title.main">Analise Financeira para Migracao Azure</span>
            </h2>
            <p class="mb-0" style="font-size:.85rem;color:#64748b;">
                <span data-i18n="title.description">Upload do export do Cost Management</span>
            </p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius:8px;font-size:.88rem;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius:8px;font-size:.88rem;">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">

        <!-- ===== UPLOAD FORM ===== -->
        <div class="col-lg-3 mb-4">
            <div class="fin-card">
                <div class="card-header"><i class="bi bi-upload me-2"></i>Upload do Export</div>
                <div class="card-body p-3">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="action" value="analyze">
                        <input type="hidden" name="uploadId" id="uploadIdField" value="">
                        <input type="hidden" name="fileName" id="fileNameField" value="">

                        <?php
                        // Carregar schemas disponíveis
                        $schemaConfig = require __DIR__ . '/../src/Shared/Config/dataset-schemas.php';
                        $schemas = $schemaConfig['schemas'];
                        $agreements = $schemaConfig['agreements'];
                        
                        // Agrupar schemas por acordo (agreement)
                        $schemasByAgreement = [];
                        foreach ($schemas as $key => $cfg) {
                            $schemasByAgreement[$cfg['agreement']][$key] = $cfg;
                        }
                        
                        // Schema selecionado (padrão: MOSA 2019-11-01 para MOSP→CSP)
                        $selectedSchema = $results['schemaKey'] ?? 'mosa_2019-11-01';
                        ?>

                        <!-- Dataset Schema -->
                        <div class="mb-3" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 12px;">
                            <label class="form-label" style="font-size:.82rem;font-weight:700;color:#0369a1;margin-bottom:6px;display:flex;align-items:center;gap:5px;">
                                <i class="bi bi-database"></i> Dataset Schema
                            </label>
                            <select class="form-select form-select-sm" name="schemaKey" id="schemaKey" onchange="updateSchemaHint()">
                                <?php foreach ($schemasByAgreement as $agreement => $schemaList): ?>
                                <optgroup label="<?= htmlspecialchars($agreements[$agreement]) ?>">
                                    <?php foreach ($schemaList as $key => $cfg): ?>
                                    <option value="<?= $key ?>" <?= $selectedSchema === $key ? 'selected' : '' ?>
                                            data-qty="<?= $cfg['quantityCol'] ?>"
                                            data-version="<?= $cfg['version'] ?>"
                                            data-agreement="<?= $cfg['agreement'] ?>"
                                            <?= $cfg['isLatest'] ? 'data-latest="1"' : '' ?>>
                                        <?= $cfg['isLatest'] ? '★ ' : '' ?><?= $cfg['version'] ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>


                        </div>

                        <!-- Tipo de Migração (legacy - agora controlado pelo schema) -->
                        <input type="hidden" name="migrationType" id="migrationType" value="<?= ($results['migrationType'] ?? 'mosp_csp') ?>">

                        <div class="mb-3">
                            <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;" data-i18n="form.clientName">Cliente (opcional)</label>
                            <input type="text" class="form-control form-control-sm" name="clientName"
                                   value="<?= htmlspecialchars($results['clientName'] ?? '') ?>"
                                   placeholder="Ex: Empresa XYZ">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">Cambio USD &rarr; BRL</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">R$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="exchangeRate"
                                       value="<?= htmlspecialchars((string)($results['summary']['exchangeRate'] ?? '5.39')) ?>">
                            </div>
                        </div>

                        <div class="upload-zone mb-3" id="dropZone" onclick="document.getElementById('fileInput').click()">
                            <i class="bi bi-file-earmark-bar-graph" style="font-size:2rem;color:#94a3b8;"></i>
                            <p class="mt-2 mb-1" style="font-size:.85rem;color:#475569;">Arraste o CSV ou clique</p>
                            <small class="text-muted" style="font-size:.75rem;">Formato: .csv (Cost Management Export)</small>
                            <input type="file" id="fileInput" name="file" accept=".csv" class="d-none"
                                   onchange="handleFile(this)">
                        </div>

                        <div id="selectedFile" class="alert alert-info d-none p-2 mb-2" style="font-size:.82rem;">
                            <i class="bi bi-file-earmark me-1"></i><span id="fileName"></span>
                        </div>

                        <button type="submit" class="btn btn-teal btn-sm w-100" id="analyzeBtn" disabled>
                            <i class="bi bi-search me-1"></i><span data-i18n="form.analyze">Calcular Custos CSP</span>
                        </button>

                        <!-- Progress bar de upload chunked -->
                        <div id="uploadProgress" class="d-none mt-2">
                            <div style="background:#e2e8f0;border-radius:6px;height:22px;overflow:hidden;position:relative;">
                                <div id="uploadProgressBar" style="background:linear-gradient(90deg,#005758,#0097D7);height:100%;width:0%;border-radius:6px;transition:width .2s;"></div>
                                <span id="uploadProgressText" style="position:absolute;top:0;left:0;right:0;text-align:center;font-size:.72rem;font-weight:700;color:#fff;line-height:22px;">0%</span>
                            </div>
                            <div id="uploadProgressDetail" style="font-size:.72rem;color:#64748b;margin-top:4px;text-align:center;"></div>
                        </div>
                    </form>

                    <hr style="margin:14px 0;">
                    <!-- Impostos -->
                    <div class="mb-2">
                        <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">
                            <i class="bi bi-receipt me-1" style="color:var(--td-teal);"></i>Impostos (%)
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="taxInput" step="0.01" min="0" max="999"
                                   class="form-control" value="0"
                                   oninput="recalcTable()">
                            <span class="input-group-text">%</span>
                        </div>

                    </div>

                    <!-- Markup -->
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">
                            <i class="bi bi-percent me-1" style="color:var(--td-teal);"></i>Markup (%)
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="markupInput" step="0.1" min="0" max="500"
                                   class="form-control" value="0"
                                   oninput="recalcTable()">
                            <span class="input-group-text">%</span>
                        </div>

                    </div>

                    <hr style="margin:14px 0;">
                    <p style="font-size:.78rem;font-weight:600;color:#374151;margin-bottom:6px;">
                        <i class="bi bi-info-circle me-1" style="color:var(--td-teal);"></i>Colunas usadas na validação:
                    </p>
                    <ul style="font-size:.76rem;color:#64748b;padding-left:1.2rem;margin-bottom:8px;">
                        <li><code>MeterID</code> &mdash; ID do medidor Azure</li>
                        <li><code id="qtyColDisplay">Quantity</code> <span id="qtyColHint">(MOSA/EA/MCA)</span> ou <code>UsageQuantity</code> <span>(MPA/CSP)</span></li>
                        <li><code>UnitOfMeasure</code> &mdash; Unidade de medida</li>
                        <li><code>MeterName</code> &mdash; Nome do medidor</li>
                        <li><code>ServiceName</code> <span style="color:#94a3b8;">ou MeterCategory/ConsumedService</span></li>
                        <li><code>ResourceLocation</code> <span style="color:#94a3b8;">ou Location</span> &mdash; Região Azure</li>
                    </ul>
                    <p style="font-size:.74rem;color:#94a3b8;">
                        Schema: <a href="https://learn.microsoft.com/en-us/azure/cost-management-billing/dataset-schema/schema-index" target="_blank" style="color:var(--td-teal);">Cost Management Dataset Reference</a>
                    </p>

                    <a href="assets/examples/exemplo-cost-management.csv" class="btn btn-sm btn-outline-secondary w-100" style="font-size:.78rem;">
                        <i class="bi bi-download me-1"></i>Baixar CSV de Exemplo
                    </a>
                </div>
            </div>
        </div>

        <!-- ===== RESULTS ===== -->
        <div class="col-lg-9">
        <?php if ($results): ?>
            <?php $s = $results['summary']; ?>

            <!-- Info bar -->
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3" style="font-size:.82rem;color:#64748b;">
                <?php if (!empty($results['schemaAgreement']) && !empty($results['schemaVersion'])): ?>
                <span style="background:#f0f9ff;color:#0369a1;font-weight:600;padding:2px 8px;border-radius:12px;font-size:.76rem;border:1px solid #bae6fd;">
                    <i class="bi bi-database me-1"></i><?= htmlspecialchars($results['schemaAgreement']) ?> <?= htmlspecialchars($results['schemaVersion']) ?>
                </span>
                <?php endif; ?>
                <?php if ($results['clientName']): ?>
                <span><i class="bi bi-building me-1"></i><strong><?= htmlspecialchars($results['clientName']) ?></strong></span>
                <?php endif; ?>
                <?php if (!empty($results['filename'])): ?>
                <span class="text-muted">|</span>
                <span><i class="bi bi-file-earmark-text me-1"></i><?= htmlspecialchars($results['filename']) ?></span>
                <?php endif; ?>
                <span class="text-muted">|</span>
                <span><i class="bi bi-hash me-1"></i><?= number_format($s['totalRows']) ?> linhas &middot; <?= $s['uniqueMeterIds'] ?> MeterIDs unicos</span>
            </div>

            <!-- Stat Cards -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-4">
                    <div class="stat-box csp">
                        <div class="stat-value" id="cardTotalFob"><?= fmtUsd($s['totalCsp']) ?></div>
                        <div class="stat-brl" id="cardTotalFobBrl"><?= fmtBrl($s['totalCspBrl']) ?></div>
                        <div class="stat-label">Total FOB</div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="stat-box tax">
                        <div class="stat-value" id="cardTotalTax">—</div>
                        <div class="stat-brl" id="cardTotalTaxBrl" style="font-size:.78rem;">defina os impostos</div>
                        <div class="stat-label">Total c/ Impostos</div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="stat-box tax" style="border-top-color:var(--td-teal);">
                        <div class="stat-value" id="cardTotalMarkup">—</div>
                        <div class="stat-brl" id="cardTotalMarkupBrl" style="font-size:.78rem;">defina markup</div>
                        <div class="stat-label">Total c/ Markup</div>
                    </div>
                </div>
            </div>


            <?php if ($s['notFoundCount'] > 0): ?>
            <div class="alert alert-warning py-2 px-3" style="font-size:.82rem;border-radius:8px;">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <strong><?= $s['notFoundCount'] ?> MeterID(s)</strong> nao foram encontrados na API de precos da Microsoft. Os valores CSP/diferenca dessas linhas sao exibidos como &ldquo;N/D&rdquo;.
                <details class="mt-1">
                    <summary style="cursor:pointer;font-size:.78rem;">Ver IDs nao encontrados</summary>
                    <div class="mt-1" style="font-family:monospace;font-size:.75rem;word-break:break-all;">
                        <?= implode('<br>', array_map('htmlspecialchars', $s['notFoundMeterIds'])) ?>
                    </div>
                </details>
            </div>
            <?php endif; ?>

            <!-- Tab navigation -->
            <ul class="analysis-tabs mb-3" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" onclick="switchTab('financeiro')" id="tabBtnFinanceiro" type="button">
                        <i class="bi bi-cash-stack me-1"></i>Detalhamento Financeiro
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" onclick="switchTab('tecnico')" id="tabBtnTecnico" type="button">
                        <i class="bi bi-cpu me-1"></i>Analise Tecnica
                        <?php if (!empty($results['technicalAnalysis']['summary'])): ?>
                        <span class="badge rounded-pill" style="background:#005758;font-size:.68rem;margin-left:4px;"><?= $results['technicalAnalysis']['summary']['totalMovablePercent'] ?>% migravel</span>
                        <?php endif; ?>
                    </button>
                </li>
            </ul>

            <div id="tab-financeiro">
            <!-- Export toolbar -->
            <div class="fin-card mb-3">
                <div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0" style="font-weight:600;color:#1e293b;">
                        <i class="bi bi-file-earmark-arrow-down me-2" style="color:var(--td-teal);"></i>Exportar Resultado
                    </h6>
                    <div class="d-flex gap-2 flex-wrap">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="export_csv">
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i><span data-i18n="btn.exportCsv">CSV</span>
                            </button>
                        </form>
                        <button type="button" class="btn btn-sm btn-teal" onclick="gerarPropostaPDF()" id="btnPdf">
                            <i class="bi bi-file-earmark-pdf me-1"></i><span data-i18n="btn.generatePdf">Proposta PDF</span>
                        </button>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="new_analysis">
                            <button type="submit" class="btn btn-sm btn-outline-primary"
                                    onclick="return confirm('Iniciar nova analise? Os dados atuais serao descartados.')">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Nova Analise
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Breakdowns -->
            <div class="row g-3 mb-3">
                <!-- Por Servico -->
                <div class="col-md-4">
                    <div class="fin-card h-100">
                        <div class="card-header"><i class="bi bi-grid me-2"></i>Por Servico</div>
                        <div class="card-body p-3">
                            <?php foreach (array_slice($s['byService'], 0, 8, true) as $svcName => $svcData): ?>
                            <?php $pct = $s['totalCsp'] > 0 ? ($svcData['costCsp'] / $s['totalCsp']) * 100 : 0; ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span style="font-size:.82rem;font-weight:500;color:var(--td-dark);"><?= htmlspecialchars($svcName) ?></span>
                                    <div class="text-end">
                                        <?php if ($svcData['costCsp'] > 0): ?>
                                        <span style="font-size:.82rem;font-weight:700;color:var(--td-teal);"><?= fmtUsd($svcData['costCsp']) ?></span>
                                        <?php else: ?>
                                        <span style="font-size:.78rem;color:#94a3b8;">N/D</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="breakdown-bar">
                                    <div class="breakdown-bar-fill" style="width:<?= min(100, $pct) ?>%;"></div>
                                </div>
                                <div style="font-size:.72rem;color:#94a3b8;"><?= number_format($pct,1) ?>% do total CSP &middot; <?= $svcData['count'] ?> linha(s)</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Por Resource Group -->
                <div class="col-md-4">
                    <div class="fin-card h-100">
                        <div class="card-header"><i class="bi bi-folder me-2"></i>Por Resource Group</div>
                        <div class="card-body p-3">
                            <?php foreach (array_slice($s['byResourceGroup'], 0, 8, true) as $rgName => $rgData): ?>
                            <?php $pct = $s['totalCsp'] > 0 ? ($rgData['costCsp'] / $s['totalCsp']) * 100 : 0; ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span style="font-size:.82rem;font-weight:500;color:var(--td-dark);"><?= htmlspecialchars($rgName) ?></span>
                                    <div class="text-end">
                                        <?php if ($rgData['costCsp'] > 0): ?>
                                        <span style="font-size:.82rem;font-weight:700;color:var(--td-teal);"><?= fmtUsd($rgData['costCsp']) ?></span>
                                        <?php else: ?>
                                        <span style="font-size:.78rem;color:#94a3b8;">N/D</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="breakdown-bar">
                                    <div class="breakdown-bar-fill" style="width:<?= min(100, $pct) ?>%;background:#0097D7;"></div>
                                </div>
                                <div style="font-size:.72rem;color:#94a3b8;"><?= number_format($pct,1) ?>% do total CSP &middot; <?= $rgData['count'] ?> linha(s)</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Por Assinatura -->
                <div class="col-md-4">
                    <div class="fin-card h-100">
                        <div class="card-header"><i class="bi bi-building me-2"></i>Por Assinatura</div>
                        <div class="card-body p-3">
                            <?php if (count($s['bySubscription']) <= 1 && array_key_first($s['bySubscription']) === 'Sem Assinatura'): ?>
                            <div class="text-center py-4" style="color:#94a3b8;font-size:.82rem;">
                                <i class="bi bi-check-circle" style="font-size:1.5rem;color:#0097D7;display:block;margin-bottom:.5rem;"></i>
                                Cliente possui apenas uma assinatura Azure.
                            </div>
                            <?php else: ?>
                            <?php foreach (array_slice($s['bySubscription'], 0, 8, true) as $subName => $subData): ?>
                            <?php $pct = $s['totalCsp'] > 0 ? ($subData['costCsp'] / $s['totalCsp']) * 100 : 0; ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div style="min-width:0;">
                                        <div style="font-size:.82rem;font-weight:500;color:var(--td-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;" title="<?= htmlspecialchars($subName) ?>"><?= htmlspecialchars($subName) ?></div>
                                        <?php if (!empty($subData['subscriptionId'])): ?>
                                        <div style="font-size:.68rem;color:#94a3b8;font-family:monospace;"><?= htmlspecialchars(substr($subData['subscriptionId'], 0, 18)) ?>...</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($subData['costCsp'] > 0): ?>
                                        <span style="font-size:.82rem;font-weight:700;color:var(--td-teal);"><?= fmtUsd($subData['costCsp']) ?></span>
                                        <?php else: ?>
                                        <span style="font-size:.78rem;color:#94a3b8;">N/D</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="breakdown-bar">
                                    <div class="breakdown-bar-fill" style="width:<?= min(100, $pct) ?>%;background:#82C341;"></div>
                                </div>
                                <div style="font-size:.72rem;color:#94a3b8;"><?= number_format($pct,1) ?>% do total CSP &middot; <?= $subData['count'] ?> linha(s)</div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela detalhada -->
            <?php
                // Detecta se o CSV usa DD/MM/YYYY: se qualquer primeiro segmento > 12 não pode ser mês
                $slashDMY_ = false;
                foreach ($results['results'] as $_rd) {
                    if (preg_match('/^(\d{1,2})\//', trim($_rd['date'] ?? ''), $_dm) && (int)$_dm[1] > 12) {
                        $slashDMY_ = true;
                        break;
                    }
                }
                // Helper: converte qualquer formato de data para YYYY-MM-DD
                $toIso_ = function(string $raw) use ($slashDMY_): string {
                    $raw = trim($raw);
                    if ($raw === '') return '';
                    // Remove time portion (T or space)
                    $dp = preg_split('/[T ]/', $raw)[0];
                    // Already YYYY-MM-DD
                    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dp)) return $dp;
                    // DD/MM/YYYY or MM/DD/YYYY — detectar pelo flag ou pelo valor > 12
                    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dp, $m)) {
                        $isDay1 = $slashDMY_ || (int)$m[1] > 12;
                        [$month, $day] = $isDay1 ? [$m[2], $m[1]] : [$m[1], $m[2]];
                        return $m[3] . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                    }
                    // Fallback: strtotime handles many formats
                    $ts = @strtotime($dp);
                    return ($ts !== false && $ts > 0) ? date('Y-m-d', $ts) : '';
                };
                // Calcular data mínima e máxima dos dados para pré-popular o filtro
                $allDates_ = [];
                foreach ($results['results'] as $row_) {
                    $iso = $toIso_(trim($row_['date'] ?? ''));
                    if ($iso !== '') $allDates_[] = $iso;
                }
                $minDate_ = $allDates_ ? min($allDates_) : '';
                $maxDate_ = $allDates_ ? max($allDates_) : '';
            ?>
            <div class="fin-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i class="bi bi-table me-2"></i>Detalhamento Linha a Linha</span>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="d-flex align-items-center gap-1" style="font-size:.8rem;">
                            <label style="color:#94a3b8;white-space:nowrap;margin:0;"><i class="bi bi-calendar-range me-1"></i>De</label>
                            <input type="date" id="dateFrom" class="form-control form-control-sm" style="width:140px;" onchange="filterTable()"
                                   value="<?= htmlspecialchars($minDate_) ?>" min="<?= htmlspecialchars($minDate_) ?>" max="<?= htmlspecialchars($maxDate_) ?>">
                            <label style="color:#94a3b8;margin:0;">Até</label>
                            <input type="date" id="dateTo" class="form-control form-control-sm" style="width:140px;" onchange="filterTable()"
                                   value="<?= htmlspecialchars($maxDate_) ?>" min="<?= htmlspecialchars($minDate_) ?>" max="<?= htmlspecialchars($maxDate_) ?>">
                            <button class="btn btn-sm btn-outline-secondary" onclick="clearDateFilter()" title="Limpar filtro de data" style="padding:2px 8px;">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                        <input type="text" id="searchInput" class="form-control form-control-sm" style="width:180px;"
                               placeholder="Filtrar recurso..." oninput="debouncedFilter()">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div style="overflow-x:auto;max-height:500px;overflow-y:auto;">
                        <table class="table table-hover table-fin mb-0" id="detailTable">
                            <thead style="position:sticky;top:0;z-index:1;">
                                <tr>
                                    <th style="width:160px;" data-i18n="table.resource">Resource Name</th>
                                    <th style="width:100px;" data-i18n="table.date">Data</th>
                                    <th style="width:270px;">Meter ID</th>
                                    <th style="width:140px;">Subscription</th>
                                    <th style="width:280px;">Subscription ID</th>
                                    <th style="width:155px;" data-i18n="table.service">Servico / Meter</th>
                                    <th style="width:130px;" data-i18n="table.resourceGroup">Resource Group</th>
                                    <th style="width:90px;" data-i18n="table.region">Região</th>
                                    <th style="width:85px;" data-i18n="table.quantity">Qtd</th>
                                    <th style="width:95px;">Custo MOSP</th>
                                    <th style="width:110px;">Preço Unit. FOB</th>
                                    <th style="width:135px;">Total sem Impostos</th>
                                    <th style="width:130px;">Total c/ Impostos</th>
                                    <th style="width:130px;">Total c/ Markup</th>
                                    <th style="width:215px;">Validação</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($results['results'] as $r):
                                $diff    = $r['difference'];
                                $dClass  = $diff !== null ? ($diff < -0.001 ? 'diff-negative' : ($diff > 0.001 ? 'diff-positive' : '')) : '';
                            ?>
                            <?php
                                // Normalise date to YYYY-MM-DD for data-date filter attribute
                                $isoDate_ = $toIso_(trim($r['date'] ?? ''));
                            ?>
                            <tr data-search="<?= htmlspecialchars(strtolower($r['resourceName'].'|'.$r['meterCategory'].'|'.$r['meterName'].'|'.$r['resourceGroup'].'|'.$r['resourceLocation'].'|'.$r['subscriptionId'].'|'.$r['subscriptionName'].'|'.$r['meterId'])) ?>"
                                data-date="<?= htmlspecialchars($isoDate_) ?>"
                                data-mosp="<?= $r['costMosp'] !== null ? number_format((float)$r['costMosp'], 10, '.', '') : '' ?>"
                                data-csp="<?= $r['costCsp'] !== null ? number_format((float)$r['costCsp'], 10, '.', '') : '' ?>">
                                <td>
                                    <div style="font-weight:500;font-size:.82rem;"><?= htmlspecialchars($r['resourceName'] ?: '—') ?></div>
                                </td>
                                <td style="font-size:.82rem;white-space:nowrap;"><?php
                                    // Display: converter ISO para DD/MM/YYYY; MM/DD/YYYY (Azure) → DD/MM/YYYY
                                    $iso = $toIso_(trim($r['date'] ?? ''));
                                    if ($iso !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
                                        echo $m[3] . '/' . $m[2] . '/' . $m[1];
                                    } else {
                                        echo '—';
                                    }
                                ?></td>
                                <td>
                                    <div class="meter-id" style="word-break:break-all;"><?= htmlspecialchars($r['meterId']) ?></div>
                                </td>
                                <td>
                                    <div style="font-size:.8rem;font-weight:500;"><?= htmlspecialchars($r['subscriptionName'] ?: '—') ?></div>
                                </td>
                                <td>
                                    <div class="sub-id" style="word-break:break-all;"><?= htmlspecialchars($r['subscriptionId'] ?: '—') ?></div>
                                </td>
                                <td>
                                    <div style="font-size:.82rem;"><?= htmlspecialchars($r['meterCategory'] ?: $r['serviceFamily'] ?: '—') ?></div>
                                    <?php if ($r['meterName']): ?>
                                    <div style="font-size:.72rem;color:#64748b;"><?= htmlspecialchars($r['meterName']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($r['meterSubcategory']): ?>
                                    <div style="font-size:.68rem;color:#94a3b8;"><?= htmlspecialchars($r['meterSubcategory']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:.82rem;"><?= htmlspecialchars($r['resourceGroup'] ?: '—') ?></td>
                                <td style="font-size:.82rem;"><?= htmlspecialchars($r['resourceLocation'] ?: '—') ?></td>
                                <td style="font-size:.82rem;white-space:nowrap;"><?= number_format($r['quantity'], 2, ',', '.') ?> <span style="color:#94a3b8;font-size:.72rem;"><?= htmlspecialchars($r['unitOfMeasure']) ?></span></td>
                                <td style="font-size:.82rem;font-weight:600;"><?= fmtUsd($r['costMosp']) ?></td>
                                <?php
                                    $unitCsp_ = $r['unitPriceCsp'];
                                    $totalCsp_ = $r['costCsp']; // qty * unitCspApi
                                ?>
                                <td style="font-size:.82rem;">
                                    <?= $unitCsp_ !== null ? '$' . number_format($unitCsp_, 6, ',', '.') : '<span class="text-muted">N/D</span>' ?>
                                </td>
                                <td style="font-size:.82rem;font-weight:600;" class="col-total-fob"
                                    data-fob="<?= $totalCsp_ !== null ? number_format($totalCsp_, 10, '.', '') : '' ?>">
                                    <?= $totalCsp_ !== null ? fmtUsd($totalCsp_) : '<span class="text-muted">N/D</span>' ?>
                                </td>
                                <td style="font-size:.82rem;font-weight:600;" class="col-total-tax">
                                    <?= $totalCsp_ !== null ? fmtUsd($totalCsp_) : '<span class="text-muted">N/D</span>' ?>
                                </td>
                                <td style="font-size:.82rem;font-weight:600;" class="col-total-markup">
                                    <?= $totalCsp_ !== null ? fmtUsd($totalCsp_) : '<span class="text-muted">N/D</span>' ?>
                                </td>
                                <td>
                                    <?php
                                        // ── helpers inline ──
                                        $mId_      = $r['meterId'];
                                        $csvLoc_   = $r['resourceLocation'] ?? '';

                                        $filterStr_ = $r['apiFilterUsed'] ?? "meterId eq '{$mId_}'";
                                        if (!$r['priceFound']) {
                                            $f1_ = "meterId eq '{$mId_}'"
                                                 . (!empty($r['unitOfMeasure']) ? " and unitOfMeasure eq '{$r['unitOfMeasure']}'" : '')
                                                 . " and priceType eq 'Consumption'";
                                            $filterStr_ = $f1_;
                                        }
                                        $apiUrl_ = 'https://prices.azure.com/api/retail/prices?api-version=2023-01-01-preview&$filter=' . rawurlencode($filterStr_);

                                        // função inline para gerar badge de check
                                        $chk = function(string $label, ?string $csvVal, ?string $apiVal, bool $normalize = false) {
                                            if ($apiVal === null || $apiVal === '') {
                                                return '<span class="vchk vchk-na" title="' . htmlspecialchars($label) . ': não retornado pela API">' . htmlspecialchars($label) . '</span>';
                                            }
                                            $c = $normalize
                                                ? strtolower(str_replace([' ','-','_'], '', (string)$csvVal)) === strtolower(str_replace([' ','-','_'], '', (string)$apiVal))
                                                : (string)$csvVal === (string)$apiVal;
                                            $icon = $c ? '✓' : '≠';
                                            $cls  = $c ? 'vchk-ok' : 'vchk-err';
                                            $tip  = htmlspecialchars("{$label}\nCSV: " . ($csvVal ?: '—') . "\nAPI: {$apiVal}");
                                            return "<span class=\"vchk {$cls}\" title=\"{$tip}\">{$icon} " . htmlspecialchars($label) . '</span>';
                                        };

                                        if ($r['priceFound']) {
                                            $lvl_     = (int)($r['apiMatchLevel'] ?? 0);
                                            $score_   = (int)($r['apiMatchScore'] ?? 0);
                                            $maxScr_  = (int)($r['apiMatchMaxScore'] ?? 0);
                                            $pct_     = $maxScr_ > 0 ? round($score_ / $maxScr_ * 100) : 0;
                                            $lvlTip_  = match($lvl_) {
                                                1 => 'Query: meterId + priceType=Consumption',
                                                2 => 'Query: meterId (sem filtro priceType — fallback)',
                                                default => 'Encontrado',
                                            };
                                            echo '<div class="d-flex flex-wrap gap-1" style="max-width:210px;">';
                                            // meterId
                                            echo $chk('MeterId', $mId_, $r['apiMeterId'] ?? null);
                                            // location (formato original do CSV)
                                            echo $chk('Location', $csvLoc_, $r['apiLocation'] ?? null, true);
                                            // unitOfMeasure
                                            echo $chk('UoM', $r['unitOfMeasure'], $r['apiUnitOfMeasure'] ?? null);
                                            // meterName
                                            echo $chk('Meter', $r['meterName'], $r['cspMeterName'] ?? null);
                                            // score badge
                                            $scoreColor_ = $pct_ >= 90 ? 'vchk-ok' : ($pct_ >= 50 ? 'vchk-na' : 'vchk-err');
                                            echo '<span class="vchk ' . $scoreColor_ . '" title="' . htmlspecialchars("Score: {$score_}/{$maxScr_} ({$pct_}%)\n{$lvlTip_}") . '">' . $pct_ . '%</span>';
                                            // link API
                                            echo '<a href="' . htmlspecialchars($apiUrl_) . '" target="_blank" rel="noopener noreferrer" class="vchk vchk-na" title="Abrir query na API Microsoft" style="text-decoration:none;">&#x1F517; API</a>';
                                            echo '</div>';
                                        } else {
                                            echo '<div class="d-flex flex-wrap gap-1" style="max-width:210px;">';
                                            echo '<span class="vchk vchk-err">N/D</span>';
                                            echo '<a href="' . htmlspecialchars($apiUrl_) . '" target="_blank" rel="noopener noreferrer" class="vchk vchk-na" title="Tentar query na API Microsoft" style="text-decoration:none;">&#x1F517; API</a>';
                                            echo '</div>';
                                        }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-2 px-3" style="font-size:.78rem;color:#94a3b8;border-top:1px solid #f1f5f9;">
                        <span id="rowCount"><?= count($results['results']) ?></span> de <?= count($results['results']) ?> linhas exibidas
                        <span id="dateFilterHint" style="margin-left:10px;color:#0097D7;font-weight:500;"></span>
                    </div>
                </div>
            </div>
            </div><!-- /tab-financeiro -->

            <!-- ===== ABA TÉCNICA ===== -->
            <div id="tab-tecnico" style="display:none;">
                <?php if (!empty($results['technicalAnalysis'])): ?>
                <?php $tech = $results['technicalAnalysis']; $ts = $tech['summary']; ?>

                <!-- Migration Summary Cards -->
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="stat-box" style="border-top-color:#005758;cursor:pointer;" onclick="toggleTechFilter('movable')" id="techCard-movable">
                            <div class="stat-value" style="color:#005758;"><?= $ts['movable'] ?></div>
                            <div class="stat-brl"><?= $ts['movablePercent'] ?>%</div>
                            <div class="stat-label">Migraveis</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box" style="border-top-color:#0097D7;cursor:pointer;" onclick="toggleTechFilter('movable-with-restrictions')" id="techCard-movable-with-restrictions">
                            <div class="stat-value" style="color:#0097D7;"><?= $ts['movableWithRestrictions'] ?></div>
                            <div class="stat-brl"><?= $ts['movableWithRestrictionsPercent'] ?>%</div>
                            <div class="stat-label">Com Restricoes</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box" style="border-top-color:#D9272E;cursor:pointer;" onclick="toggleTechFilter('not-movable')" id="techCard-not-movable">
                            <div class="stat-value" style="color:#D9272E;"><?= $ts['notMovable'] ?></div>
                            <div class="stat-brl"><?= $ts['notMovablePercent'] ?>%</div>
                            <div class="stat-label">Nao Migraveis</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box" style="border-top-color:#737373;cursor:pointer;" onclick="toggleTechFilter('unknown')" id="techCard-unknown">
                            <div class="stat-value" style="color:#737373;"><?= $ts['unknown'] ?></div>
                            <div class="stat-brl"><?= $ts['unknownPercent'] ?>%</div>
                            <div class="stat-label">Desconhecidos</div>
                        </div>
                    </div>
                </div>

                <!-- Migration Viability Bar -->
                <div class="fin-card mb-3">
                    <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Viabilidade de Migracao</div>
                    <div class="card-body p-3">
                        <div class="mig-bar">
                            <?php if ($ts['movablePercent'] > 0): ?>
                            <div style="width:<?= $ts['movablePercent'] ?>%;background:#005758;" title="Migraveis: <?= $ts['movable'] ?> (<?= $ts['movablePercent'] ?>%)"></div>
                            <?php endif; ?>
                            <?php if ($ts['movableWithRestrictionsPercent'] > 0): ?>
                            <div style="width:<?= $ts['movableWithRestrictionsPercent'] ?>%;background:#0097D7;" title="Com Restricoes: <?= $ts['movableWithRestrictions'] ?> (<?= $ts['movableWithRestrictionsPercent'] ?>%)"></div>
                            <?php endif; ?>
                            <?php if ($ts['notMovablePercent'] > 0): ?>
                            <div style="width:<?= $ts['notMovablePercent'] ?>%;background:#D9272E;" title="Nao Migraveis: <?= $ts['notMovable'] ?> (<?= $ts['notMovablePercent'] ?>%)"></div>
                            <?php endif; ?>
                            <?php if ($ts['unknownPercent'] > 0): ?>
                            <div style="width:<?= $ts['unknownPercent'] ?>%;background:#737373;" title="Desconhecidos: <?= $ts['unknown'] ?> (<?= $ts['unknownPercent'] ?>%)"></div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap gap-3 mt-2" style="font-size:.75rem;">
                            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#005758;margin-right:4px;vertical-align:middle;"></span>Migraveis (<?= $ts['movablePercent'] ?>%)</span>
                            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#0097D7;margin-right:4px;vertical-align:middle;"></span>Com Restricoes (<?= $ts['movableWithRestrictionsPercent'] ?>%)</span>
                            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#D9272E;margin-right:4px;vertical-align:middle;"></span>Nao Migraveis (<?= $ts['notMovablePercent'] ?>%)</span>
                            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#737373;margin-right:4px;vertical-align:middle;"></span>Desconhecidos (<?= $ts['unknownPercent'] ?>%)</span>
                        </div>
                    </div>
                </div>

                <!-- Export toolbar -->
                <div class="fin-card mb-3">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="mb-0" style="font-weight:600;color:#1e293b;">
                            <i class="bi bi-file-earmark-arrow-down me-2" style="color:var(--td-teal);"></i>Exportar Analise Tecnica
                        </h6>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="exportTechCsv()">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV
                            </button>
                            <button type="button" class="btn btn-sm btn-teal" onclick="exportTechPdf()" id="btnTechPdf">
                                <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Resource Type Detail Table -->
                <div class="fin-card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><i class="bi bi-list-check me-2"></i>Detalhamento por Tipo de Recurso</span>
                        <div class="d-flex align-items-center gap-2">
                            <input type="text" id="techSearchInput" class="form-control form-control-sm" style="width:180px;" placeholder="Filtrar recurso..." oninput="filterTechTable()">
                            <span style="font-size:.78rem;color:#94a3b8;white-space:nowrap;"><span id="techRowCount"><?= $ts['total'] ?></span> tipos</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div style="overflow-x:auto;max-height:500px;overflow-y:auto;">
                            <table class="table table-hover table-fin mb-0" id="techTable">
                                <thead style="position:sticky;top:0;z-index:1;">
                                    <tr>
                                        <th style="width:280px;">Tipo do Recurso</th>
                                        <th style="width:140px;">Subscription</th>
                                        <th style="width:150px;">Status</th>
                                        <th style="width:80px;text-align:center;">RG</th>
                                        <th style="width:80px;text-align:center;">Sub</th>
                                        <th style="width:80px;text-align:center;">Regiao</th>
                                        <th>Observacoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($tech['results'] as $tr_): ?>
                                <tr data-tech-search="<?= htmlspecialchars(strtolower($tr_['resourceType'] . '|' . $tr_['provider'] . '|' . $tr_['notes'] . '|' . ($tr_['subscription'] ?? ''))) ?>"
                                    data-status="<?= htmlspecialchars($tr_['status']) ?>">
                                    <td style="font-size:.82rem;">
                                        <div style="font-weight:500;"><?= htmlspecialchars($tr_['resourceType']) ?></div>
                                        <div style="font-size:.7rem;color:#94a3b8;"><?= htmlspecialchars($tr_['provider']) ?></div>
                                    </td>
                                    <td style="font-size:.8rem;">
                                        <div style="font-weight:500;"><?= htmlspecialchars($tr_['subscription'] ?? '—') ?></div>
                                    </td>
                                    <td>
                                        <span class="mig-status-badge" style="background:<?= htmlspecialchars($tr_['statusColor']) ?>22;color:<?= htmlspecialchars($tr_['statusColor']) ?>;">
                                            <?= $tr_['statusLabel'] ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;font-size:.9rem;">
                                        <?php if ($tr_['resourceGroupMove'] === true): ?>
                                            <span style="color:#16a34a;" title="Migravel entre Resource Groups">&#10003;</span>
                                        <?php elseif ($tr_['resourceGroupMove'] === false): ?>
                                            <span style="color:#dc2626;" title="Nao migravel entre Resource Groups">&#10007;</span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;font-size:.9rem;">
                                        <?php if ($tr_['subscriptionMove'] === true): ?>
                                            <span style="color:#16a34a;" title="Migravel entre Subscriptions">&#10003;</span>
                                        <?php elseif ($tr_['subscriptionMove'] === false): ?>
                                            <span style="color:#dc2626;" title="Nao migravel entre Subscriptions">&#10007;</span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;font-size:.9rem;">
                                        <?php if ($tr_['regionMove'] === true): ?>
                                            <span style="color:#16a34a;" title="Migravel entre Regioes">&#10003;</span>
                                        <?php elseif ($tr_['regionMove'] === false): ?>
                                            <span style="color:#dc2626;" title="Nao migravel entre Regioes">&#10007;</span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:.78rem;color:#64748b;"><?= htmlspecialchars($tr_['notes'] ?: '') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <!-- No ResourceId in CSV -->
                <div class="fin-card d-flex align-items-center justify-content-center" style="min-height:220px;">
                    <div class="text-center py-4 px-4">
                        <i class="bi bi-info-circle" style="font-size:2rem;color:#94a3b8;"></i>
                        <h6 style="font-weight:600;color:#64748b;margin-top:.75rem;">Analise tecnica indisponivel</h6>
                        <p class="text-muted mb-0" style="font-size:.85rem;max-width:400px;">
                            O arquivo CSV nao contem a coluna <code>ResourceId</code>, necessaria para identificar
                            os tipos de recurso e avaliar a viabilidade de migracao entre Resource Groups, Subscriptions e Regioes.
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div><!-- /tab-tecnico -->

        <?php else: ?>
            <!-- Empty state -->
            <div class="fin-card d-flex align-items-center justify-content-center" style="min-height:420px;">
                <div class="text-center py-5 px-4">
                    <div style="background:#ccfbf1;width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;">
                        <i class="bi bi-cloud-arrow-up" style="font-size:2rem;color:var(--td-teal);"></i>
                    </div>
                    <h5 style="font-weight:700;color:#1e293b;">Nenhuma analise realizada</h5>
                    <p class="text-muted mb-0" style="max-width:400px;font-size:.87rem;">
                        Faca o upload do arquivo CSV exportado do <strong>Azure Cost Management</strong>
                        para calcular e comparar os custos MOSP vs CSP.
                    </p>
                </div>
            </div>
        <?php endif; ?>
        </div><!-- col-lg-9 -->
    </div><!-- row -->
</div><!-- container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateSchemaHint() {
    const sel = document.getElementById('schemaKey');
    const col = document.getElementById('schemaQtyCol');
    const badge = document.getElementById('schemaLatestBadge');
    const qtyColDisplay = document.getElementById('qtyColDisplay');
    const qtyColHint = document.getElementById('qtyColHint');
    const migrationType = document.getElementById('migrationType');
    if (!sel || !col) return;
    
    const opt = sel.selectedOptions[0];
    const qtyCol = opt?.dataset.qty || 'quantity';
    const version = opt?.dataset.version || '';
    const agreement = opt?.dataset.agreement || 'MOSA';
    const isLatest = opt?.dataset.latest === '1';
    
    col.textContent = qtyCol;
    badge.style.display = isLatest ? '' : 'none';
    
    // Atualiza migrationType hidden (para compatibilidade)
    if (migrationType) {
        migrationType.value = ['MPA', 'CSP'].includes(agreement) ? 'mpn_csp' : 'mosp_csp';
    }
    
    // Atualiza visualização na lista de colunas obrigatórias
    if (qtyColDisplay) {
        qtyColDisplay.textContent = qtyCol === 'usagequantity' ? 'UsageQuantity' : 'Quantity';
    }
    if (qtyColHint) {
        qtyColHint.textContent = ['MPA', 'CSP'].includes(agreement) ? '(MPA/CSP)' : '(MOSA/EA/MCA)';
    }
}

// ============================================================
// CHUNKED UPLOAD — envia arquivo em pedaços de 2 MB
// Bypassa qualquer limite de Nginx / IIS / proxy
// ============================================================
const CHUNK_SIZE = 512 * 1024; // 512 KB por chunk — abaixo do limite padrão Nginx (1 MB)

function generateUploadId() {
    return 'up_' + Date.now() + '_' + Math.random().toString(36).substring(2, 10);
}

async function uploadChunked(file) {
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    const uploadId    = generateUploadId();

    const progressDiv  = document.getElementById('uploadProgress');
    const progressBar  = document.getElementById('uploadProgressBar');
    const progressText = document.getElementById('uploadProgressText');
    const progressDet  = document.getElementById('uploadProgressDetail');

    progressDiv.classList.remove('d-none');
    progressBar.style.width = '0%';
    progressText.textContent = '0%';
    progressDet.textContent = `Enviando ${file.name} (${(file.size / 1024 / 1024).toFixed(1)} MB) em ${totalChunks} parte(s)...`;

    for (let i = 0; i < totalChunks; i++) {
        const start = i * CHUNK_SIZE;
        const end   = Math.min(start + CHUNK_SIZE, file.size);
        const blob  = file.slice(start, end);

        const fd = new FormData();
        fd.append('chunk', blob, 'chunk');
        fd.append('chunkIndex',  String(i));
        fd.append('totalChunks', String(totalChunks));
        fd.append('uploadId',    uploadId);
        fd.append('fileName',    file.name);

        let retries = 0;
        let resp;
        while (retries < 3) {
            try {
                resp = await fetch('upload-chunk.php', { method: 'POST', body: fd });
                if (resp.ok) break;
            } catch (e) { /* retry */ }
            retries++;
            await new Promise(r => setTimeout(r, 1000 * retries));
        }

        if (!resp || !resp.ok) {
            const errText = resp ? await resp.text() : 'Sem resposta do servidor';
            throw new Error(`Falha no chunk ${i + 1}/${totalChunks}: ${errText}`);
        }

        const data = await resp.json();
        if (!data.ok) {
            throw new Error(data.error || `Erro no chunk ${i + 1}`);
        }

        const pct = Math.round(((i + 1) / totalChunks) * 100);
        progressBar.style.width = pct + '%';
        progressText.textContent = pct + '%';
        progressDet.textContent = `Parte ${i + 1} de ${totalChunks} enviada (${(end / 1024 / 1024).toFixed(1)} MB)`;
    }

    progressDet.textContent = 'Upload concluido! Iniciando analise...';
    return uploadId;
}

function handleFile(input) {
    const file = input.files[0];
    if (!file) return;
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('selectedFile').classList.remove('d-none');
    document.getElementById('analyzeBtn').disabled = false;
    document.querySelector('.upload-zone').style.borderColor = 'var(--td-teal)';
}

// Interceptar submit do formulário
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('uploadForm');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        const fileInput = document.getElementById('fileInput');
        const file = fileInput?.files?.[0];

        // Se não tem arquivo, deixa o form submeter normalmente (pode ser ação sem upload)
        if (!file) return;

        // Impede o submit padrão
        e.preventDefault();

        const btn = document.getElementById('analyzeBtn');
        const progressDiv = document.getElementById('uploadProgress');
        const progressDet = document.getElementById('uploadProgressDetail');

        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Enviando...';

        try {
            const uploadId = await uploadChunked(file);

            // Colocar o uploadId no form e limpar o file input para não enviar via multipart
            document.getElementById('uploadIdField').value = uploadId;
            document.getElementById('fileNameField').value = file.name;

            // Desabilitar e limpar o file input — evita que o browser envie o arquivo grande
            fileInput.disabled = true;
            fileInput.value = '';

            btn.innerHTML = '<i class="bi bi-gear me-1 spin-icon"></i>Processando API...';
            if (progressDet) progressDet.textContent = 'Upload concluido! Processando analise na API Microsoft...';

            // Submeter o form normalmente (agora sem o arquivo, apenas com uploadId)
            form.submit();
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i>Calcular Custos CSP';
            progressDiv.classList.add('d-none');
            alert('Erro no upload: ' + err.message);
        }
    });
});

// Drag-and-drop
const dz = document.getElementById('dropZone');
if (dz) {
    dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('dragover'); });
    dz.addEventListener('dragleave', ()  => dz.classList.remove('dragover'));
    dz.addEventListener('drop',      e  => {
        e.preventDefault();
        dz.classList.remove('dragover');
        const fi = document.getElementById('fileInput');
        fi.files = e.dataTransfer.files;
        handleFile(fi);
    });
}

function switchTab(tab) {
    const fin = document.getElementById('tab-financeiro');
    const tec = document.getElementById('tab-tecnico');
    const btnFin = document.getElementById('tabBtnFinanceiro');
    const btnTec = document.getElementById('tabBtnTecnico');
    if (fin) fin.style.display = tab === 'financeiro' ? '' : 'none';
    if (tec) tec.style.display = tab === 'tecnico' ? '' : 'none';
    if (btnFin) btnFin.classList.toggle('active', tab === 'financeiro');
    if (btnTec) btnTec.classList.toggle('active', tab === 'tecnico');
}

let _activeTechStatus = null;

function toggleTechFilter(status) {
    const cards = document.querySelectorAll('[id^="techCard-"]');
    if (_activeTechStatus === status) {
        _activeTechStatus = null;
        cards.forEach(c => c.classList.remove('tech-active'));
    } else {
        _activeTechStatus = status;
        cards.forEach(c => c.classList.toggle('tech-active', c.id === 'techCard-' + status));
    }
    filterTechTable();
}

function filterTechTable() {
    const q = (document.getElementById('techSearchInput')?.value || '').toLowerCase();
    const rows = document.querySelectorAll('#techTable tbody tr');
    let visible = 0;
    rows.forEach(tr => {
        const matchText = !q || (tr.dataset.techSearch || '').includes(q);
        const matchStatus = !_activeTechStatus || tr.dataset.status === _activeTechStatus;
        const show = matchText && matchStatus;
        tr.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const rc = document.getElementById('techRowCount');
    if (rc) rc.textContent = visible;
}

function exportTechCsv() {
    const rows = document.querySelectorAll('#techTable tbody tr');
    const lines = ['Tipo do Recurso,Provider,Status,Resource Group,Subscription,Regiao,Observacoes'];
    rows.forEach(tr => {
        if (tr.style.display === 'none') return;
        const cells = tr.querySelectorAll('td');
        const type = (cells[0]?.querySelector('div')?.textContent || '').trim();
        const prov = (cells[0]?.querySelectorAll('div')[1]?.textContent || '').trim();
        const st   = (cells[1]?.textContent || '').trim().replace(/[^\w\sáéíóúãçàèìòùÁÉÍÓÚÃÇÀÈÌÒÙ⚠️✅❌❓]/g, '').trim();
        const rg   = (cells[2]?.textContent || '').trim() === '✓' ? 'Sim' : (cells[2]?.textContent || '').trim() === '✗' ? 'Nao' : '—';
        const sub  = (cells[3]?.textContent || '').trim() === '✓' ? 'Sim' : (cells[3]?.textContent || '').trim() === '✗' ? 'Nao' : '—';
        const reg  = (cells[4]?.textContent || '').trim() === '✓' ? 'Sim' : (cells[4]?.textContent || '').trim() === '✗' ? 'Nao' : '—';
        const note = (cells[5]?.textContent || '').trim();
        lines.push([type, prov, st, rg, sub, reg, note].map(v => '"' + v.replace(/"/g, '""') + '"').join(','));
    });
    const blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'analise_tecnica_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
    URL.revokeObjectURL(a.href);
}

function exportTechPdf() {
    const btn = document.getElementById('btnTechPdf');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Gerando...'; }
    setTimeout(() => { try { _doTechPdf(); } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-1"></i>PDF'; }
    }}, 30);
}

function _doTechPdf() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const W = 210, ML = 15, MR = 15, CW = W - ML - MR;
    const TEAL = [0, 87, 88], DARK = [30, 41, 59], GRAY = [100, 116, 139];
    const WHITE = [255, 255, 255], LIGHT = [248, 250, 252], BLUE = [0, 151, 215];

    _pdfHeader(doc, 'Analise Tecnica de Migracao', W, TEAL, BLUE, WHITE, DARK, GRAY, ML);

    let y = 44;
    // Summary cards
    const cards = document.querySelectorAll('#tab-tecnico .stat-box');
    const cardData = [];
    cards.forEach(c => {
        const val = (c.querySelector('.stat-value')?.textContent || '0').trim();
        const pct = (c.querySelector('.stat-brl')?.textContent || '').trim();
        const lbl = (c.querySelector('.stat-label')?.textContent || '').trim();
        cardData.push({ val, pct, lbl });
    });
    const cW = (CW - 12) / 4;
    const cColors = [TEAL, [0,151,215], [217,39,46], [115,115,115]];
    cardData.forEach((c, i) => {
        const x = ML + i * (cW + 4);
        doc.setFillColor(...LIGHT);
        doc.roundedRect(x, y, cW, 22, 3, 3, 'F');
        doc.setFillColor(...cColors[i]);
        doc.rect(x, y, cW, 2, 'F');
        doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.setTextColor(...cColors[i]);
        doc.text(c.val, x + cW / 2, y + 11, { align: 'center' });
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(...GRAY);
        doc.text(c.pct, x + cW / 2, y + 16, { align: 'center' });
        doc.setFontSize(6.5); doc.text(c.lbl.toUpperCase(), x + cW / 2, y + 20, { align: 'center' });
    });
    y += 30;

    // Table
    const visibleRows = [];
    document.querySelectorAll('#techTable tbody tr').forEach(tr => {
        if (tr.style.display === 'none') return;
        const cells = tr.querySelectorAll('td');
        const type = (cells[0]?.querySelector('div')?.textContent || '').trim();
        const st   = (cells[1]?.textContent || '').trim();
        const rg   = (cells[2]?.textContent || '').trim();
        const sub  = (cells[3]?.textContent || '').trim();
        const reg  = (cells[4]?.textContent || '').trim();
        const note = (cells[5]?.textContent || '').trim();
        visibleRows.push([type, st, rg, sub, reg, note.substring(0, 50)]);
    });
    if (visibleRows.length) {
        doc.autoTable({
            startY: y,
            head: [['Tipo do Recurso', 'Status', 'RG', 'Sub', 'Regiao', 'Observacoes']],
            body: visibleRows,
            styles: { fontSize: 7, cellPadding: 2, overflow: 'ellipsize' },
            headStyles: { fillColor: TEAL, textColor: WHITE, fontStyle: 'bold', fontSize: 7.5 },
            alternateRowStyles: { fillColor: LIGHT },
            margin: { left: ML, right: MR },
            tableWidth: CW,
            columnStyles: { 0: { cellWidth: 55 }, 1: { cellWidth: 30 }, 2: { cellWidth: 12, halign: 'center' }, 3: { cellWidth: 12, halign: 'center' }, 4: { cellWidth: 14, halign: 'center' } },
            didDrawPage: () => {
                const pg = doc.getCurrentPageInfo().pageNumber;
                const fy = 297 - 14;
                doc.setDrawColor(226, 232, 240); doc.setLineWidth(0.3);
                doc.line(ML, fy, W - MR, fy);
                doc.setTextColor(160, 165, 170); doc.setFont('helvetica', 'normal'); doc.setFontSize(6.5);
                doc.text('TD SYNNEX  |  Analise Tecnica  |  Documento Confidencial', ML, fy + 5);
                doc.text('Pag. ' + pg, W - MR, fy + 5, { align: 'right' });
            },
        });
    }
    doc.save('analise_tecnica_' + new Date().toISOString().slice(0, 10) + '.pdf');
}

function clearDateFilter() {
    const df = document.getElementById('dateFrom');
    const dt = document.getElementById('dateTo');
    if (df) df.value = df.min || '';
    if (dt) dt.value = dt.max || '';
    filterTable();
}

let _filterTimer = null;
function debouncedFilter() {
    clearTimeout(_filterTimer);
    _filterTimer = setTimeout(filterTable, 200);
}

// Cache row array + pre-extracted data for performance with large datasets
let _cachedRows = null;
function _getRows() {
    if (_cachedRows) return _cachedRows;
    const trs = document.querySelectorAll('#detailTable tbody tr');
    _cachedRows = Array.from(trs).map(tr => ({
        el: tr,
        search: tr.dataset.search || '',
        date: (tr.dataset.date || '').substring(0, 10),
        mosp: parseFloat(tr.dataset.mosp) || 0,
        fobTd: tr.querySelector('.col-total-fob'),
        taxTd: tr.querySelector('.col-total-tax'),
        markupTd: tr.querySelector('.col-total-markup'),
        fob: parseFloat(tr.querySelector('.col-total-fob')?.dataset?.fob) || 0,
        hasFob: !isNaN(parseFloat(tr.querySelector('.col-total-fob')?.dataset?.fob)),
    }));
    return _cachedRows;
}

function filterTable() {
    const q       = (document.getElementById('searchInput')?.value || '').toLowerCase();
    const dFrom   = document.getElementById('dateFrom')?.value  || '';
    const dTo     = document.getElementById('dateTo')?.value    || '';
    const rows    = _getRows();
    let visible   = 0;

    const tax    = parseFloat(document.getElementById('taxInput')?.value)    || 0;
    const markup = parseFloat(document.getElementById('markupInput')?.value) || 0;
    const tFactor = 1 + (tax    / 100);
    const mFactor = 1 + (markup / 100);
    const exchRate = <?= $results ? (float)($results['summary']['exchangeRate'] ?? 5.39) : 5.39 ?>;

    let sumFob = 0, sumTax = 0, sumMarkup = 0;

    for (let i = 0, len = rows.length; i < len; i++) {
        const r = rows[i];
        const matchText = !q || r.search.includes(q);
        const matchFrom = !dFrom || !r.date || r.date >= dFrom;
        const matchTo   = !dTo   || !r.date || r.date <= dTo;
        const show      = matchText && matchFrom && matchTo;

        r.el.style.display = show ? '' : 'none';

        if (r.hasFob) {
            const withTax    = r.fob * tFactor;
            const withMarkup = withTax * mFactor;
            if (r.taxTd)    r.taxTd.textContent    = fmtUsdJs(withTax);
            if (r.markupTd) r.markupTd.textContent = fmtUsdJs(withMarkup);
            if (show) { sumFob += r.fob; sumTax += withTax; sumMarkup += withMarkup; visible++; }
        } else if (show) {
            visible++;
        }
    }

    const rc = document.getElementById('rowCount');
    if (rc) rc.textContent = visible;

    updateDateHint(dFrom, dTo);
    _updateCards(sumFob, sumTax, sumMarkup, exchRate);
}

function updateDateHint(dFrom, dTo) {
    const hint = document.getElementById('dateFilterHint');
    if (!hint) return;
    if (!dFrom && !dTo) { hint.textContent = ''; return; }
    const fmt = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '…';
    hint.textContent = `Período: ${fmt(dFrom)} – ${fmt(dTo)}`;
}

function fmtUsdJs(v) {
    return '$' + v.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function recalcTable() {
    const tax    = parseFloat(document.getElementById('taxInput').value)    || 0;
    const markup = parseFloat(document.getElementById('markupInput').value) || 0;
    const tFactor = 1 + (tax    / 100);
    const mFactor = 1 + (markup / 100);
    const exchRate = <?= $results ? (float)($results['summary']['exchangeRate'] ?? 5.39) : 5.39 ?>;

    const rows = _getRows();
    let sumFob = 0, sumTax = 0, sumMarkup = 0;

    for (let i = 0, len = rows.length; i < len; i++) {
        const r = rows[i];
        const visible = r.el.style.display !== 'none';
        if (r.hasFob) {
            const withTax    = r.fob * tFactor;
            const withMarkup = withTax * mFactor;
            if (r.taxTd)    r.taxTd.textContent    = fmtUsdJs(withTax);
            if (r.markupTd) r.markupTd.textContent = fmtUsdJs(withMarkup);
            if (visible) { sumFob += r.fob; sumTax += withTax; sumMarkup += withMarkup; }
        }
    }

    _updateCards(sumFob, sumTax, sumMarkup, exchRate);
}

function _updateCards(sumFob, sumTax, sumMarkup, exchRate) {
    const cardFob       = document.getElementById('cardTotalFob');
    const cardFobBrl    = document.getElementById('cardTotalFobBrl');
    const cardTax       = document.getElementById('cardTotalTax');
    const cardTaxBrl    = document.getElementById('cardTotalTaxBrl');
    const cardMarkup    = document.getElementById('cardTotalMarkup');
    const cardMarkupBrl = document.getElementById('cardTotalMarkupBrl');

    if (cardFob)       cardFob.textContent       = fmtUsdJs(sumFob);
    if (cardFobBrl)    cardFobBrl.textContent     = 'R$ ' + (sumFob * exchRate).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
    if (cardTax)       cardTax.textContent        = fmtUsdJs(sumTax);
    if (cardTaxBrl)    cardTaxBrl.textContent     = 'R$ ' + (sumTax * exchRate).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
    if (cardMarkup)    cardMarkup.textContent     = fmtUsdJs(sumMarkup);
    if (cardMarkupBrl) cardMarkupBrl.textContent  = 'R$ ' + (sumMarkup * exchRate).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
}

// Bootstrap Tooltips

// Auto-apply date filter on page load so pre-populated dates take effect
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('detailTable')) filterTable();
});
(function initColResize() {
    const table = document.getElementById('detailTable');
    if (!table) return;

    const ths = Array.from(table.querySelectorAll('thead tr:first-child th'));

    // Inject resize handles + lock current widths as pixels
    ths.forEach(th => {
        th.style.width = th.getBoundingClientRect().width + 'px';
        const h = document.createElement('div');
        h.className = 'col-resize-handle';
        th.appendChild(h);
    });
    table.style.tableLayout = 'fixed';

    let dragging = false, startX = 0, startW = 0, activeTh = null, activeHandle = null;

    ths.forEach(th => {
        const handle = th.querySelector('.col-resize-handle');
        handle.addEventListener('mousedown', e => {
            dragging   = true;
            startX     = e.clientX;
            startW     = th.getBoundingClientRect().width;
            activeTh   = th;
            activeHandle = handle;
            handle.classList.add('active');
            document.body.style.cursor = 'col-resize';
            e.preventDefault();
            e.stopPropagation();
        });
    });

    document.addEventListener('mousemove', e => {
        if (!dragging || !activeTh) return;
        const newW = Math.max(50, startW + (e.clientX - startX));
        activeTh.style.width = newW + 'px';
    });

    document.addEventListener('mouseup', () => {
        if (activeHandle) activeHandle.classList.remove('active');
        document.body.style.cursor = '';
        dragging = false; activeTh = null; activeHandle = null;
    });
})();
</script>
<?php if ($results): ?>
<script>
const pdfData = <?= json_encode([
    'clientName'     => $results['clientName']     ?? '',
    'referenceMonth' => $results['referenceMonth'] ?? '',
    'filename'       => $results['filename']       ?? '',
    'exchangeRate'   => (float)($results['summary']['exchangeRate'] ?? 5.39),
    'migrationType'  => $results['migrationType']  ?? 'mosp_csp',
    'migrationLabel' => migrationLabel($results['migrationType'] ?? 'mosp_csp'),
    'schemaKey'      => $results['schemaKey']      ?? 'mosa_2019-11-01',
    'schemaVersion'  => $results['schemaVersion']  ?? '2019-11-01',
    'schemaAgreement'=> $results['schemaAgreement'] ?? 'MOSA',
    'summary' => [
        'totalRows'       => $results['summary']['totalRows'],
        'uniqueMeterIds'  => $results['summary']['uniqueMeterIds'],
        'notFoundCount'   => $results['summary']['notFoundCount'],
        'totalMosp'       => (float)$results['summary']['totalMosp'],
        'totalMospBrl'    => (float)$results['summary']['totalMospBrl'],
        'totalCsp'        => (float)$results['summary']['totalCsp'],
        'totalCspBrl'     => (float)$results['summary']['totalCspBrl'],
        'differencePercent' => (float)$results['summary']['differencePercent'],
        'byService'       => array_map(fn($v) => ['costMosp' => (float)$v['costMosp'], 'costCsp' => (float)($v['costCsp'] ?? 0), 'count' => (int)$v['count']], array_slice($results['summary']['byService'], 0, 8, true)),
        'byResourceGroup' => array_map(fn($v) => ['costMosp' => (float)$v['costMosp'], 'costCsp' => (float)($v['costCsp'] ?? 0), 'count' => (int)$v['count']], array_slice($results['summary']['byResourceGroup'], 0, 8, true)),
    ],
], JSON_UNESCAPED_UNICODE) ?>;

// Pre-load logo once and cache it globally
(function _preloadPdfLogo() {
    if (window._finLogoDataUrl !== undefined) return;
    window._finLogoDataUrl = null;
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function() {
        const c = document.createElement('canvas');
        c.width = img.width; c.height = img.height;
        c.getContext('2d').drawImage(img, 0, 0);
        window._finLogoDataUrl = c.toDataURL('image/png');
    };
    img.onerror = function() { window._finLogoDataUrl = null; };
    img.src = 'logo.png';
})();

function _pdfHeader(doc, title, W, TEAL, BLUE, WHITE, DARK, GRAY, ML) {
    const GRAY_L = [160, 165, 170];
    const BORDER = [226, 232, 240];
    // Thin teal bar
    doc.setFillColor(...TEAL);
    doc.rect(0, 0, W, 4, 'F');
    // Logo or text
    const _logo = window._finLogoDataUrl || null;
    if (_logo) {
        try { doc.addImage(_logo, 'PNG', ML, 9, 36, 7.3); } catch(e) {}
    } else {
        doc.setFontSize(10); doc.setTextColor(...TEAL);
        doc.setFont('helvetica', 'bold'); doc.text('TD SYNNEX', ML, 15);
    }
    // Date right
    const _today = new Date().toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit', year:'numeric'});
    doc.setFontSize(7.5); doc.setTextColor(...GRAY_L);
    doc.setFont('helvetica', 'normal'); doc.text(_today, W - ML, 15, {align:'right'});
    // Separator
    doc.setDrawColor(...BORDER); doc.setLineWidth(0.3);
    doc.line(ML, 20, W - ML, 20);
    // Page title + accent line
    doc.setFontSize(13); doc.setTextColor(...DARK);
    doc.setFont('helvetica', 'bold'); doc.text(title, ML, 31);
    doc.setFillColor(...TEAL); doc.rect(ML, 34, 28, 1.8, 'F');
}

async function gerarPropostaPDF() {
    const btn = document.getElementById('btnPdf');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Gerando...'; }
    // Ensure logo is loaded
    if (window._finLogoDataUrl === null) {
        await new Promise(resolve => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function() {
                const c = document.createElement('canvas');
                c.width = img.width; c.height = img.height;
                c.getContext('2d').drawImage(img, 0, 0);
                window._finLogoDataUrl = c.toDataURL('image/png');
                resolve();
            };
            img.onerror = function() { window._finLogoDataUrl = null; resolve(); };
            img.src = 'logo.png';
        });
    }
    await new Promise(r => setTimeout(r, 30));
    try { _doGerarPDF(); } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-1"></i>Proposta PDF'; }
    }
}

function _doGerarPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const W = 210, H = 297, ML = 15, MR = 15;
    const CW = W - ML - MR;

    // Helper para decodificar entidades HTML
    const decodeHtml = (html) => {
        const txt = document.createElement('textarea');
        txt.innerHTML = html;
        return txt.value;
    };

    const TEAL      = [0, 87, 88];
    const TEAL_DARK = [0, 48, 49];
    const BLUE      = [0, 151, 215];
    const GREEN_C   = [130, 195, 65];
    const DARK      = [30, 41, 59];
    const GRAY      = [100, 116, 139];
    const WHITE     = [255, 255, 255];
    const LIGHT     = [248, 250, 252];
    const ECO_GREEN = [22, 163, 74];
    const ECO_RED   = [220, 38, 38];

    // --- Collect live data from DOM ---
    const tax    = parseFloat(document.getElementById('taxInput')?.value)    || 0;
    const markup = parseFloat(document.getElementById('markupInput')?.value) || 0;
    const tFactor = 1 + tax / 100;
    const mFactor = 1 + markup / 100;
    const exch = pdfData.exchangeRate;

    let sumFob = 0, sumMosp = 0;
    const visibleRows = [];
    document.querySelectorAll('#detailTable tbody tr').forEach(tr => {
        if (tr.style.display === 'none') return;
        const fobRaw = parseFloat(tr.querySelector('.col-total-fob')?.dataset?.fob) || 0;
        const mosp   = parseFloat(tr.dataset.mosp) || 0;
        sumFob  += fobRaw;
        sumMosp += mosp;
        const cells = tr.querySelectorAll('td');
        visibleRows.push({
            resource : (cells[0]?.querySelector('div')?.textContent || '—').trim(),
            date     : (cells[1]?.textContent || '—').trim(),
            service  : (cells[5]?.querySelector('div')?.textContent || '—').trim(),
            rg       : (cells[6]?.textContent || '—').trim(),
            region   : (cells[7]?.textContent || '—').trim(),
            mosp, fob: fobRaw,
            tax      : fobRaw * tFactor,
            markup   : fobRaw * tFactor * mFactor,
        });
    });
    const sumTax    = sumFob * tFactor;
    const sumMarkup = sumTax * mFactor;
    const saving    = sumMosp - sumFob;
    const isEco     = saving >= 0;
    const pctAbs    = sumMosp > 0 ? Math.abs(saving / sumMosp * 100) : 0;

    const fmtU  = v => '$' + v.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
    const fmtB  = v => 'R$ ' + (v * exch).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
    const fmtN  = (v, d) => v.toLocaleString('pt-BR', {minimumFractionDigits:d, maximumFractionDigits:d});
    const trunc = (s, n) => s && s.length > n ? s.substring(0, n - 1) + '...' : (s || '—');

    // ============================================================
    // PAGE 1 — CAPA (sql-advisor design)
    // ============================================================
    const TEAL_L   = [0, 128, 130];
    const GRAY_L   = [160, 165, 170];
    const BG       = [245, 247, 250];
    const BORDER   = [226, 232, 240];
    const CHARCOAL = [38, 38, 38];
    const todayFull = new Date().toLocaleDateString('pt-BR', {day:'numeric', month:'long', year:'numeric'});
    const dFrom = document.getElementById('dateFrom')?.value;
    const dTo   = document.getElementById('dateTo')?.value;
    const fmtDate = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '...';

    // White background
    doc.setFillColor(...WHITE); doc.rect(0, 0, W, H, 'F');
    // Thin teal bar
    doc.setFillColor(...TEAL); doc.rect(0, 0, W, 4, 'F');
    // Logo
    const _logo = window._finLogoDataUrl || null;
    if (_logo) {
        try { doc.addImage(_logo, 'PNG', ML, 18, 50, 10.2); } catch(e) {}
    } else {
        doc.setFontSize(16); doc.setTextColor(...TEAL_DARK);
        doc.setFont('helvetica', 'bold'); doc.text('TD SYNNEX', ML, 26);
    }
    // Teal side accent
    doc.setFillColor(...TEAL); doc.rect(0, 80, 6, 95, 'F');

    // Title
    doc.setFontSize(11); doc.setTextColor(...TEAL_L);
    doc.setFont('helvetica', 'bold');
    doc.text('PROPOSTA COMERCIAL', ML + 8, 100);

    doc.setFontSize(30); doc.setTextColor(...CHARCOAL);
    doc.setFont('helvetica', 'bold');
    doc.text('Analise Financeira', ML + 8, 118);
    doc.setFontSize(22);
    doc.text(decodeHtml(pdfData.migrationLabel) || 'MOSP para Azure CSP', ML + 8, 131);

    doc.setFontSize(12); doc.setTextColor(...GRAY);
    doc.setFont('helvetica', 'normal');
    doc.text('Comparativo de Custos via TD SYNNEX', ML + 8, 141);
    // Schema info
    const schemaLabel = (pdfData.schemaAgreement || 'MOSA') + ' ' + (pdfData.schemaVersion || '2019-11-01');
    doc.setFontSize(8); doc.setTextColor(...GRAY);
    doc.text('Dataset Schema: ' + schemaLabel, ML + 8, 149);

    // Client box
    const clientBoxY = 162;
    doc.setFillColor(...BG);
    doc.roundedRect(ML + 8, clientBoxY, CW - 8, 28, 3, 3, 'F');
    doc.setFillColor(...TEAL); doc.rect(ML + 8, clientBoxY, 3, 28, 'F');
    doc.setFontSize(8); doc.setTextColor(...TEAL_L);
    doc.setFont('helvetica', 'bold');
    doc.text('PREPARADO PARA', ML + 18, clientBoxY + 9);
    doc.setFontSize(17); doc.setTextColor(...CHARCOAL);
    doc.setFont('helvetica', 'bold');
    const clientLabel = (pdfData.clientName || 'Cliente').substring(0, 42);
    doc.text(clientLabel, ML + 18, clientBoxY + 21);

    // Bottom metadata bar
    const metaY = H - 52;
    doc.setDrawColor(...BORDER); doc.setLineWidth(0.3);
    doc.line(ML, metaY, W - MR, metaY);
    const metaCols = [
        {label: 'DATA DA ANALISE', value: todayFull},
        {label: 'PERIODO',         value: (dFrom || dTo) ? fmtDate(dFrom) + ' a ' + fmtDate(dTo) : 'Todo o periodo'},
    ];
    const colW_ = CW / metaCols.length;
    metaCols.forEach((col, i) => {
        const cx = ML + i * colW_;
        doc.setFontSize(7); doc.setTextColor(...TEAL_L);
        doc.setFont('helvetica', 'bold'); doc.text(col.label, cx, metaY + 9);
        doc.setFontSize(9); doc.setTextColor(...CHARCOAL);
        doc.setFont('helvetica', 'normal');
        const val = col.value.length > 28 ? col.value.substring(0, 27) + '…' : col.value;
        doc.text(val, cx, metaY + 17);
    });
    // Confidential note
    doc.setFontSize(7); doc.setTextColor(...GRAY_L);
    doc.setFont('helvetica', 'italic');
    doc.text('Documento Confidencial — TD SYNNEX Brasil — Valores sujeitos a alteracao', ML, H - 16);

    let y = 125; // reset y (used by pages 2+)

    // ============================================================
    // PAGE 2 — SUMARIO EXECUTIVO
    // ============================================================
    doc.addPage();
    _pdfHeader(doc, 'Sumario Executivo', W, TEAL, BLUE, WHITE, DARK, GRAY, ML);

    y = 44;

    // Row 1: 3 metric cards
    const cardW3 = (CW - 8) / 3;
    [
        { label: 'Total MOSP (Atual)',  val: fmtU(sumMosp),   sub: fmtB(sumMosp),   color: GRAY },
        { label: 'Total CSP FOB',       val: fmtU(sumFob),    sub: fmtB(sumFob),    color: TEAL },
        { label: 'Taxa de Cambio',      val: 'R$ ' + fmtN(exch, 2), sub: 'USD > BRL', color: BLUE },
    ].forEach((m, i) => {
        const x = ML + i * (cardW3 + 4);
        doc.setFillColor(...LIGHT);
        doc.roundedRect(x, y, cardW3, 26, 3, 3, 'F');
        doc.setFillColor(...m.color);
        doc.rect(x, y, cardW3, 2, 'F');
        doc.setFont('helvetica', 'bold'); doc.setFontSize(7); doc.setTextColor(...GRAY);
        doc.text(m.label.toUpperCase(), x + 3, y + 9);
        doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.setTextColor(...DARK);
        doc.text(m.val, x + 3, y + 18);
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor(...GRAY);
        doc.text(m.sub, x + 3, y + 23);
    });
    y += 32;

    // Row 2: 3 more cards
    [
        { label: tax > 0 ? 'Total c/ Impostos (' + fmtN(tax,2) + '%)' : 'Total c/ Impostos', val: fmtU(sumTax), sub: fmtB(sumTax), color: BLUE },
        { label: markup > 0 ? 'Total c/ Markup (' + fmtN(markup,1) + '%)' : 'Total c/ Markup',     val: fmtU(sumMarkup), sub: fmtB(sumMarkup), color: [0,150,136] },
        { label: isEco ? 'Economia (MOSP vs CSP)' : 'Variacao (MOSP vs CSP)', val: (isEco ? '-' : '+') + fmtU(Math.abs(saving)), sub: (isEco ? '-' : '+') + fmtN(pctAbs,1) + '%', color: isEco ? ECO_GREEN : ECO_RED },
    ].forEach((m, i) => {
        const x = ML + i * (cardW3 + 4);
        doc.setFillColor(...LIGHT);
        doc.roundedRect(x, y, cardW3, 26, 3, 3, 'F');
        doc.setFillColor(...m.color);
        doc.rect(x, y, cardW3, 2, 'F');
        doc.setFont('helvetica', 'bold'); doc.setFontSize(7); doc.setTextColor(...GRAY);
        doc.text(m.label.toUpperCase(), x + 3, y + 9);
        doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.setTextColor(...DARK);
        doc.text(m.val, x + 3, y + 18);
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor(...GRAY);
        doc.text(m.sub, x + 3, y + 23);
    });
    y += 34;

    // Parameters table
    doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(...DARK);
    doc.text('Parametros da Analise', ML, y); y += 5;

    const params = [
        ['Total de registros no CSV',          pdfData.summary.totalRows + ' registros'],
        ['Linhas exibidas (filtro ativo)',      visibleRows.length + ' registros'],
        ['MeterIDs unicos consultados na API',  pdfData.summary.uniqueMeterIds + ' IDs'],
        ['MeterIDs encontrados na API',         (pdfData.summary.uniqueMeterIds - pdfData.summary.notFoundCount) + ' de ' + pdfData.summary.uniqueMeterIds],
        ['Impostos aplicados',                  tax > 0 ? fmtN(tax, 2) + '%' : 'Nao aplicado'],
        ['Markup aplicado',                     markup > 0 ? fmtN(markup, 1) + '%' : 'Nao aplicado'],
        ['Periodo analisado',                   dFrom ? fmtDate(dFrom) + ' a ' + fmtDate(dTo) : 'Todo o periodo'],
        ['Arquivo CSV',                         pdfData.filename || '—'],
    ];
    params.forEach(([label, val], i) => {
        const ry = y + i * 8.5;
        if (i % 2 === 0) { doc.setFillColor(...LIGHT); doc.rect(ML, ry - 3.5, CW, 8.5, 'F'); }
        doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor(...GRAY);
        doc.text(label, ML + 3, ry + 1);
        doc.setFont('helvetica', 'bold'); doc.setTextColor(...DARK);
        doc.text(val, W - MR, ry + 1, {align: 'right'});
    });
    y += params.length * 8.5 + 8;

    // Top Services
    const svcEntries = Object.entries(pdfData.summary.byService);
    if (svcEntries.length > 0) {
        doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(...DARK);
        doc.text('Principais Servicos Azure', ML, y); y += 3;
        const svcRows = svcEntries.slice(0, 7).map(([name, d]) => [
            trunc(name, 38),
            fmtU(d.costCsp),
            d.count + ' linhas',
        ]);
        doc.autoTable({
            startY: y,
            head: [['Servico', 'Custo CSP FOB', 'Linhas']],
            body: svcRows,
            styles: { fontSize: 8, cellPadding: 2 },
            headStyles: { fillColor: TEAL, textColor: WHITE, fontStyle: 'bold', fontSize: 8 },
            alternateRowStyles: { fillColor: LIGHT },
            margin: { left: ML, right: MR },
            tableWidth: CW,
            columnStyles: { 0: {cellWidth: 95}, 1: {halign:'right'}, 2: {halign:'right'} },
        });
        y = doc.lastAutoTable.finalY + 6;
    }

    // Top Resource Groups
    const rgEntries = Object.entries(pdfData.summary.byResourceGroup);
    if (rgEntries.length > 0 && y < H - 60) {
        doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(...DARK);
        doc.text('Principais Resource Groups', ML, y); y += 3;
        const rgRows = rgEntries.slice(0, 6).map(([name, d]) => [
            trunc(name, 38),
            fmtU(d.costMosp),
            fmtU(d.costCsp),
            d.count + ' linhas',
        ]);
        doc.autoTable({
            startY: y,
            head: [['Resource Group', 'Custo MOSP', 'Custo CSP FOB', 'Linhas']],
            body: rgRows,
            styles: { fontSize: 8, cellPadding: 2 },
            headStyles: { fillColor: TEAL, textColor: WHITE, fontStyle: 'bold', fontSize: 8 },
            alternateRowStyles: { fillColor: LIGHT },
            margin: { left: ML, right: MR },
            tableWidth: CW,
            columnStyles: { 0: {cellWidth: 70}, 1: {halign:'right'}, 2: {halign:'right'}, 3: {halign:'right'} },
        });
    }

    // ============================================================
    // PAGE 3 — POR QUE AZURE CSP VIA TD SYNNEX
    // ============================================================
    doc.addPage();
    _pdfHeader(doc, 'Por que Azure CSP via TD SYNNEX?', W, TEAL, BLUE, WHITE, DARK, GRAY, ML);

    y = 44;
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9.5); doc.setTextColor(...GRAY);
    const introTxt = 'A migracao para o modelo Azure CSP via TD SYNNEX representa a estrategia mais vantajosa para empresas que buscam otimizar custos de nuvem com suporte especializado, faturamento local e condicoes comerciais exclusivas no mercado brasileiro.';
    const introLines = doc.splitTextToSize(introTxt, CW);
    doc.text(introLines, ML, y);
    y += introLines.length * 4.8 + 8;

    const benefits = [
        {
            title: 'Precos CSP com Desconto vs. MOSP (Pay-As-You-Go)',
            desc:  'Acesso a precos do canal Microsoft CSP, sistematicamente inferiores ao modelo MOSP. Faturamento flexivel em BRL elimina exposicao ao cambio USD e simplifica o planejamento financeiro.',
        },
        {
            title: 'Suporte Especializado em Portugues',
            desc:  'Time dedicado de especialistas Azure certificados, com SLA de atendimento, suporte tecnico L1/L2 em portugues e acompanhamento proativo da assinatura para maximizar eficiencia operacional.',
        },
        {
            title: 'Faturamento em Real com Nota Fiscal Brasileira',
            desc:  'Emissao de NF-e brasileira, contrato local em conformidade com a legislacao nacional (LGPD, ANATEL), sem complexidades de transacoes internacionais ou exposicao cambial.',
        },
        {
            title: 'Governanca, FinOps e Gestao de Custos',
            desc:  'Implementacao de politicas de governanca, gestao de budget por centro de custo, alertas automaticos de consumo e relatorios periodicos de rightsizing para eliminar recursos ociosos.',
        },
        {
            title: 'Azure Hybrid Benefit + Reserved Instances',
            desc:  'Orientacao para aproveitar o Azure Hybrid Benefit (reutilizacao de licencas Windows Server e SQL Server existentes) e Reserved Instances com economia adicional de ate 72% vs PAYG.',
        },
        {
            title: 'Flexibilidade sem Lock-in de Contrato',
            desc:  'O modelo CSP oferece escala imediata sem compromisso de prazo minimo obrigatorio, permitindo ajustar recursos conforme a demanda do negocio mes a mes com total agilidade.',
        },
    ];

    benefits.forEach(b => {
        const descLines = doc.splitTextToSize(b.desc, CW - 12);
        const boxH = 9 + descLines.length * 4.3 + 4;
        if (y + boxH > H - 28) { doc.addPage(); _pdfHeader(doc, 'Por que Azure CSP via TD SYNNEX?', W, TEAL, BLUE, WHITE, DARK, GRAY, ML); y = 44; }
        doc.setFillColor(240, 253, 252);
        doc.roundedRect(ML, y, CW, boxH, 3, 3, 'F');
        doc.setFillColor(...TEAL);
        doc.rect(ML, y, 3, boxH, 'F');
        doc.setFont('helvetica', 'bold'); doc.setFontSize(9.5); doc.setTextColor(...TEAL);
        doc.text(b.title, ML + 7, y + 7);
        doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor(...DARK);
        doc.text(descLines, ML + 7, y + 13);
        y += boxH + 5;
    });

    // TD SYNNEX promo box
    if (y + 28 < H - 28) {
        doc.setFillColor(...TEAL);
        doc.roundedRect(ML, y, CW, 28, 4, 4, 'F');
        doc.setTextColor(...WHITE);
        doc.setFont('helvetica', 'bold'); doc.setFontSize(11);
        doc.text('TD SYNNEX - Maior Distribuidor Microsoft no Brasil', W / 2, y + 10, {align: 'center'});
        doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5);
        const promoLines = doc.splitTextToSize('Acesso a programas exclusivos Microsoft, descontos de back-end, rebates e beneficios do programa CSP Tier 1 - todos repassados em condicoes competitivas ao cliente final.', CW - 20);
        doc.text(promoLines, W / 2, y + 18, {align: 'center'});
        y += 36;
    }

    // ============================================================
    // PAGE 4 — DETALHAMENTO LINHA A LINHA
    // ============================================================
    if (visibleRows.length > 0) {
        doc.addPage();
        _pdfHeader(doc, 'Detalhamento de Recursos (' + visibleRows.length + ' linhas)', W, TEAL, BLUE, WHITE, DARK, GRAY, ML);

        const hasTax    = tax > 0;
        const hasMarkup = markup > 0;
        const headers   = ['Recurso', 'Servico', 'Resource Group', 'Data', 'Regiao', 'CSP FOB',
            ...(hasTax    ? ['c/ Impostos'] : []),
            ...(hasMarkup ? ['c/ Markup']   : []),
        ];
        const tableRows = visibleRows.map(r => [
            trunc(r.resource, 26),
            trunc(r.service,  20),
            trunc(r.rg,       16),
            r.date,
            trunc(r.region,   10),
            fmtU(r.fob),
            ...(hasTax    ? [fmtU(r.tax)]    : []),
            ...(hasMarkup ? [fmtU(r.markup)] : []),
        ]);

        doc.autoTable({
            startY: 38,
            head: [headers],
            body: tableRows,
            styles: { fontSize: 6, cellPadding: 1.5, overflow: 'ellipsize' },
            headStyles: { fillColor: TEAL, textColor: WHITE, fontStyle: 'bold', fontSize: 6.5 },
            alternateRowStyles: { fillColor: LIGHT },
            margin: { left: ML, right: MR },
            tableWidth: CW,
            columnStyles: {
                0: {cellWidth: 34},
                1: {cellWidth: 30},
                2: {cellWidth: 24},
                3: {cellWidth: 17},
                4: {cellWidth: 16},
                5: {halign:'right', cellWidth: 18},
                ...(hasTax    ? {6: {halign:'right', cellWidth: 18}} : {}),
                ...(hasMarkup ? {7: {halign:'right', cellWidth: 18}} : {}),
            },
            didDrawPage: data => {
                const pg = doc.getCurrentPageInfo().pageNumber;
                const fy = H - 14;
                doc.setDrawColor(226, 232, 240); doc.setLineWidth(0.3);
                doc.line(ML, fy, W - MR, fy);
                doc.setTextColor(160, 165, 170); doc.setFont('helvetica','normal'); doc.setFontSize(6.5);
                doc.text('TD SYNNEX  |  Documento Confidencial  |  Valores estimados sujeitos a alteracao', ML, fy + 5);
                doc.text('Pag. ' + pg, W - MR, fy + 5, {align: 'right'});
            },
        });
    }

    // Footer on pages 1–3 (and page 4 cover if no detail)
    const totalPages = doc.getNumberOfPages();
    const detailStart = visibleRows.length > 0 ? totalPages - (doc.getNumberOfPages() - 3) : 99;
    for (let p = 1; p <= totalPages; p++) {
        if (p >= 4 && visibleRows.length > 0) continue; // handled by autoTable didDrawPage
        doc.setPage(p);
        if (p > 1) { // page 1 already has its own footer
            const fy = H - 14;
            doc.setDrawColor(226, 232, 240); doc.setLineWidth(0.3);
            doc.line(ML, fy, W - MR, fy);
            doc.setTextColor(160, 165, 170); doc.setFont('helvetica','normal'); doc.setFontSize(6.5);
            doc.text('TD SYNNEX  |  Documento Confidencial  |  Valores estimados sujeitos a alteracao', ML, fy + 5);
            doc.text('Pag. ' + p + ' / ' + totalPages, W - MR, fy + 5, {align: 'right'});
        }
    }

    const fname = 'Proposta_Azure_CSP_' +
        (pdfData.clientName ? pdfData.clientName.replace(/[^a-zA-Z0-9]/g, '_') + '_' : '') +
        new Date().toISOString().slice(0, 10) + '.pdf';
    doc.save(fname);
}
</script>
<?php endif; ?>

<script>
// ============================================================
// SISTEMA DE TRADUÇÃO / i18n
// ============================================================
const translations = {
    'pt-BR': {
        'header.subtitle': 'Ferramentas e Calculadoras',
        'nav.home': 'Home',
        'nav.migration': 'Migracao Azure',
        'breadcrumb.migration': 'Migracao Azure',
        'breadcrumb.financial': 'Analise Financeira',
        'title.main': 'Analise Financeira para Migracao Azure',
        'title.description': 'Upload do export do Cost Management',
        'label.schema': 'Schema:',
        'label.qty': 'Qty:',
        'alert.error': 'Erro',
        'alert.success': 'Sucesso',
        'form.upload': 'Upload e Analise',
        'form.file': 'Arquivo CSV',
        'form.selectFile': 'Selecione o arquivo',
        'form.agreement': 'Agreement/Subscription',
        'form.schema': 'Schema',
        'form.clientName': 'Nome do Cliente',
        'form.month': 'Mes Referencia',
        'form.analyze': 'Analisar',
        'form.analyzing': 'Analisando',
        'summary.title': 'Resumo Geral',
        'summary.totalRows': 'Total de Linhas',
        'summary.uniqueMeters': 'Meters Unicos',
        'summary.successApi': 'API OK',
        'summary.errorApi': 'Erro API',
        'summary.costMosp': 'Custo MOSP',
        'summary.costCsp': 'Custo CSP FOB',
        'summary.difference': 'Diferenca',
        'summary.savings': 'Economia',
        'table.resource': 'Recurso',
        'table.service': 'Servico',
        'table.resourceGroup': 'Resource Group',
        'table.date': 'Data',
        'table.region': 'Regiao',
        'table.quantity': 'Quantidade',
        'table.unit': 'Unidade',
        'table.costCsp': 'Custo CSP',
        'table.actions': 'Acoes',
        'btn.exportCsv': 'Exportar CSV',
        'btn.generatePdf': 'Proposta PDF',
        'btn.reset': 'Limpar Dados',
        'footer.confidential': 'TD SYNNEX | Documento Confidencial | Valores estimados sujeitos a alteracao',
    },
    'en-US': {
        'header.subtitle': 'Tools and Calculators',
        'nav.home': 'Home',
        'nav.migration': 'Azure Migration',
        'breadcrumb.migration': 'Azure Migration',
        'breadcrumb.financial': 'Financial Analysis MOSP - CSP',
        'title.main': 'Financial Analysis for Azure Migration',
        'title.description': 'Upload Cost Management export',
        'label.schema': 'Schema:',
        'label.qty': 'Qty:',
        'alert.error': 'Error',
        'alert.success': 'Success',
        'form.upload': 'Upload and Analysis',
        'form.file': 'CSV File',
        'form.selectFile': 'Select file',
        'form.agreement': 'Agreement/Subscription',
        'form.schema': 'Schema',
        'form.clientName': 'Client Name',
        'form.month': 'Reference Month',
        'form.analyze': 'Analyze',
        'form.analyzing': 'Analyzing',
        'summary.title': 'General Summary',
        'summary.totalRows': 'Total Rows',
        'summary.uniqueMeters': 'Unique Meters',
        'summary.successApi': 'API OK',
        'summary.errorApi': 'API Error',
        'summary.costMosp': 'MOSP Cost',
        'summary.costCsp': 'CSP FOB Cost',
        'summary.difference': 'Difference',
        'summary.savings': 'Savings',
        'table.resource': 'Resource',
        'table.service': 'Service',
        'table.resourceGroup': 'Resource Group',
        'table.date': 'Date',
        'table.region': 'Region',
        'table.quantity': 'Quantity',
        'table.unit': 'Unit',
        'table.costCsp': 'CSP Cost',
        'table.actions': 'Actions',
        'btn.exportCsv': 'Export CSV',
        'btn.generatePdf': 'PDF Proposal',
        'btn.reset': 'Clear Data',
        'footer.confidential': 'TD SYNNEX | Confidential Document | Estimated values subject to change',
    },
    'es-ES': {
        'header.subtitle': 'Herramientas y Calculadoras',
        'nav.home': 'Inicio',
        'nav.migration': 'Migracion Azure',
        'breadcrumb.migration': 'Migracion Azure',
        'breadcrumb.financial': 'Analisis Financiero MOSP - CSP',
        'title.main': 'Analisis Financiero de Migracion',
        'title.description': 'Cargue la exportacion de Cost Management y compare costos a traves de la API publica de Microsoft.',
        'label.schema': 'Schema:',
        'label.qty': 'Cant:',
        'alert.error': 'Error',
        'alert.success': 'Exito',
        'form.upload': 'Carga y Analisis',
        'form.file': 'Archivo CSV',
        'form.selectFile': 'Seleccionar archivo',
        'form.agreement': 'Acuerdo/Suscripcion',
        'form.schema': 'Schema',
        'form.clientName': 'Nombre del Cliente',
        'form.month': 'Mes Referencia',
        'form.analyze': 'Analizar',
        'form.analyzing': 'Analizando',
        'summary.title': 'Resumen General',
        'summary.totalRows': 'Total de Lineas',
        'summary.uniqueMeters': 'Medidores Unicos',
        'summary.successApi': 'API OK',
        'summary.errorApi': 'Error API',
        'summary.costMosp': 'Costo MOSP',
        'summary.costCsp': 'Costo CSP FOB',
        'summary.difference': 'Diferencia',
        'summary.savings': 'Ahorro',
        'table.resource': 'Recurso',
        'table.service': 'Servicio',
        'table.resourceGroup': 'Grupo de Recursos',
        'table.date': 'Fecha',
        'table.region': 'Region',
        'table.quantity': 'Cantidad',
        'table.unit': 'Unidad',
        'table.costCsp': 'Costo CSP',
        'table.actions': 'Acciones',
        'btn.exportCsv': 'Exportar CSV',
        'btn.generatePdf': 'Propuesta PDF',
        'btn.reset': 'Limpiar Datos',
        'footer.confidential': 'TD SYNNEX | Documento Confidencial | Valores estimados sujetos a cambios',
    }
};

let currentLanguage = localStorage.getItem('appLanguage') || 'pt-BR';

function applyTranslations(lang) {
    const trans = translations[lang] || translations['pt-BR'];
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (trans[key]) {
            if (el.tagName === 'INPUT' && el.type === 'submit') {
                el.value = trans[key];
            } else if (el.hasAttribute('placeholder')) {
                el.placeholder = trans[key];
            } else {
                el.textContent = trans[key];
            }
        }
    });
}

function setLanguage(lang) {
    currentLanguage = lang;
    localStorage.setItem('appLanguage', lang);
    
    const langLabels = {
        'pt-BR': 'PT-BR',
        'en-US': 'EN-US',
        'es-ES': 'Español'
    };
    
    const currentLangEl = document.getElementById('currentLang');
    if (currentLangEl) {
        currentLangEl.textContent = langLabels[lang] || 'PT-BR';
    }
    
    applyTranslations(lang);
}

// Event listeners para seletor de idioma
document.addEventListener('DOMContentLoaded', () => {
    // Aplicar idioma salvo
    setLanguage(currentLanguage);
    
    // Click nos itens do dropdown
    document.querySelectorAll('[data-lang]').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const lang = e.target.closest('[data-lang]').getAttribute('data-lang');
            setLanguage(lang);
        });
    });
});
</script>

</body>
</html>