<?php
declare(strict_types=1);
session_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Shared\Services\FileParser;
use App\Features\AzureMigration\Services\AzureResourceAnalyzer;
use App\Shared\Services\ReportGenerator;

$uploadDir  = __DIR__ . '/../uploads';
$reportsDir = __DIR__ . '/../reports';

$fileParser      = new FileParser();
$analyzer        = new AzureResourceAnalyzer();
$reportGenerator = new ReportGenerator($reportsDir);

$fileParser->cleanOldFiles($uploadDir, 24);
$reportGenerator->cleanOldReports(7);

$error           = null;
$success         = null;
$analysisResults = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'analyze';

    if ($action === 'analyze' && isset($_FILES['file'])) {
        $file       = $_FILES['file'];
        $validation = $fileParser->validateFile($file);

        if (!$validation['valid']) {
            $error = $validation['error'];
        } else {
            $moveResult = $fileParser->moveUploadedFile($file, $uploadDir);
            if (!$moveResult['success']) {
                $error = $moveResult['error'];
            } else {
                $parseResult = $fileParser->parseFile($moveResult['path']);
                if (!$parseResult['success']) {
                    $error = $parseResult['error'];
                } else {
                    $analysisResults = $analyzer->analyzeResources($parseResult['data']);
                    $analysisResults['recommendations'] = $analyzer->generateRecommendations($analysisResults);
                    $_SESSION['analysisResults'] = $analysisResults;
                    $_SESSION['clientName']      = filter_input(INPUT_POST, 'clientName', FILTER_SANITIZE_SPECIAL_CHARS);
                    $success = "Análise concluída! {$analysisResults['summary']['total']} recursos analisados.";
                }
                unlink($moveResult['path']);
            }
        }
    } elseif ($action === 'generate_pdf') {
        if (isset($_SESSION['analysisResults'])) {
            $customNotes = [];
            if (isset($_POST['customNotes'])) {
                $customNotes = json_decode($_POST['customNotes'], true) ?? [];
            }
            $result = $reportGenerator->generatePDF(
                $_SESSION['analysisResults'],
                $_SESSION['clientName'] ?? null,
                $customNotes
            );
            if ($result['success']) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . basename($result['filename']) . '"');
                header('Content-Length: ' . filesize($result['path']));
                readfile($result['path']);
                exit;
            } else {
                $error = $result['error'];
            }
        } else {
            $error = 'Nenhuma análise disponível. Faça o upload de um arquivo primeiro.';
        }
        $analysisResults = $_SESSION['analysisResults'] ?? null;
    } elseif ($action === 'new_analysis') {
        unset($_SESSION['analysisResults']);
        unset($_SESSION['clientName']);
        header('Location: analise-migracao.php');
        exit;
    } elseif ($action === 'generate_excel') {
        if (isset($_SESSION['analysisResults'])) {
            $customNotes = [];
            if (isset($_POST['customNotes'])) {
                $customNotes = json_decode($_POST['customNotes'], true) ?? [];
            }
            $result = $reportGenerator->generateExcel(
                $_SESSION['analysisResults'],
                $_SESSION['clientName'] ?? null,
                $customNotes
            );
            if ($result['success']) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . basename($result['filename']) . '"');
                header('Content-Length: ' . filesize($result['path']));
                readfile($result['path']);
                exit;
            } else {
                $error = $result['error'];
            }
        } else {
            $error = 'Nenhuma análise disponível. Faça o upload de um arquivo primeiro.';
        }
        $analysisResults = $_SESSION['analysisResults'] ?? null;
    }
} else {
    $analysisResults = $_SESSION['analysisResults'] ?? null;
}

