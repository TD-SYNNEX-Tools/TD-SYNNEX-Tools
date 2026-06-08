<?php
declare(strict_types=1);
session_start();

// Initialize i18n
require_once __DIR__ . '/../src/Shared/Services/i18n-bootstrap.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Features\M365Migration\Services\M365Processor;
use App\Features\CspPricing\Services\PriceListProcessor;

$uploadDir = __DIR__ . '/../uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$error          = null;
$success        = null;
$results        = null;
$priceSuccess   = null;
$priceError     = null;
$priceList      = null;

$processor      = new M365Processor();
$plProcessor    = new PriceListProcessor();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'analyze';

    // ── Processar arquivo ─────────────────────────────────────────────────────
    if ($action === 'analyze' && isset($_FILES['file'])) {
        $file       = $_FILES['file'];
        $validation = $processor->validateFile($file);

        if (!$validation['valid']) {
            $error = $validation['error'];
        } else {
            $ext     = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            $tmpPath = $uploadDir . '/m365_upload_' . uniqid() . '.' . $ext;

            if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
                $error = 'Erro ao mover o arquivo enviado.';
            } else {
                $result = $processor->processFile($tmpPath, $uploadDir);
                @unlink($tmpPath);

                if (!$result['success']) {
                    $error = $result['error'];
                } else {
                    // Limpa saída anterior se existir
                    if (!empty($_SESSION['m365Results']['outputFile'])) {
                        $old = $uploadDir . '/' . $_SESSION['m365Results']['outputFile'];
                        if (file_exists($old)) {
                            @unlink($old);
                        }
                    }

                    $_SESSION['m365Results'] = [
                        'totalRows'  => $result['totalRows'],
                        'headers'    => $result['headers'],
                        'preview'    => $result['preview'],
                        'outputFile' => $result['outputFile'],
                    ];
                    $_SESSION['m365ClientName'] = filter_input(INPUT_POST, 'clientName', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

                    $success = "Processamento concluído! {$result['totalRows']} linhas processadas.";
                    $results = $_SESSION['m365Results'];
                }
            }
        }

    // ── Download CSV processado ───────────────────────────────────────────────
    } elseif ($action === 'download') {
        if (!empty($_SESSION['m365Results']['outputFile'])) {
            $outPath = $uploadDir . '/' . $_SESSION['m365Results']['outputFile'];
            if (file_exists($outPath)) {
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="m365_processado_' . date('Ymd_His') . '.csv"');
                header('Content-Length: ' . filesize($outPath));
                readfile($outPath);
                exit;
            }
        }
        $error   = 'Arquivo processado não encontrado. Realize o upload novamente.';
        $results = $_SESSION['m365Results'] ?? null;

    // ── Download Excel processado ─────────────────────────────────────────────
    } elseif ($action === 'download_excel') {
        if (!empty($_SESSION['m365Results']['outputFile'])) {
            $outPath = $uploadDir . '/' . $_SESSION['m365Results']['outputFile'];
            if (file_exists($outPath)) {
                try {
                    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setTitle('M365 Processado');

                    // Ler o CSV e popular a planilha
                    $csvHandle = fopen($outPath, 'r');
                    $rowNum = 1;
                    while (($row = fgetcsv($csvHandle)) !== false) {
                        $colNum = 1;
                        foreach ($row as $cellValue) {
                            $sheet->setCellValue([$colNum, $rowNum], $cellValue);
                            $colNum++;
                        }
                        $rowNum++;
                    }
                    fclose($csvHandle);

                    // Estilizar cabeçalho
                    $lastCol = $sheet->getHighestColumn();
                    $headerRange = 'A1:' . $lastCol . '1';
                    $sheet->getStyle($headerRange)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
                        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
                    ]);

                    // Auto-ajustar largura das colunas
                    foreach (range('A', $lastCol) as $col) {
                        $sheet->getColumnDimension($col)->setAutoSize(true);
                    }

                    // Aplicar filtro automático
                    $sheet->setAutoFilter('A1:' . $lastCol . ($rowNum - 1));

                    // Enviar arquivo Excel
                    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    header('Content-Disposition: attachment; filename="m365_processado_' . date('Ymd_His') . '.xlsx"');
                    header('Cache-Control: max-age=0');

                    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                    $writer->save('php://output');
                    exit;
                } catch (Exception $e) {
                    $error = 'Erro ao gerar arquivo Excel: ' . $e->getMessage();
                    $results = $_SESSION['m365Results'] ?? null;
                }
            }
        }
        $error   = 'Arquivo processado não encontrado. Realize o upload novamente.';
        $results = $_SESSION['m365Results'] ?? null;

    // ── Carregar lista de preços ─────────────────────────────────────────────
    } elseif ($action === 'load_pricelist' && isset($_FILES['priceFile'])) {
        $results = $_SESSION['m365Results'] ?? null;
        $pf      = $_FILES['priceFile'];

        // Validação básica
        if ($pf['error'] !== UPLOAD_ERR_OK) {
            $priceError = 'Erro no upload (código ' . (int)$pf['error'] . ').';
        } elseif (!in_array(strtolower(pathinfo((string)$pf['name'], PATHINFO_EXTENSION)), ['xlsx', 'xls'])) {
            $priceError = 'Apenas arquivos XLSX/XLS são suportados para a lista de preços.';
        } elseif ($pf['size'] > 50 * 1024 * 1024) {
            $priceError = 'Arquivo muito grande (máximo 50 MB).';
        } else {
            $ext     = strtolower(pathinfo((string)$pf['name'], PATHINFO_EXTENSION));
            $tmpPath = $uploadDir . '/pl_upload_' . uniqid() . '.' . $ext;

            if (!move_uploaded_file($pf['tmp_name'], $tmpPath)) {
                $priceError = 'Erro ao mover o arquivo da lista de preços.';
            } else {
                $plResult = $plProcessor->processFile($tmpPath);
                @unlink($tmpPath);

                if (!$plResult['success']) {
                    $priceError = $plResult['error'];
                } else {
                    $_SESSION['m365PriceList'] = [
                        'totalRows' => $plResult['totalRows'],
                        'preview'   => $plResult['preview'],
                        'lookup'    => $plResult['lookup'],
                    ];
                    $priceSuccess = "Lista de preços carregada! {$plResult['totalRows']} itens indexados.";
                    $priceList    = $_SESSION['m365PriceList'];
                }
            }
        }

    // ── Remover lista de preços ────────────────────────────────────────────
    } elseif ($action === 'clear_pricelist') {
        unset($_SESSION['m365PriceList']);
        $results = $_SESSION['m365Results'] ?? null;
        header('Location: migracao-m365.php');
        exit;

    // ── Nova análise ────────────────────────────────────────────
    } elseif ($action === 'new_analysis') {
        if (!empty($_SESSION['m365Results']['outputFile'])) {
            @unlink($uploadDir . '/' . $_SESSION['m365Results']['outputFile']);
        }
        unset($_SESSION['m365Results'], $_SESSION['m365ClientName'], $_SESSION['m365PriceList']);
        header('Location: migracao-m365.php');
        exit;
    }

