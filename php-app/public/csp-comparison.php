<?php
declare(strict_types=1);
session_start();

// Initialize i18n
require_once __DIR__ . '/../src/Shared/Services/i18n-bootstrap.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Features\CspPricing\Services\CspPriceValidator;

$uploadDir = __DIR__ . '/../uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$error      = null;
$success    = null;
$validation = null;
$comparison = null;
$report     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'validate';

    // Determinar origem do arquivo: upload direto ou chunked
    $tmpPath  = null;
    $origName = '';
    $fileOk   = false;

    $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['uploadId'] ?? ''));

    if ($uploadId !== '') {
        // Chunked upload
        if (!empty($_SESSION['chunkedUpload']) && $_SESSION['chunkedUpload']['uploadId'] === $uploadId) {
            $chunked  = $_SESSION['chunkedUpload'];
            $tmpPath  = $chunked['filePath'];
            $origName = $chunked['fileName'];
            unset($_SESSION['chunkedUpload']);
        } else {
            $candidatePath = $uploadDir . '/csp_' . $uploadId . '.csv';
            if (file_exists($candidatePath)) {
                $tmpPath  = $candidatePath;
                $origName = (string)($_POST['fileName'] ?? 'upload.csv');
            }
        }

        if ($tmpPath && file_exists($tmpPath)) {
            if (strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'csv') {
                $error = 'Apenas arquivos CSV são suportados.';
                @unlink($tmpPath);
            } else {
                $fileOk = true;
            }
        } else {
            $error = 'Arquivo do upload não encontrado. Tente novamente.';
        }
    } elseif (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['file'];
        $ext  = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Erro no upload: código ' . (int)$file['error'];
        } elseif ($ext !== 'csv') {
            $error = 'Apenas arquivos CSV são suportados.';
        } elseif ($file['size'] > 200 * 1024 * 1024) {
            $error = 'Arquivo muito grande (máximo 200 MB).';
        } else {
            $tmpPath  = $uploadDir . '/csp_' . uniqid() . '.csv';
            $origName = (string)$file['name'];
            if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
                $error = 'Falha ao salvar arquivo temporário.';
            } else {
                $fileOk = true;
            }
        }
    } else {
        $error = 'Nenhum arquivo recebido. Selecione um CSV.';
    }

    if ($fileOk && $tmpPath) {
        set_time_limit(300);

        $validator = new CspPriceValidator();

        if ($action === 'validate') {
            // Apenas valida estrutura
            $validation = $validator->validateCsvStructure($tmpPath);
            
            if ($validation['valid']) {
                // Armazena caminho para comparação posterior
                $_SESSION['cspCsvPath']     = $tmpPath;
                $_SESSION['cspCsvFileName'] = $origName;
                $success = 'Arquivo validado com sucesso! ' . $validation['rowCount'] . ' linhas encontradas.';
            } else {
                @unlink($tmpPath);
            }

        } elseif ($action === 'compare') {
            // Valida e compara
            $validation = $validator->validateCsvStructure($tmpPath);

            if (!$validation['valid']) {
                @unlink($tmpPath);
            } else {
                $parseResult = $validator->parseCsv($tmpPath);

                if (!$parseResult['success']) {
                    $error = $parseResult['error'];
                    @unlink($tmpPath);
                } else {
                    $comparison = $validator->compareWithAzureApi($parseResult['data']);
                    $report     = $validator->generateReport($comparison);
                    $success    = 'Comparação concluída!';

                    // Armazena para exportação
                    $_SESSION['cspReport'] = $report;
                }
            }
        }
    }
}