$dbMetadata = $analyzer->getDatabaseMetadata();
$dbStats    = $analyzer->getDatabaseStats();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise de Migração Azure - TD SYNNEX Tools</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', 'Segoe UI', sans-serif; }

        /* ---- App header override ---- */
        .app-header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.875rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        /* ---- Migration tool styles ---- */
        :root {
            --td-blue: #0097D7;
            --td-green: #82C341;
            --td-yellow: #FFD100;
            --td-red:  #D9272E;
            --td-dark: #1e293b;
            --td-gray: #64748b;
        }

        .mig-card {
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            border-radius: 10px;
            background: #fff;
            margin-bottom: 1.25rem;
        }
        .mig-card .card-header {
            background: #fff;
            border-bottom: 1px solid #f1f5f9;
            padding: 1rem 1.25rem;
            font-weight: 600;
            font-size: .9rem;
            color: var(--td-dark);
            border-radius: 10px 10px 0 0 !important;
            display: flex;
            align-items: center;
        }
        .mig-card .card-header i { color: var(--td-blue); font-size: 1rem; }

        .upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 40px 16px;
            text-align: center;
            transition: all .2s;
            background: #f8fafc;
            cursor: pointer;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--td-blue);
            background: rgba(0,151,215,.04);
        }
        .upload-zone i { font-size: 2.5rem; color: #94a3b8; transition: color .2s; }
        .upload-zone:hover i { color: var(--td-blue); }

        .stat-card {
            padding: 20px;
            border-radius: 10px;
            background: #fff;
            border-left: 4px solid transparent;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            height: 100%;
            transition: transform .15s;
            position: relative;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-number { font-size: 2rem; font-weight: 700; line-height: 1.2; margin-bottom: 2px; }
        .stat-label  { font-size: .75rem; text-transform: uppercase; letter-spacing: .8px; color: var(--td-gray); font-weight: 600; }
        .stat-percent{ font-size: .8rem; font-weight: 500; margin-top: 4px; display: block; }
        .stat-movable      { border-left-color: var(--td-green); }
        .stat-movable .stat-number { color: var(--td-green); }
        .stat-restrictions { border-left-color: var(--td-yellow); }
        .stat-restrictions .stat-number { color: #b38600; }
        .stat-not-movable  { border-left-color: var(--td-red); }
        .stat-not-movable .stat-number { color: var(--td-red); }
        .stat-total        { border-left-color: var(--td-blue); }
        .stat-total .stat-number { color: var(--td-blue); }
        .stat-unknown      { border-left-color: #94a3b8; }
        .stat-unknown .stat-number { color: #475569; }

        .filter-card { cursor: pointer; transition: all .15s; }
        .filter-card:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,.1); }
        .filter-card.active { box-shadow: 0 0 0 2px var(--td-blue), 0 4px 14px rgba(0,151,215,.2); transform: translateY(-1px); }

        .filter-badge {
            display: none; position: fixed; top: 76px; right: 20px;
            background: #005758; color: white; padding: 6px 14px;
            border-radius: 20px; font-size: .8rem; font-weight: 500;
            z-index: 1000; box-shadow: 0 3px 10px rgba(0,0,0,.2);
        }
        .filter-badge.show { display: flex; align-items: center; gap: 8px; }
        .filter-badge .clear-filter {
            background: rgba(255,255,255,.2); border: none; color: white;
            padding: 2px 6px; border-radius: 10px; cursor: pointer; font-size: .7rem;
        }

        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: .78rem; font-weight: 600; }
        .status-movable     { background: rgba(130,195,65,.14); color: #4a7a22; }
        .status-restrictions{ background: rgba(255,209,0,.14); color: #a07800; }
        .status-not-movable { background: rgba(217,39,46,.14); color: #9b1920; }
        .status-unknown     { background: #f1f5f9; color: #475569; }

        .recommendation {
            padding: 14px 16px; border-radius: 6px; margin-bottom: 10px;
            border-left: 3px solid; background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        .recommendation-success{ border-color: var(--td-green); }
        .recommendation-warning{ border-color: var(--td-yellow); }
        .recommendation-danger { border-color: var(--td-red); }
        .recommendation-info   { border-color: var(--td-blue); }

        .table-results { font-size: .87rem; }
        .table-results thead th {
            background: #f8fafc; color: var(--td-dark); font-weight: 600;
            border-bottom: 2px solid #e2e8f0; padding: 12px 14px;
            text-transform: uppercase; font-size: .72rem; letter-spacing: .8px;
            cursor: pointer; user-select: none;
        }
        .table-results thead th:hover { background: #f1f5f9; }
        .table-results tbody td { padding: 12px 14px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }

        .btn-azure {
            background: var(--td-blue); border-color: var(--td-blue); color: white;
            font-weight: 600; border-radius: 6px; transition: all .15s;
        }
        .btn-azure:hover {
            background: #007bb5; border-color: #007bb5; color: white;
            transform: translateY(-1px); box-shadow: 0 3px 8px rgba(0,151,215,.25);
        }
        .btn-azure:disabled { background: #a0d6ee; border-color: #a0d6ee; }

        .resource-type {
            font-family: 'Consolas','Monaco',monospace;
            font-size: .82rem; color: var(--td-blue);
        }

        .table-container { max-height: 560px; overflow-y: auto; scrollbar-width: thin; }

        .note-btn {
            background: none; border: none; padding: 3px 7px; cursor: pointer;
            color: #94a3b8; font-size: .95rem; border-radius: 4px; transition: all .15s;
        }
        .note-btn:hover { color: var(--td-blue); background: rgba(0,151,215,.08); }
        .note-btn.has-note { color: var(--td-blue); background: rgba(0,151,215,.08); }
        .note-btn.has-note::after {
            content: ''; display: inline-block; width: 5px; height: 5px;
            background: var(--td-green); border-radius: 50%; margin-left: 2px; vertical-align: super;
        }
        .note-preview { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .78rem; color: var(--td-gray); }
        .notes-count-badge { background: var(--td-blue); color: white; font-size: .68rem; padding: 1px 6px; border-radius: 10px; margin-left: 6px; }
        .clear-notes-btn { background: none; border: none; color: var(--td-gray); font-size: .78rem; cursor: pointer; padding: 3px 7px; border-radius: 4px; }
        .clear-notes-btn:hover { color: var(--td-red); background: rgba(217,39,46,.08); }
        tr.has-custom-note { background: rgba(0,151,215,.02) !important; }
        .note-modal-resource-info { background: #f8fafc; border-radius: 6px; padding: 12px; margin-bottom: 12px; }
        .db-info { font-size: .78rem; color: var(--td-gray); margin-top: 12px; }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../templates/topbar.php'; ?>

    <div class="container-fluid px-4" style="max-width:1400px; margin:0 auto;">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3" style="margin-top:16px;">
            <ol class="breadcrumb" style="font-size:.82rem;">
                <li class="breadcrumb-item"><a href="home.php" style="color:var(--td-blue); text-decoration:none;">Home</a></li>
                <li class="breadcrumb-item active">Análise Técnica de Recursos</li>
            </ol>
        </nav>

        <!-- Título -->
        <div class="mb-4 d-flex align-items-center gap-3">
            <div style="background:#dbeafe; padding:10px; border-radius:8px;">
                <i class="bi bi-cloud-check-fill" style="color:#2563eb; font-size:1.4rem;"></i>
            </div>
            <div>
                <h2 class="mb-0" style="font-size:1.5rem; font-weight:700; color:#1e293b;">Análise Técnica de Recursos Azure</h2>
                <p class="mb-0" style="font-size:.85rem; color:#64748b;">
                    Verifique a viabilidade de migração de recursos entre assinaturas Azure.
                    Base atualizada: <strong><?= htmlspecialchars($dbMetadata['lastUpdated'] ?? 'N/A') ?></strong>
                </p>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius:8px; font-size:.88rem;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius:8px; font-size:.88rem;">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">

            <!-- Upload Section -->
            <div class="col-lg-3 mb-4">
                <div class="mig-card">
                    <div class="card-header"><i class="bi bi-upload me-2"></i>Upload de Arquivo</div>
                    <div class="card-body p-3">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="action" value="analyze">
                            <div class="mb-3">
                                <label for="clientName" class="form-label" style="font-size:.82rem; font-weight:600; color:#374151;">Nome do Cliente (opcional)</label>
                                <input type="text" class="form-control form-control-sm" id="clientName" name="clientName" placeholder="Ex: Empresa XYZ">
                            </div>
                            <div class="upload-zone mb-3" id="dropZone" onclick="document.getElementById('fileInput').click()">
                                <i class="bi bi-file-earmark-spreadsheet"></i>
                                <p class="mt-2 mb-1" style="font-size:.85rem; color:#475569;">Arraste o arquivo aqui ou clique para selecionar</p>
                                <small class="text-muted" style="font-size:.75rem;">Formatos: .xlsx, .xls, .csv</small>
                                <input type="file" id="fileInput" name="file" accept=".xlsx,.xls,.csv" class="d-none" onchange="handleFileSelect(this)">
                            </div>
                            <div id="selectedFile" class="alert alert-info d-none p-2" style="font-size:.82rem;">
                                <i class="bi bi-file-earmark me-1"></i><span id="fileName"></span>
                            </div>
                            <button type="submit" class="btn btn-azure btn-sm w-100" id="analyzeBtn" disabled>
                                <i class="bi bi-search me-1"></i>Analisar Recursos
                            </button>
                        </form>
                        <hr style="margin:14px 0;">
                        <p style="font-size:.78rem; font-weight:600; color:#374151; margin-bottom:8px;">
                            <i class="bi bi-info-circle me-1" style="color:var(--td-blue);"></i>Colunas esperadas:
                        </p>
                        <table class="table table-sm table-bordered mb-0" style="font-size:.75rem;">
                            <thead class="table-light">
                                <tr><th>Coluna</th><th>Obrig.</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>Resource Type</code></td><td><span class="badge bg-danger" style="font-size:.65rem;">Sim</span></td></tr>
                                <tr><td><code>Resource Name</code></td><td><span class="badge bg-secondary" style="font-size:.65rem;">Não</span></td></tr>
                                <tr><td><code>Resource Group</code></td><td><span class="badge bg-secondary" style="font-size:.65rem;">Não</span></td></tr>
                                <tr><td><code>Location</code></td><td><span class="badge bg-secondary" style="font-size:.65rem;">Não</span></td></tr>
                            </tbody>
                        </table>
                        <div class="db-info mt-2">
                            <i class="bi bi-database me-1"></i>
                            <?= number_format($dbStats['total']) ?> tipos de recursos catalogados.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Section -->
            <div class="col-lg-9">
                <?php if ($analysisResults): ?>
                    <?php $summary = $analysisResults['summary']; ?>

                    <!-- Summary Stats -->
                    <div class="row mb-3">
                        <div class="col-6 col-md mb-3">
                            <div class="stat-card stat-movable filter-card" data-filter="movable" onclick="filterByStatus('movable')">
                                <div class="stat-number"><?= $summary['movable'] ?></div>
                                <div class="stat-label">Migráveis</div>
                                <span class="stat-percent text-success"><i class="bi bi-check-circle"></i> <?= $summary['movablePercent'] ?>%</span>
                            </div>
                        </div>
                        <div class="col-6 col-md mb-3">
                            <div class="stat-card stat-restrictions filter-card" data-filter="restrictions" onclick="filterByStatus('restrictions')">
                                <div class="stat-number"><?= $summary['movableWithRestrictions'] ?></div>
                                <div class="stat-label">Com Restrições</div>
                                <span class="stat-percent text-warning"><i class="bi bi-exclamation-circle"></i> <?= $summary['movableWithRestrictionsPercent'] ?>%</span>
                            </div>
                        </div>
                        <div class="col-6 col-md mb-3">
                            <div class="stat-card stat-not-movable filter-card" data-filter="not-movable" onclick="filterByStatus('not-movable')">
                                <div class="stat-number"><?= $summary['notMovable'] ?></div>
                                <div class="stat-label">Não Migráveis</div>
                                <span class="stat-percent text-danger"><i class="bi bi-x-circle"></i> <?= $summary['notMovablePercent'] ?>%</span>
                            </div>
                        </div>
                        <?php if (($summary['unknown'] ?? 0) > 0): ?>
                        <div class="col-6 col-md mb-3">
                            <div class="stat-card stat-unknown filter-card" data-filter="unknown" onclick="filterByStatus('unknown')">
                                <div class="stat-number"><?= $summary['unknown'] ?></div>
                                <div class="stat-label">Desconhecidos</div>
                                <span class="stat-percent text-secondary"><i class="bi bi-question-circle"></i> <?= $summary['unknownPercent'] ?>%</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="col-6 col-md mb-3">
                            <div class="stat-card stat-total filter-card" data-filter="all" onclick="filterByStatus('all')">
                                <div class="stat-number"><?= $summary['total'] ?></div>
                                <div class="stat-label">Total</div>
                                <span class="stat-percent text-primary"><i class="bi bi-layers"></i> Recursos</span>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Badge -->
                    <div id="filterBadge" class="filter-badge">
                        <i class="bi bi-funnel-fill"></i>
                        <span id="filterText"></span>
                        <button class="clear-filter" onclick="filterByStatus('all')"><i class="bi bi-x"></i> Limpar</button>
                    </div>

                    <!-- Export -->
                    <div class="mig-card mb-3">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h6 class="mb-0" style="font-weight:600; color:#1e293b;">
                                <i class="bi bi-file-earmark-arrow-down me-2" style="color:var(--td-blue);"></i>Exportar Relatório
                            </h6>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="generate_pdf">
                                    <button type="submit" class="btn btn-sm btn-outline-danger me-1">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                                    </button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="generate_excel">
                                    <button type="submit" class="btn btn-sm btn-outline-success me-1">
                                        <i class="bi bi-file-earmark-excel me-1"></i>Excel
                                    </button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="new_analysis">
                                    <button type="submit" class="btn btn-sm btn-outline-primary"
                                            onclick="return confirm('Iniciar uma nova análise? Os dados atuais serão descartados.')">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Nova Análise
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Recommendations -->
                    <?php if (!empty($analysisResults['recommendations'])): ?>
                    <div class="mig-card mb-3">
                        <div class="card-header"><i class="bi bi-lightbulb me-2"></i>Recomendações</div>
                        <div class="card-body p-3">
                            <?php foreach ($analysisResults['recommendations'] as $rec): ?>
                            <div class="recommendation recommendation-<?= htmlspecialchars($rec['type']) ?>">
                                <strong style="font-size:.87rem;"><?= htmlspecialchars($rec['title']) ?></strong>
                                <p class="mb-0 mt-1" style="font-size:.83rem;"><?= htmlspecialchars($rec['message']) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Results Table -->
                    <div class="mig-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-list-ul me-2"></i>Análise Detalhada</span>
                            <div class="d-flex align-items-center gap-2">
                                <button class="clear-notes-btn" id="clearAllNotesBtn" onclick="openClearAllNotesModal()" style="display:none;">
                                    <i class="bi bi-trash me-1"></i>Limpar notas
                                </button>
                                <input type="text" class="form-control form-control-sm" style="width:180px;"
                                       id="searchInput" placeholder="Filtrar recursos...">
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-container">
                                <table class="table table-hover table-results mb-0" id="resultsTable">
                                    <thead>
                                        <tr>
                                            <th onclick="sortTable(0,'string')">Nome <i class="bi bi-arrow-down-up ms-1" style="opacity:.4;font-size:.65rem;"></i></th>
                                            <th onclick="sortTable(1,'string')">Tipo do Recurso <i class="bi bi-arrow-down-up ms-1" style="opacity:.4;font-size:.65rem;"></i></th>
                                            <th onclick="sortTable(2,'string')">Resource Group <i class="bi bi-arrow-down-up ms-1" style="opacity:.4;font-size:.65rem;"></i></th>
                                            <th onclick="sortTable(3,'status')">Status <i class="bi bi-arrow-down-up ms-1" style="opacity:.4;font-size:.65rem;"></i></th>
                                            <th>Observações</th>
                                            <th style="width:100px;text-align:center;">
                                                Notas <span id="notesCountBadge" class="notes-count-badge" style="display:none;">0</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($analysisResults['results'] as $result):
                                            $dataStatus = match($result['status']) {
                                                'movable'                    => 'movable',
                                                'movable-with-restrictions'  => 'restrictions',
                                                'not-movable'                => 'not-movable',
                                                default                      => 'unknown'
                                            };
                                            $resourceId = base64_encode($result['resourceName'] . '|' . $result['resourceType'] . '|' . $result['resourceGroup']);
                                        ?>
                                        <tr data-status="<?= $dataStatus ?>"
                                            data-resource-id="<?= htmlspecialchars($resourceId) ?>"
                                            data-resource-name="<?= htmlspecialchars($result['resourceName']) ?>"
                                            data-resource-type="<?= htmlspecialchars($result['resourceType']) ?>"
                                            data-resource-group="<?= htmlspecialchars($result['resourceGroup']) ?>">
                                            <td><?= htmlspecialchars($result['resourceName']) ?></td>
                                            <td class="resource-type"><?= htmlspecialchars($result['resourceType']) ?></td>
                                            <td><?= htmlspecialchars($result['resourceGroup']) ?></td>
                                            <td>
                                                <?php $cls = match($result['status']) {
                                                    'movable'                   => 'status-movable',
                                                    'movable-with-restrictions' => 'status-restrictions',
                                                    'not-movable'               => 'status-not-movable',
                                                    default                     => 'status-unknown'
                                                }; ?>
                                                <span class="status-badge <?= $cls ?>"><?= htmlspecialchars($result['statusLabel']) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($result['notes']): ?>
                                                <small class="text-muted"><?= htmlspecialchars($result['notes']) ?></small>
                                                <?php else: ?><span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button class="note-btn" onclick="openNoteModal('<?= htmlspecialchars($resourceId) ?>')"
                                                        data-note-btn="<?= htmlspecialchars($resourceId) ?>">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <div class="note-preview" data-note-preview="<?= htmlspecialchars($resourceId) ?>"></div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="mig-card d-flex align-items-center justify-content-center" style="min-height:380px;">
                        <div class="text-center py-5">
                            <i class="bi bi-cloud-upload" style="font-size:4rem; color:#e2e8f0;"></i>
                            <h5 class="mt-3" style="font-weight:600; color:#1e293b;">Nenhuma análise realizada</h5>
                            <p class="text-muted" style="max-width:380px; margin:0 auto; font-size:.87rem;">
                                Faça o upload de um arquivo Excel ou CSV com a lista de recursos Azure para iniciar a análise de viabilidade de migração.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Note Modal -->
    <div class="modal fade" id="noteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-pencil-square me-2" style="color:var(--td-blue);"></i>Nota Personalizada</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="note-modal-resource-info">
                        <p class="mb-1 fw-semibold" style="font-size:.87rem;" id="noteModalResourceName"></p>
                        <small id="noteModalResourceType" class="d-block text-muted"></small>
                        <small id="noteModalResourceGroup" class="text-muted"></small>
                    </div>
                    <div class="mb-2">
                        <label for="noteTextarea" class="form-label fw-semibold" style="font-size:.83rem;">Sua nota:</label>
                        <textarea class="form-control" id="noteTextarea" rows="4"
                                  placeholder="Observações, lembretes ou informações adicionais..."></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <span style="font-size:.72rem; color:var(--td-gray);"><span id="noteCharCount">0</span>/500</span>
                            <button type="button" class="clear-notes-btn" onclick="clearCurrentNote()"><i class="bi bi-trash me-1"></i>Limpar</button>
                        </div>
                    </div>
                    <input type="hidden" id="currentResourceId" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-azure" onclick="saveNote()"><i class="bi bi-check2 me-1"></i>Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Clear All Notes Modal -->
    <div class="modal fade" id="clearAllNotesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Confirmar</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" style="font-size:.87rem;">Remover <strong>todas</strong> as notas personalizadas?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmClearAllNotes()"><i class="bi bi-trash me-1"></i>Limpar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ── Drag & Drop ──────────────────────────────────────────────────────────
    const dropZone   = document.getElementById('dropZone');
    const fileInput  = document.getElementById('fileInput');
    const analyzeBtn = document.getElementById('analyzeBtn');
    const selectedFile = document.getElementById('selectedFile');
    const fileName   = document.getElementById('fileName');

    ['dragenter','dragover','dragleave','drop'].forEach(e => dropZone.addEventListener(e, ev => { ev.preventDefault(); ev.stopPropagation(); }));
    ['dragenter','dragover'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.add('dragover')));
    ['dragleave','drop'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.remove('dragover')));
    dropZone.addEventListener('drop', e => { fileInput.files = e.dataTransfer.files; handleFileSelect(fileInput); });

    function handleFileSelect(input) {
        if (input.files.length > 0) {
            fileName.textContent = input.files[0].name;
            selectedFile.classList.remove('d-none');
            analyzeBtn.disabled = false;
        }
    }

    // ── Search filter ────────────────────────────────────────────────────────
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const f = this.value.toLowerCase();
            document.querySelectorAll('#resultsTable tbody tr').forEach(row => {
                if (row.dataset.statusHidden === 'true') return;
                row.style.display = row.textContent.toLowerCase().includes(f) ? '' : 'none';
            });
        });
    }

    // ── Status filter ────────────────────────────────────────────────────────
    let currentStatusFilter = 'all';

    function filterByStatus(status) {
        const rows = document.querySelectorAll('#resultsTable tbody tr');
        const badge = document.getElementById('filterBadge');
        const badgeText = document.getElementById('filterText');
        currentStatusFilter = status;

        document.querySelectorAll('.filter-card').forEach(c => {
            c.classList.toggle('active', c.dataset.filter === status);
        });

        let count = 0;
        rows.forEach(row => {
            const show = status === 'all' || row.dataset.status === status;
            row.style.display = show ? '' : 'none';
            row.dataset.statusHidden = show ? 'false' : 'true';
            if (show) count++;
        });

        if (status === 'all') {
            badge.classList.remove('show');
        } else {
            const labels = { movable:'Migráveis', restrictions:'Com Restrições', 'not-movable':'Não Migráveis', unknown:'Desconhecidos' };
            badgeText.innerHTML = `<strong>${labels[status]}</strong> (${count} recursos)`;
            badge.classList.add('show');
        }
        if (searchInput) searchInput.value = '';
    }

    // ── Table sort ───────────────────────────────────────────────────────────
    let sortCol = -1, sortDir = 'none';

    function sortTable(col, type) {
        const tbody = document.querySelector('#resultsTable tbody');
        const rows  = Array.from(tbody.querySelectorAll('tr'));
        if (sortCol === col) {
            sortDir = sortDir === 'asc' ? 'desc' : (sortDir === 'desc' ? 'none' : 'asc');
        } else { sortCol = col; sortDir = 'asc'; }

        if (sortDir === 'none') { location.reload(); return; }

        const statusOrd = { movable:1, restrictions:2, 'not-movable':3, unknown:4 };
        rows.sort((a, b) => {
            let av = a.cells[col].textContent.trim().toLowerCase();
            let bv = b.cells[col].textContent.trim().toLowerCase();
            if (type === 'status') {
                av = Object.entries(statusOrd).find(([k]) => av.includes(k))?.[1] ?? 4;
                bv = Object.entries(statusOrd).find(([k]) => bv.includes(k))?.[1] ?? 4;
            }
            const cmp = typeof av === 'number' ? av - bv : av.localeCompare(bv, 'pt-BR');
            return sortDir === 'asc' ? cmp : -cmp;
        });
        rows.forEach(r => tbody.appendChild(r));
    }

    // ── Custom Notes ─────────────────────────────────────────────────────────
    const NOTES_KEY = 'azure_migration_notes';
    let noteModalBs = null, clearAllModalBs = null;

    document.addEventListener('DOMContentLoaded', () => {
        noteModalBs     = new bootstrap.Modal(document.getElementById('noteModal'));
        clearAllModalBs = new bootstrap.Modal(document.getElementById('clearAllNotesModal'));
        loadAndDisplayNotes();
        const ta = document.getElementById('noteTextarea');
        if (ta) ta.addEventListener('input', function() {
            const n = Math.min(this.value.length, 500);
            document.getElementById('noteCharCount').textContent = n;
            if (this.value.length > 500) this.value = this.value.substring(0, 500);
        });
    });

    function getNotes() { try { return JSON.parse(localStorage.getItem(NOTES_KEY) || '{}'); } catch { return {}; } }
    function setNotes(n) { try { localStorage.setItem(NOTES_KEY, JSON.stringify(n)); } catch {} }

    function loadAndDisplayNotes() {
        const notes = getNotes();
        let count = 0;
        document.querySelectorAll('#resultsTable tbody tr').forEach(row => {
            const id = row.dataset.resourceId;
            const btn = row.querySelector(`[data-note-btn="${id}"]`);
            const prev = row.querySelector(`[data-note-preview="${id}"]`);
            if (notes[id]) {
                count++;
                row.classList.add('has-custom-note');
                if (btn)  btn.classList.add('has-note');
                if (prev) prev.textContent = notes[id];
            } else {
                row.classList.remove('has-custom-note');
                if (btn)  btn.classList.remove('has-note');
                if (prev) prev.textContent = '';
            }
        });
        const badge = document.getElementById('notesCountBadge');
        const clearBtn = document.getElementById('clearAllNotesBtn');
        if (count > 0) { badge.textContent = count; badge.style.display='inline'; clearBtn.style.display='inline-block'; }
        else           { badge.style.display='none'; clearBtn.style.display='none'; }
    }

    function openNoteModal(id) {
        const row = document.querySelector(`tr[data-resource-id="${id}"]`);
        if (!row) return;
        document.getElementById('noteModalResourceName').textContent  = row.dataset.resourceName;
        document.getElementById('noteModalResourceType').textContent  = row.dataset.resourceType;
        document.getElementById('noteModalResourceGroup').textContent = 'Resource Group: ' + row.dataset.resourceGroup;
        document.getElementById('currentResourceId').value = id;
        const note = getNotes()[id] || '';
        document.getElementById('noteTextarea').value = note;
        document.getElementById('noteCharCount').textContent = note.length;
        noteModalBs.show();
    }

    function saveNote() {
        const id   = document.getElementById('currentResourceId').value;
        const text = document.getElementById('noteTextarea').value.trim();
        const notes = getNotes();
        if (text) notes[id] = text; else delete notes[id];
        setNotes(notes);
        loadAndDisplayNotes();
        noteModalBs.hide();
        showToast(text ? 'Nota salva!' : 'Nota removida.');
    }

    function clearCurrentNote() {
        document.getElementById('noteTextarea').value = '';
        document.getElementById('noteCharCount').textContent = '0';
    }

    function openClearAllNotesModal() { clearAllModalBs.show(); }

    function confirmClearAllNotes() {
        localStorage.removeItem(NOTES_KEY);
        loadAndDisplayNotes();
        clearAllModalBs.hide();
        showToast('Todas as notas foram removidas.');
    }

    function showToast(msg) {
        const t = document.createElement('div');
        t.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#1e293b;color:white;padding:10px 20px;border-radius:8px;font-size:.85rem;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.2);';
        t.innerHTML = `<i class="bi bi-check-circle me-2"></i>${msg}`;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3000);
    }

    // Inject notes into export forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const action = this.querySelector('input[name="action"]')?.value;
            if (action === 'generate_pdf' || action === 'generate_excel') {
                let ni = this.querySelector('input[name="customNotes"]');
                if (!ni) { ni = document.createElement('input'); ni.type='hidden'; ni.name='customNotes'; this.appendChild(ni); }
                ni.value = JSON.stringify(getNotes());
            }
        });
    });
    </script>
</body>
</html>