// ── Recupera resultados da sessão (GET) ─────────────────────────────────────────────
} elseif (isset($_SESSION['m365Results'])) {
    $results   = $_SESSION['m365Results'];
    $priceList = $_SESSION['m365PriceList'] ?? null;
}

// Colunas alteradas/criadas (para destaque na tabela)
const HIGHLIGHT_COLS = ['SkuId', 'TermAndBillingCycle', 'ChaveM365'];
?>
<!DOCTYPE html>
<html lang="<?= getHtmlLang() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= __('pages.m365_migration') ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* Destaque das colunas processadas */
    .col-highlight-header { background-color: #ede9fe !important; color: #5b21b6 !important; font-weight: 700; }
    .col-highlight-cell   { background-color: #f5f3ff !important; color: #4c1d95; }
    .col-new-header       { background-color: #d1fae5 !important; color: #065f46 !important; font-weight: 700; }
    .col-new-cell         { background-color: #ecfdf5 !important; color: #064e3b; }

    /* Tabela de resultados */
    #resultsTable          { border-collapse: collapse; min-width: 100%; white-space: nowrap; font-size: 0.78rem; }
    #resultsTable th       { position: sticky; top: 0; z-index: 2; background: #f8fafc; border-bottom: 2px solid #e2e8f0;
                             padding: 0.55rem 0.75rem; text-align: left; font-size: 0.72rem; text-transform: uppercase;
                             letter-spacing: 0.04em; color: #475569; }
    #resultsTable td       { padding: 0.45rem 0.75rem; border-bottom: 1px solid #f1f5f9; color: #334155; max-width: 220px;
                             overflow: hidden; text-overflow: ellipsis; }
    #resultsTable tr:hover td { background-color: #f8fafc; }

    /* Upload drag-and-drop */
    #dropZone.dragover     { border-color: #7c3aed; background-color: #f5f3ff; }

    /* Tooltip para células truncadas */
    #resultsTable td[title] { cursor: default; }
  </style>
</head>
<body class="bg-slate-100 min-h-screen font-sans text-slate-900">
<?php include __DIR__ . '/../templates/topbar.php'; ?>
<div id="app" class="container mx-auto p-4 md:p-8 max-w-[1600px]">

  <!-- ── Título ─────────────────────────────────────────────────────────── -->
  <div class="mb-8">
    <div class="flex items-center gap-3 mb-2">
      <div class="p-2 rounded-lg" style="background-color:#ede9fe;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6" style="color:#7c3aed;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
        </svg>
      </div>
      <div>
        <h2 class="text-3xl font-bold text-slate-800">Migração M365 <span class="text-lg font-semibold text-purple-600">(T1)</span></h2>
        <p class="text-slate-600 mt-1">Faça upload do arquivo de fatura do Partner Center e normalize os dados para análise de migração.</p>
      </div>
    </div>

    <!-- Fase badge -->
    <div class="mt-3 flex items-center gap-2">
      <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold" style="background:#ede9fe; color:#5b21b6;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        Fase 1 — Normalização e Mapeamento
      </span>
      <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-slate-200 text-slate-500">
        Fase 2 — Comparação (em breve)
      </span>
    </div>
  </div>

  <?php if ($error): ?>
  <!-- ── Erro ──────────────────────────────────────────────────────────── -->
  <div class="mb-6 flex items-start gap-3 bg-red-50 border border-red-200 rounded-xl p-4">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-600 shrink-0 mt-0.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
    </svg>
    <div>
      <p class="font-semibold text-red-800">Erro no processamento</p>
      <p class="text-sm text-red-700 mt-0.5"><?= htmlspecialchars($error) ?></p>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($success): ?>
  <!-- ── Sucesso ───────────────────────────────────────────────────────── -->
  <div class="mb-6 flex items-start gap-3 bg-green-50 border border-green-200 rounded-xl p-4">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-green-600 shrink-0 mt-0.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
    <p class="font-semibold text-green-800"><?= htmlspecialchars($success) ?></p>
  </div>
  <?php endif; ?>

  <?php if ($results === null): ?>
  <!-- ══════════════════════════════════════════════════════════════════════
       ESTADO INICIAL — Formulário de upload
  ═══════════════════════════════════════════════════════════════════════ -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Card Upload -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-100">
        <h3 class="text-lg font-semibold text-slate-800">Upload do Arquivo de Fatura</h3>
        <p class="text-sm text-slate-500 mt-0.5">Arquivo de reconciliação do Partner Center (CSV ou Excel)</p>
      </div>
      <div class="p-6">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
          <input type="hidden" name="action" value="analyze">

          <!-- Nome do cliente (opcional) -->
          <div class="mb-5">
            <label for="clientName" class="block text-sm font-medium text-slate-700 mb-1.5">
              Nome do Cliente <span class="text-slate-400 font-normal">(opcional)</span>
            </label>
            <input type="text" id="clientName" name="clientName"
                   class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                   placeholder="Ex.: Contoso Ltda">
          </div>

          <!-- Área de drag-and-drop -->
          <div id="dropZone"
               class="border-2 border-dashed border-slate-300 rounded-xl p-8 text-center cursor-pointer transition-all duration-200 hover:border-purple-400 hover:bg-purple-50 mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                 class="w-12 h-12 mx-auto mb-3 text-slate-400">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
            </svg>
            <p class="text-slate-600 font-medium mb-1">Arraste e solte o arquivo aqui</p>
            <p class="text-sm text-slate-400 mb-4">ou clique para selecionar</p>
            <span class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:#7c3aed;">
              Selecionar Arquivo
            </span>
            <input type="file" id="fileInput" name="file" accept=".csv,.xlsx,.xls" class="hidden">
            <p id="fileLabel" class="mt-4 text-sm text-slate-500 hidden"></p>
          </div>

          <p class="text-xs text-slate-400 mb-5">
            Formatos aceitos: <strong>CSV</strong>, <strong>XLSX</strong>, <strong>XLS</strong> &mdash; Tamanho máximo: 50 MB
          </p>

          <button type="submit" id="submitBtn"
                  class="w-full flex items-center justify-center gap-2 py-3 rounded-lg text-sm font-semibold text-white transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                  style="background:#7c3aed;" disabled>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
            </svg>
            Processar Arquivo
          </button>
        </form>
      </div>
    </div>

    <!-- Card Informações -->
    <div class="flex flex-col gap-4">

      <!-- O que é processado -->
      <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center gap-2 mb-3">
          <div class="p-1.5 rounded-lg" style="background:#ede9fe;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" style="color:#7c3aed;">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 1-6.23-.693L4.2 14L2.25 15.3" />
            </svg>
          </div>
          <h4 class="text-sm font-semibold text-slate-700">Transformações Aplicadas</h4>
        </div>
        <ul class="space-y-2.5 text-xs text-slate-600">
          <li class="flex items-start gap-2">
            <span class="mt-0.5 w-1.5 h-1.5 rounded-full bg-purple-500 shrink-0"></span>
            <span><strong class="text-purple-700">SkuId</strong> normalizado para 4 caracteres com zeros à esquerda</span>
          </li>
          <li class="flex items-start gap-2">
            <span class="mt-0.5 w-1.5 h-1.5 rounded-full bg-purple-500 shrink-0"></span>
            <span><strong class="text-purple-700">TermAndBillingCycle</strong> convertido para código curto (P1Y, P1M, P1Y-M, P1YM)</span>
          </li>
          <li class="flex items-start gap-2">
            <span class="mt-0.5 w-1.5 h-1.5 rounded-full bg-green-500 shrink-0"></span>
            <span>Nova coluna <strong class="text-green-700">ChaveM365</strong> criada (ProductId + SkuId + SkuName + Term)</span>
          </li>
        </ul>
      </div>

      <!-- Mapeamento de termos -->
      <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h4 class="text-sm font-semibold text-slate-700 mb-3">Mapeamento de Termos</h4>
        <div class="space-y-2">
          <?php
          $termMap = [
              'One-Year commitment for monthly/yearly billing' => 'P1Y-M',
              'One-Month commitment for monthly billing'       => 'P1M',
              'One-Year commitment for monthly billing'        => 'P1YM',
              'Qualquer outro valor'                           => 'P1Y',
          ];
          foreach ($termMap as $from => $to): ?>
          <div class="flex items-center justify-between gap-2 text-xs">
            <span class="text-slate-500 truncate flex-1" title="<?= htmlspecialchars($from) ?>"><?= htmlspecialchars($from) ?></span>
            <span class="shrink-0 px-2 py-0.5 rounded font-bold font-mono" style="background:#ede9fe; color:#5b21b6;"><?= htmlspecialchars($to) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </div>

  <?php else: ?>
  <!-- ══════════════════════════════════════════════════════════════════════
       ESTADO RESULTADOS — Tabela de preview
  ═══════════════════════════════════════════════════════════════════════ -->

  <!-- Barra de ações -->
  <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-4">
      <!-- Stats -->
      <div class="flex items-center gap-2 bg-white rounded-lg px-4 py-2.5 border border-slate-200 shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-purple-600">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
        </svg>
        <span class="text-sm font-semibold text-slate-800"><?= number_format($results['totalRows'], 0, ',', '.') ?></span>
        <span class="text-sm text-slate-500">linhas processadas</span>
      </div>
      <?php if (count($results['preview']) < $results['totalRows']): ?>
      <span class="text-xs text-slate-400 bg-amber-50 border border-amber-200 px-2.5 py-1.5 rounded-lg">
        Pré-visualização: <?= number_format(count($results['preview']), 0, ',', '.') ?> primeiras linhas
      </span>
      <?php endif; ?>
    </div>

    <div class="flex items-center gap-3">
      <!-- Download CSV -->
      <form method="POST" class="inline">
        <input type="hidden" name="action" value="download">
        <button type="submit"
                class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-white transition-all"
                style="background:#059669;">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          Baixar CSV
        </button>
      </form>

      <!-- Download Excel -->
      <form method="POST" class="inline">
        <input type="hidden" name="action" value="download_excel">
        <button type="submit"
                class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-white transition-all"
                style="background:#217346;">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          Baixar Excel
        </button>
      </form>

      <!-- Nova análise -->
      <form method="POST" class="inline">
        <input type="hidden" name="action" value="new_analysis">
        <button type="submit"
                class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition-all">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
          Novo Upload
        </button>
      </form>
    </div>
  </div>

  <?php
  // Coleta valores únicos de ProductCategory para o filtro
  $categoryOptions = [];
  $catColExists    = in_array('ProductCategory', $results['headers'] ?? [], true);
  if ($catColExists) {
      foreach ($results['preview'] as $pRow) {
          $cat = trim((string)($pRow['ProductCategory'] ?? ''));
          if ($cat !== '' && !in_array($cat, $categoryOptions, true)) {
              $categoryOptions[] = $cat;
          }
      }
      sort($categoryOptions);
  }
  ?>

  <!-- Barra de filtro -->
  <?php if ($catColExists): ?>
  <div class="flex flex-wrap items-center gap-3 mb-4 bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-3">
    <div class="flex items-center gap-2 shrink-0">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-purple-600">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591L15.75 12.75V19.5a.75.75 0 0 1-.393.659l-3 1.5a.75.75 0 0 1-1.107-.66v-6.449L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
      </svg>
      <label for="filterCategory" class="text-sm font-semibold text-slate-700 whitespace-nowrap">ProductCategory</label>
    </div>
    <select id="filterCategory"
            class="flex-1 min-w-[220px] max-w-xs px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition bg-white">
      <option value="">Todas as categorias</option>
      <?php foreach ($categoryOptions as $cat): ?>
      <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
      <?php endforeach; ?>
    </select>
    <button id="clearCategoryFilter"
            class="hidden items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold text-purple-700 bg-purple-50 border border-purple-200 hover:bg-purple-100 transition"
            title="Limpar filtro">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
      </svg>
      Limpar
    </button>
    <span id="filteredCount" class="ml-auto text-xs text-slate-400 hidden"></span>
  </div>
  <?php endif; ?>

  <!-- Legenda das colunas -->
  <div class="flex items-center gap-4 mb-3">
    <span class="flex items-center gap-1.5 text-xs text-slate-500">
      <span class="inline-block w-3 h-3 rounded" style="background:#ede9fe; border:1px solid #ddd8fe;"></span>
      Coluna normalizada/transformada
    </span>
    <span class="flex items-center gap-1.5 text-xs text-slate-500">
      <span class="inline-block w-3 h-3 rounded" style="background:#d1fae5; border:1px solid #a7f3d0;"></span>
      Coluna criada (ChaveM365)
    </span>
  </div>

  <!-- Tabela -->
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto overflow-y-auto" style="max-height: 580px;">
      <table id="resultsTable">
        <thead>
          <tr>
            <?php foreach ($results['headers'] as $col): ?>
              <?php
              $colName = htmlspecialchars($col);
              if ($col === 'ChaveM365'):
              ?>
              <th class="col-new-header"><?= $colName ?></th>
              <?php elseif (in_array($col, ['SkuId', 'TermAndBillingCycle'], true)): ?>
              <th class="col-highlight-header"><?= $colName ?></th>
              <?php else: ?>
              <th><?= $colName ?></th>
              <?php endif; ?>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results['preview'] as $row): ?>
          <tr>
            <?php foreach ($results['headers'] as $col): ?>
              <?php
              $val = htmlspecialchars((string)($row[$col] ?? ''));
              if ($col === 'ChaveM365'):
              ?>
              <td class="col-new-cell" title="<?= $val ?>"><?= $val ?></td>
              <?php elseif (in_array($col, ['SkuId', 'TermAndBillingCycle'], true)): ?>
              <td class="col-highlight-cell" title="<?= $val ?>"><?= $val ?></td>
              <?php else: ?>
              <td title="<?= $val ?>"><?= $val ?></td>
              <?php endif; ?>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($results['totalRows'] > count($results['preview'])): ?>
  <p class="mt-3 text-xs text-center text-slate-400">
    Exibindo <?= number_format(count($results['preview']), 0, ',', '.') ?> de <?= number_format($results['totalRows'], 0, ',', '.') ?> linhas. 
    Faça o download (CSV ou Excel) para obter o conjunto completo.
  </p>
  <?php endif; ?>

  <!-- ══════════════════════════════════════════════════════════════════════
       FASE 2 — Lista de Preços CSP NCE
  ═══════════════════════════════════════════════════════════════════════ -->
  <div class="mt-10 mb-2">
    <div class="flex items-center gap-3 mb-1">
      <div class="flex items-center justify-center w-7 h-7 rounded-full text-sm font-bold text-white shrink-0" style="background:#0f766e;">2</div>
      <h3 class="text-xl font-bold text-slate-800">Lista de Preços CSP NCE</h3>
      <?php if ($priceList): ?>
      <span class="px-2 py-0.5 text-xs font-bold text-white rounded-full" style="background:#059669;">CARREGADA</span>
      <?php else: ?>
      <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-slate-200 text-slate-500">AGUARDANDO</span>
      <?php endif; ?>
    </div>
    <p class="text-slate-500 text-sm ml-10">Faça upload da lista de preços TD SYNNEX NCE (XLSX) para habilitar a comparação.</p>
  </div>

  <?php if ($priceError): ?>
  <div class="ml-10 mb-4 flex items-start gap-3 bg-red-50 border border-red-200 rounded-xl p-4">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-600 shrink-0 mt-0.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
    </svg>
    <p class="text-sm text-red-700"><?= htmlspecialchars($priceError) ?></p>
  </div>
  <?php endif; ?>
  <?php if ($priceSuccess): ?>
  <div class="ml-10 mb-4 flex items-start gap-3 bg-green-50 border border-green-200 rounded-xl p-4">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-green-600 shrink-0 mt-0.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
    <p class="text-sm font-semibold text-green-800"><?= htmlspecialchars($priceSuccess) ?></p>
  </div>
  <?php endif; ?>

  <?php if (!$priceList): ?>
  <!-- Upload da lista de preços -->
  <div class="ml-10 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
    <div class="p-6">
      <form method="POST" enctype="multipart/form-data" id="priceForm">
        <input type="hidden" name="action" value="load_pricelist">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
          <label for="priceFile"
                 class="flex items-center gap-2 px-5 py-2.5 rounded-lg border-2 border-dashed border-slate-300 text-sm font-medium text-slate-600 cursor-pointer hover:border-teal-500 hover:bg-teal-50 transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-teal-600">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
            </svg>
            <span id="priceFileLabel">Selecionar Lista de Preços (.xlsx)</span>
          </label>
          <input type="file" id="priceFile" name="priceFile" accept=".xlsx,.xls" class="hidden">
          <button type="submit" id="priceSubmitBtn"
                  class="flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                  style="background:#0f766e;" disabled>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            Carregar e Processar
          </button>
          <p class="text-xs text-slate-400">Aba <strong>MW</strong> será processada automaticamente</p>
        </div>
      </form>
    </div>
  </div>

  <?php else: ?>
  <!-- Status lista carregada + ação de remover -->
  <div class="ml-10 mb-6 flex items-center justify-between gap-4 bg-teal-50 border border-teal-200 rounded-xl p-4">
    <div class="flex items-center gap-3">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-teal-700 shrink-0">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
      </svg>
      <div>
        <p class="text-sm font-semibold text-teal-800">Lista de preços carregada — <span class="font-bold"><?= number_format($priceList['totalRows'], 0, ',', '.') ?> itens</span> indexados</p>
        <p class="text-xs text-teal-600 mt-0.5">Chave de comparação: ProductId + SkuId + TermShort</p>
      </div>
    </div>
    <form method="POST" class="inline">
      <input type="hidden" name="action" value="clear_pricelist">
      <button type="submit" class="text-xs text-teal-700 underline hover:text-red-600 transition-colors">Remover</button>
    </form>
  </div>

  <!-- ══════════════════════════════════════════════════════════════════════
       TABELA DE COMPARAÇÃO
  ═══════════════════════════════════════════════════════════════════════ -->
  <?php
  $lookup       = $priceList['lookup'];
  $invoiceRows  = $results['preview'];
  $matched      = 0;
  $notMatched   = 0;
  $compRows     = [];

  // Colunas necessárias do invoice
  $hasProductId = in_array('ProductId', $results['headers'], true);
  $hasUnitPrice = in_array('UnitPrice', $results['headers'], true);
  $hasCurrency  = in_array('Currency', $results['headers'], true);
  $hasQty       = in_array('BillableQuantity', $results['headers'], true) ?: in_array('Quantity', $results['headers'], true);
  $qtyCol       = in_array('BillableQuantity', $results['headers'], true) ? 'BillableQuantity' : 'Quantity';

  foreach ($invoiceRows as $inv) {
      $chave    = $inv['ChaveComparacao'] ?? '';
      $pl       = $lookup[$chave] ?? null;
      $found    = $pl !== null;
      $found ? $matched++ : $notMatched++;
      $compRows[] = ['inv' => $inv, 'pl' => $pl, 'found' => $found, 'chave' => $chave];
  }
  ?>

  <div class="mt-8 mb-2">
    <div class="flex items-center gap-3 mb-1">
      <div class="flex items-center justify-center w-7 h-7 rounded-full text-sm font-bold text-white shrink-0" style="background:#2563eb;">3</div>
      <h3 class="text-xl font-bold text-slate-800">Comparação Fatura × Lista de Preços</h3>
    </div>
  </div>

  <!-- Summary pills -->
  <div class="flex flex-wrap items-center gap-3 mb-4 ml-10">
    <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-lg border border-slate-200 shadow-sm text-sm">
      <span class="w-2.5 h-2.5 rounded-full bg-green-500 shrink-0"></span>
      <span class="font-semibold text-slate-800"><?= number_format($matched, 0, ',', '.') ?></span>
      <span class="text-slate-500">correspondências</span>
    </div>
    <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-lg border border-slate-200 shadow-sm text-sm">
      <span class="w-2.5 h-2.5 rounded-full bg-red-400 shrink-0"></span>
      <span class="font-semibold text-slate-800"><?= number_format($notMatched, 0, ',', '.') ?></span>
      <span class="text-slate-500">não encontrados</span>
    </div>
    <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-lg border border-slate-200 shadow-sm text-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-slate-400">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
      </svg>
      <span class="text-slate-500">Preços em</span>
      <span class="font-semibold text-slate-700">BRL (lista) &amp; moeda original (fatura)</span>
    </div>
    <!-- Filtro de status -->
    <div class="ml-auto flex items-center gap-2">
      <label for="filterStatus" class="text-xs font-medium text-slate-600">Exibir:</label>
      <select id="filterStatus" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
        <option value="">Todos</option>
        <option value="found">Somente encontrados</option>
        <option value="notfound">Somente não encontrados</option>
      </select>
    </div>
  </div>

  <style>
    #compTable { border-collapse: collapse; min-width: 100%; white-space: nowrap; font-size: 0.75rem; }
    #compTable th { position: sticky; top: 0; z-index: 2; background: #f1f5f9; border-bottom: 2px solid #cbd5e1;
                    padding: 0.5rem 0.7rem; text-align: left; font-size: 0.68rem; text-transform: uppercase;
                    letter-spacing: 0.04em; color: #475569; }
    #compTable td { padding: 0.4rem 0.7rem; border-bottom: 1px solid #f1f5f9; color: #334155; max-width: 200px;
                    overflow: hidden; text-overflow: ellipsis; }
    #compTable tr.row-found td   { background-color: #f0fdf4; }
    #compTable tr.row-notfound td { background-color: #fff7f7; }
    #compTable tr:hover td { filter: brightness(0.97); }
    .status-found    { display: inline-flex; align-items: center; gap: 4px; color: #15803d; font-weight: 600; }
    .status-notfound { display: inline-flex; align-items: center; gap: 4px; color: #b91c1c; font-weight: 600; }
  </style>

  <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden ml-0">
    <div class="overflow-x-auto overflow-y-auto" style="max-height: 550px;">
      <table id="compTable">
        <thead>
          <tr>
            <th>Status</th>
            <th>ProductId</th>
            <th>SkuId</th>
            <th>SkuName</th>
            <th>Term</th>
            <?php if ($hasQty): ?><th>Qtd</th><?php endif; ?>
            <?php if ($hasUnitPrice): ?><th>Preço Fatura</th><?php endif; ?>
            <?php if ($hasCurrency): ?><th>Moeda</th><?php endif; ?>
            <th style="border-left: 2px solid #cbd5e1;">Produto (Lista)</th>
            <th>Preço Lista (BRL)</th>
            <th>Anual Pg Mensal (BRL)</th>
            <th>Família</th>
            <th>Seguimento</th>
            <th>Tipo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($compRows as $cr): ?>
          <?php $inv = $cr['inv']; $pl = $cr['pl']; $found = $cr['found']; ?>
          <tr class="<?= $found ? 'row-found' : 'row-notfound' ?>" data-status="<?= $found ? 'found' : 'notfound' ?>">
            <td>
              <?php if ($found): ?>
              <span class="status-found">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-3.5 h-3.5">
                  <path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" />
                </svg>
                Encontrado
              </span>
              <?php else: ?>
              <span class="status-notfound">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-3.5 h-3.5">
                  <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                </svg>
                Não encontrado
              </span>
              <?php endif; ?>
            </td>
            <td title="<?= htmlspecialchars($inv['ProductId'] ?? '') ?>"><?= htmlspecialchars($inv['ProductId'] ?? '—') ?></td>
            <td><?= htmlspecialchars($inv['SkuId'] ?? '—') ?></td>
            <td title="<?= htmlspecialchars($inv['SkuName'] ?? '') ?>"><?= htmlspecialchars($inv['SkuName'] ?? '—') ?></td>
            <td><?= htmlspecialchars($inv['TermAndBillingCycle'] ?? '—') ?></td>
            <?php if ($hasQty): ?><td><?= htmlspecialchars($inv[$qtyCol] ?? '—') ?></td><?php endif; ?>
            <?php if ($hasUnitPrice): ?><td><?= htmlspecialchars($inv['UnitPrice'] ?? '—') ?></td><?php endif; ?>
            <?php if ($hasCurrency): ?><td><?= htmlspecialchars($inv['Currency'] ?? '—') ?></td><?php endif; ?>
            <?php if ($found): ?>
            <td style="border-left:2px solid #e2e8f0;" title="<?= htmlspecialchars($pl['Produto']) ?>"><?= htmlspecialchars($pl['Produto']) ?></td>
            <td><?= $pl['Preco'] !== null ? 'R$ ' . number_format((float)$pl['Preco'], 2, ',', '.') : '—' ?></td>
            <td><?= $pl['AnualPgMensal'] !== null ? 'R$ ' . number_format((float)$pl['AnualPgMensal'], 2, ',', '.') : '—' ?></td>
            <td><?= htmlspecialchars($pl['Familia']) ?></td>
            <td><?= htmlspecialchars($pl['Seguimento']) ?></td>
            <td><?= htmlspecialchars($pl['Tipo']) ?></td>
            <?php else: ?>
            <td style="border-left:2px solid #e2e8f0; color:#94a3b8;" colspan="6">Sem correspondência na lista de preços</td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (count($invoiceRows) < $results['totalRows']): ?>
  <p class="mt-2 text-xs text-center text-slate-400">
    Comparação sobre as primeiras <?= number_format(count($invoiceRows), 0, ',', '.') ?> linhas da fatura.
  </p>
  <?php endif; ?>

  <?php endif; // end priceList loaded ?>

  <?php endif; // end results ?>

</div><!-- /app -->

<script>
(function () {

  // ── Filtro de ProductCategory ────────────────────────────────────────────
  const filterSel   = document.getElementById('filterCategory');
  const clearBtn    = document.getElementById('clearCategoryFilter');
  const countBadge  = document.getElementById('filteredCount');
  const table       = document.getElementById('resultsTable');

  if (filterSel && table) {
    // Descobre o índice da coluna ProductCategory
    const headers   = Array.from(table.querySelectorAll('thead th'));
    const catIndex  = headers.findIndex(th => th.textContent.trim() === 'ProductCategory');

    function applyFilter() {
      const val     = filterSel.value;
      const rows    = Array.from(table.querySelectorAll('tbody tr'));
      let   visible = 0;

      rows.forEach(tr => {
        if (!val || catIndex < 0) {
          tr.style.display = '';
          visible++;
        } else {
          const cell = tr.cells[catIndex];
          const match = cell && cell.textContent.trim() === val;
          tr.style.display = match ? '' : 'none';
          if (match) visible++;
        }
      });

      const isFiltered = val !== '';
      clearBtn.classList.toggle('hidden',   !isFiltered);
      clearBtn.classList.toggle('flex',      isFiltered);
      countBadge.classList.toggle('hidden', !isFiltered);
      if (isFiltered) {
        countBadge.textContent = visible.toLocaleString('pt-BR') + ' linha' + (visible !== 1 ? 's' : '') + ' visível' + (visible !== 1 ? 'eis' : '');
      }
    }

    filterSel.addEventListener('change', applyFilter);
    clearBtn.addEventListener('click', () => {
      filterSel.value = '';
      applyFilter();
    });
  }
  // ────────────────────────────────────────────────────────────────────────

  const dropZone  = document.getElementById('dropZone');
  const fileInput = document.getElementById('fileInput');
  const fileLabel = document.getElementById('fileLabel');
  const submitBtn = document.getElementById('submitBtn');

  if (dropZone) {
    // Clique na zona de drop → abre seletor
    dropZone.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
      handleFileSelected(fileInput.files[0]);
    });

    dropZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropZone.classList.add('dragover');
    });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropZone.classList.remove('dragover');
      const file = e.dataTransfer.files[0];
      if (file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
        handleFileSelected(file);
      }
    });

    function handleFileSelected(file) {
      if (!file) return;
      const allowed = ['csv', 'xlsx', 'xls'];
      const ext = file.name.split('.').pop().toLowerCase();
      if (!allowed.includes(ext)) {
        alert('Formato não suportado. Use CSV, XLSX ou XLS.');
        return;
      }
      const sizeInMB = (file.size / 1024 / 1024).toFixed(2);
      fileLabel.textContent = `📄 ${file.name} (${sizeInMB} MB)`;
      fileLabel.classList.remove('hidden');
      submitBtn.disabled = false;
    }

    // Loading state no submit
    const uploadFormEl = document.getElementById('uploadForm');
    if (uploadFormEl) {
      uploadFormEl.addEventListener('submit', function () {
        submitBtn.disabled  = true;
        submitBtn.innerHTML = `
          <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
          </svg>
          Processando…`;
      });
    }
  } // end if (dropZone)

  // ── Upload da Lista de Preços ────────────────────────────────────────────
  const priceFileInput = document.getElementById('priceFile');
  const priceFileLabel = document.getElementById('priceFileLabel');
  const priceSubmitBtn = document.getElementById('priceSubmitBtn');
  const priceForm      = document.getElementById('priceForm');

  if (priceFileInput) {
    priceFileInput.addEventListener('change', () => {
      const f = priceFileInput.files[0];
      if (f) {
        priceFileLabel.textContent = `📊 ${f.name}`;
        priceSubmitBtn.disabled = false;
      }
    });
  }
  if (priceForm) {
    priceForm.addEventListener('submit', function () {
      if (priceSubmitBtn) {
        priceSubmitBtn.disabled  = true;
        priceSubmitBtn.innerHTML = `
          <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
          </svg>
          Processando lista…`;
      }
    });
  }

  // ── Filtro de status na tabela de comparação ─────────────────────────────
  const filterStatus = document.getElementById('filterStatus');
  const compTable    = document.getElementById('compTable');
  if (filterStatus && compTable) {
    filterStatus.addEventListener('change', () => {
      const val  = filterStatus.value;
      const rows = Array.from(compTable.querySelectorAll('tbody tr'));
      rows.forEach(tr => {
        const s = tr.dataset.status;
        tr.style.display = !val || s === val ? '' : 'none';
      });
    });
  }

})();
</script>
</body>
</html>