// Recupera dados da sessão se existirem
$storedPath     = $_SESSION['cspCsvPath'] ?? null;
$storedFileName = $_SESSION['cspCsvFileName'] ?? null;
$storedReport   = $_SESSION['cspReport'] ?? null;
?>
<!DOCTYPE html>
<html lang="<?= getHtmlLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('pages.csp_comparison') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
</head>
<body class="bg-slate-100 min-h-screen font-sans text-slate-900">
    <?php include __DIR__ . '/../templates/topbar.php'; ?>
    <div id="app" class="container mx-auto p-4 md:p-8 max-w-[1600px]">

        <!-- Info Banner -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
            <div class="flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-blue-600 flex-shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
                <div>
                    <h3 class="font-semibold text-blue-900">Sobre esta Ferramenta</h3>
                    <p class="text-sm text-blue-800 mt-1">
                        Valida dados de modelos CSP (MOSA, MPA, MCA) comparando com a API pública de preços do Azure.
                        Baseado no <a href="https://learn.microsoft.com/en-us/azure/cost-management-billing/dataset-schema/schema-index" target="_blank" class="underline hover:text-blue-600">Cost Management Dataset Schema</a> (Export: 2019-11-01).
                    </p>
                </div>
            </div>
        </div>

        <!-- Error/Success Messages -->
        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                <span class="text-red-800 font-medium"><?= htmlspecialchars($error) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-green-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span class="text-green-800 font-medium"><?= htmlspecialchars($success) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Upload Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <h2 class="text-lg font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        Upload do CSV
                    </h2>

                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="action" value="compare" id="formAction">

                        <!-- Drag & Drop Area -->
                        <div id="dropZone" class="border-2 border-dashed border-slate-300 rounded-lg p-8 text-center hover:border-blue-400 hover:bg-blue-50/50 transition-all cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto text-slate-400 mb-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <p class="text-slate-600 font-medium">Arraste o arquivo CSV aqui</p>
                            <p class="text-sm text-slate-400 mt-1">ou clique para selecionar</p>
                            <input type="file" name="file" id="fileInput" accept=".csv" class="hidden">
                        </div>

                        <div id="fileInfo" class="hidden mt-4 p-3 bg-slate-50 rounded-lg">
                            <div class="flex items-center justify-between">
                                <span id="fileName" class="text-sm text-slate-700 font-medium truncate"></span>
                                <button type="button" id="removeFile" class="text-red-500 hover:text-red-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <span id="fileSize" class="text-xs text-slate-500"></span>
                        </div>

                        <!-- Required Columns Info -->
                        <div class="mt-4 p-3 bg-amber-50 rounded-lg border border-amber-200">
                            <h4 class="text-sm font-semibold text-amber-800 mb-2">Colunas Usadas na Comparação:</h4>
                            <div class="grid grid-cols-2 gap-1 text-xs text-amber-700">
                                <span>• MeterID</span>
                                <span>• MeterName</span>
                                <span>• ServiceName</span>
                                <span>• UnitOfMeasure</span>
                                <span>• UsageQuantity</span>
                                <span>• Quantity</span>
                                <span>• ResourceLocation</span>
                                <span>• ServiceFamily</span>
                                <span>• ProductName</span>
                                <span class="text-amber-500 text-[10px]">* "/" normalizado</span>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="mt-6 flex flex-col gap-3">
                            <button type="submit" id="btnValidate" onclick="document.getElementById('formAction').value='validate'" 
                                    class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-lg transition-colors flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                Apenas Validar
                            </button>
                            <button type="submit" id="btnCompare" onclick="document.getElementById('formAction').value='compare'" 
                                    class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                </svg>
                                Validar e Comparar com API
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Validation Result -->
                <?php if ($validation): ?>
                <div class="mt-6 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-lg font-semibold text-slate-900 mb-4">Resultado da Validação</h3>
                    
                    <?php if ($validation['valid']): ?>
                    <div class="p-3 bg-green-50 rounded-lg border border-green-200 mb-4">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-green-600">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <span class="text-green-800 font-medium">Estrutura Válida</span>
                        </div>
                        <p class="text-sm text-green-700 mt-1"><?= $validation['rowCount'] ?> linhas encontradas</p>
                    </div>
                    <?php else: ?>
                    <div class="p-3 bg-red-50 rounded-lg border border-red-200 mb-4">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-600">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <span class="text-red-800 font-medium">Estrutura Inválida</span>
                        </div>
                        <?php foreach ($validation['errors'] as $err): ?>
                        <p class="text-sm text-red-700 mt-1">• <?= htmlspecialchars($err) ?></p>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($validation['warnings'])): ?>
                    <div class="p-3 bg-amber-50 rounded-lg border border-amber-200">
                        <h4 class="text-sm font-semibold text-amber-800 mb-2">Avisos:</h4>
                        <?php foreach (array_slice($validation['warnings'], 0, 5) as $warn): ?>
                        <p class="text-xs text-amber-700">• <?= htmlspecialchars($warn) ?></p>
                        <?php endforeach; ?>
                        <?php if (count($validation['warnings']) > 5): ?>
                        <p class="text-xs text-amber-600 mt-1">...e mais <?= count($validation['warnings']) - 5 ?> avisos</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($validation['columns'])): ?>
                    <div class="mt-4">
                        <h4 class="text-sm font-semibold text-slate-700 mb-2">Colunas Detectadas:</h4>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($validation['columns'] as $col): ?>
                            <span class="text-xs px-2 py-1 bg-slate-100 rounded-full text-slate-600"><?= htmlspecialchars($col) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Results Panel -->
            <div class="lg:col-span-2">
                <?php if ($comparison): ?>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold text-slate-900">Resultado da Comparação</h2>
                        <button onclick="exportReport()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Exportar Relatório
                        </button>
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                        <div class="p-4 bg-slate-50 rounded-lg text-center">
                            <p class="text-2xl font-bold text-slate-900"><?= $comparison['summary']['total'] ?></p>
                            <p class="text-xs text-slate-500 mt-1">Total de Linhas</p>
                        </div>
                        <div class="p-4 bg-green-50 rounded-lg text-center">
                            <p class="text-2xl font-bold text-green-600"><?= $comparison['summary']['matches'] ?></p>
                            <p class="text-xs text-green-700 mt-1">Matches</p>
                        </div>
                        <div class="p-4 bg-amber-50 rounded-lg text-center">
                            <p class="text-2xl font-bold text-amber-600"><?= $comparison['summary']['mismatches'] ?></p>
                            <p class="text-xs text-amber-700 mt-1">Divergências</p>
                        </div>
                        <div class="p-4 bg-red-50 rounded-lg text-center">
                            <p class="text-2xl font-bold text-red-600"><?= $comparison['summary']['notFound'] ?></p>
                            <p class="text-xs text-red-700 mt-1">Não Encontrados</p>
                        </div>
                        <div class="p-4 bg-blue-50 rounded-lg text-center">
                            <p class="text-2xl font-bold text-blue-600"><?= $comparison['summary']['assertiveness'] ?>%</p>
                            <p class="text-xs text-blue-700 mt-1">Assertividade</p>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between text-sm mb-2">
                            <span class="text-slate-600">Taxa de Assertividade</span>
                            <span class="font-semibold text-slate-900"><?= $comparison['summary']['assertiveness'] ?>%</span>
                        </div>
                        <div class="h-3 bg-slate-200 rounded-full overflow-hidden">
                            <?php 
                            $matchPct = ($comparison['summary']['total'] > 0) ? ($comparison['summary']['matches'] / $comparison['summary']['total']) * 100 : 0;
                            $mismatchPct = ($comparison['summary']['total'] > 0) ? ($comparison['summary']['mismatches'] / $comparison['summary']['total']) * 100 : 0;
                            $notFoundPct = ($comparison['summary']['total'] > 0) ? ($comparison['summary']['notFound'] / $comparison['summary']['total']) * 100 : 0;
                            ?>
                            <div class="h-full flex">
                                <div class="bg-green-500 h-full" style="width: <?= $matchPct ?>%"></div>
                                <div class="bg-amber-500 h-full" style="width: <?= $mismatchPct ?>%"></div>
                                <div class="bg-red-500 h-full" style="width: <?= $notFoundPct ?>%"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 mt-2 text-xs">
                            <span class="flex items-center gap-1"><span class="w-3 h-3 bg-green-500 rounded"></span> Match</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-3 bg-amber-500 rounded"></span> Divergência</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-3 bg-red-500 rounded"></span> Não Encontrado</span>
                        </div>
                    </div>

                    <!-- Results Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">#</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">MeterID</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">MeterName</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">ProductName</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-600 uppercase">Score</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-600 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach (array_slice($comparison['results'], 0, 100) as $item): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-3 py-2 text-slate-500"><?= $item['rowIndex'] + 1 ?></td>
                                    <td class="px-3 py-2 font-mono text-xs text-slate-700"><?= htmlspecialchars(substr($item['csvData']['meterId'], 0, 20)) ?>...</td>
                                    <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars(substr($item['csvData']['meterName'], 0, 30)) ?></td>
                                    <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars(substr($item['csvData']['productName'], 0, 30)) ?></td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="text-xs font-medium text-slate-600"><?= $item['matchScore'] ?>/<?= $item['maxScore'] ?></span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <?php if ($item['status'] === 'MATCH'): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Match</span>
                                        <?php elseif ($item['status'] === 'PARTIAL_MATCH'): ?>
                                        <span class="px-2 py-1 bg-amber-100 text-amber-700 text-xs font-medium rounded-full">Parcial</span>
                                        <?php elseif ($item['status'] === 'NOT_FOUND'): ?>
                                        <span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-medium rounded-full">Não Encontrado</span>
                                        <?php else: ?>
                                        <span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-medium rounded-full">Divergente</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (count($comparison['results']) > 100): ?>
                        <p class="text-center text-sm text-slate-500 mt-4">Mostrando 100 de <?= count($comparison['results']) ?> resultados. Exporte o relatório para ver todos.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Discrepancies Detail -->
                <?php if (!empty($report['discrepancies'])): ?>
                <div class="mt-6 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-lg font-semibold text-slate-900 mb-4">Detalhes das Divergências</h3>
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <?php foreach (array_slice($report['discrepancies'], 0, 20) as $disc): ?>
                        <div class="p-4 bg-amber-50 rounded-lg border border-amber-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-medium text-amber-800">Linha <?= $disc['row'] ?></span>
                                <span class="text-xs bg-amber-200 px-2 py-1 rounded-full text-amber-800">Score: <?= $disc['matchScore'] ?></span>
                            </div>
                            <p class="text-sm text-amber-700 mb-2"><?= htmlspecialchars($disc['meterName']) ?></p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <?php foreach ($disc['discrepancies'] as $d): ?>
                                <div class="text-xs">
                                    <span class="font-semibold text-amber-900"><?= $d['field'] ?>:</span>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded">CSV: <?= htmlspecialchars(substr($d['csv'], 0, 40)) ?></span>
                                        <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded">API: <?= htmlspecialchars(substr($d['api'], 0, 40)) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <!-- Empty State -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-16 h-16 mx-auto text-slate-300 mb-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" />
                    </svg>
                    <h3 class="text-lg font-semibold text-slate-700 mb-2">Nenhuma Comparação Realizada</h3>
                    <p class="text-slate-500">Faça upload de um arquivo CSV para iniciar a validação e comparação com a API do Azure.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Store report data for export -->
    <script id="reportData" type="application/json"><?= json_encode($report ?? []) ?></script>

    <script>
    // Drag & Drop
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const removeFile = document.getElementById('removeFile');

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-blue-500', 'bg-blue-50');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-blue-500', 'bg-blue-50');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-blue-500', 'bg-blue-50');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            showFileInfo(e.dataTransfer.files[0]);
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            showFileInfo(fileInput.files[0]);
        }
    });

    removeFile.addEventListener('click', () => {
        fileInput.value = '';
        fileInfo.classList.add('hidden');
    });

    function showFileInfo(file) {
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        fileInfo.classList.remove('hidden');
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // Export Report
    function exportReport() {
        const reportData = JSON.parse(document.getElementById('reportData').textContent);
        if (!reportData || !reportData.summary) {
            alert('Nenhum relatório disponível para exportar.');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Header
        doc.setFontSize(18);
        doc.setTextColor(0, 87, 88);
        doc.text('CSP Price Validation Report', 14, 20);

        doc.setFontSize(10);
        doc.setTextColor(100);
        doc.text('TD SYNNEX - ' + reportData.generatedAt, 14, 28);

        // Summary
        doc.setFontSize(12);
        doc.setTextColor(0, 0, 0);
        doc.text('Resumo da Validação', 14, 40);

        doc.autoTable({
            startY: 45,
            head: [['Métrica', 'Valor']],
            body: [
                ['Total de Linhas', reportData.summary.total],
                ['Matches', reportData.summary.matches],
                ['Divergências', reportData.summary.mismatches],
                ['Não Encontrados', reportData.summary.notFound],
                ['Assertividade', reportData.summary.assertiveness + '%'],
            ],
            theme: 'striped',
            headStyles: { fillColor: [0, 87, 88] },
        });

        // Discrepancies
        if (reportData.discrepancies && reportData.discrepancies.length > 0) {
            doc.addPage();
            doc.setFontSize(12);
            doc.text('Divergências Encontradas', 14, 20);

            const discRows = reportData.discrepancies.slice(0, 50).map(d => [
                d.row,
                d.meterId.substring(0, 20) + '...',
                d.meterName.substring(0, 30),
                d.matchScore,
                d.discrepancies.map(x => x.field).join(', '),
            ]);

            doc.autoTable({
                startY: 25,
                head: [['Linha', 'MeterID', 'MeterName', 'Score', 'Campos Divergentes']],
                body: discRows,
                theme: 'striped',
                headStyles: { fillColor: [0, 87, 88] },
                styles: { fontSize: 8 },
            });
        }

        doc.save('csp-validation-report.pdf');
    }
    </script>
</body>
</html>
