<?php
// filepath: public/migracao-t1-t2.php
session_start();

// Handle active tab
$activeTab = isset($_GET['tab']) ? htmlspecialchars($_GET['tab']) : 'visao';
$validTabs = ['visao', 'checklist', 'precificacao', 'downloads', 'api', 'suporte'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'visao';
}

$tabs = [
    ['id' => 'visao', 'label' => 'Visão Geral', 'icon' => 'eye'],
    ['id' => 'checklist', 'label' => 'Checklist', 'icon' => 'check-square'],
  ['id' => 'precificacao', 'label' => 'Precificação CSP', 'icon' => 'currency'],
  ['id' => 'downloads', 'label' => 'Download de Materiais', 'icon' => 'download'],
    ['id' => 'api', 'label' => 'Automação via API', 'icon' => 'code'],
    ['id' => 'suporte', 'label' => 'Suporte', 'icon' => 'support'],
];

$uploadError = null;
$uploadSuccess = null;
$precificacaoUploads = $_SESSION['precificacaoUploads'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

  if ($action === 'upload_precificacao') {
    $activeTab = 'precificacao';

    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }

    $requiredFiles = [
      'tdPriceList' => [
        'label' => 'Lista de Preço TD SYNNEX',
        'prefix' => 'td_price_',
      ],
      'partnerReconFile' => [
        'label' => 'Recon File do Parceiro',
        'prefix' => 'partner_recon_',
      ],
    ];

    $allowedExtensions = ['csv', 'xlsx', 'xls'];
    $maxFileSize = 80 * 1024 * 1024; // 80 MB por arquivo
    $saved = [];
    $errors = [];

    foreach ($requiredFiles as $fieldName => $cfg) {
      if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Envie o arquivo: ' . $cfg['label'] . '.';
        continue;
      }

      $file = $_FILES[$fieldName];
      if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Erro no upload de ' . $cfg['label'] . ' (codigo ' . (int)$file['error'] . ').';
        continue;
      }

      $originalName = (string)$file['name'];
      $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));

      if (!in_array($extension, $allowedExtensions, true)) {
        $errors[] = 'Formato invalido em ' . $cfg['label'] . '. Use CSV, XLSX ou XLS.';
        continue;
      }

      if ((int)$file['size'] > $maxFileSize) {
        $errors[] = $cfg['label'] . ' excede o limite de 80 MB.';
        continue;
      }

      $safeBaseName = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)pathinfo($originalName, PATHINFO_FILENAME));
      $targetName = $cfg['prefix'] . date('Ymd_His') . '_' . uniqid() . '_' . $safeBaseName . '.' . $extension;
      $targetPath = $uploadDir . '/' . $targetName;

      if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
        $errors[] = 'Falha ao salvar ' . $cfg['label'] . '.';
        continue;
      }

      $saved[$fieldName] = [
        'label' => $cfg['label'],
        'originalName' => $originalName,
        'savedName' => $targetName,
        'size' => (int)$file['size'],
      ];
    }

    if (empty($errors)) {
      $_SESSION['precificacaoUploads'] = [
        'uploadedAt' => date('d/m/Y H:i'),
        'files' => $saved,
      ];
      $precificacaoUploads = $_SESSION['precificacaoUploads'];
      $uploadSuccess = 'Upload concluido com sucesso para os dois arquivos.';
    } else {
      $uploadError = implode(' ', $errors);
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Migração CSP NCE (T1 → T2) - TD SYNNEX</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    .page-wrapper { margin-top: 0; }
    :root {
      --teal: #005758;
      --teal-dark: #003031;
      --teal-light: #007a7b;
      --charcoal: #262626;
      --gray: #737373;
      --light-gray: #f5f5f7;
      --blue: #0078D4;
      --slate-50: #f8fafc;
      --slate-100: #f1f5f9;
      --slate-200: #e2e8f0;
      --slate-300: #cbd5e1;
      --slate-400: #94a3b8;
      --slate-500: #64748b;
      --slate-600: #475569;
      --slate-700: #334155;
      --slate-800: #1e293b;
      --slate-900: #0f172a;
      --yellow-50: #fefce8;
      --yellow-100: #fef9c3;
      --yellow-800: #854d0e;
      --red-50: #fef2f2;
      --red-600: #dc2626;
      --green-50: #f0fdf4;
      --green-600: #16a34a;
      --green-700: #15803d;
      --orange-50: #fff7ed;
      --orange-500: #f97316;
      --orange-600: #ea580c;
      --purple-50: #faf5ff;
      --purple-600: #9333ea;
    }
    
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
      color: var(--slate-800);
      min-height: 100vh;
    }

    .app-container {
      min-height: calc(100vh - 56px);
      display: flex;
      flex-direction: column;
    }
    
    @media (min-width: 768px) {
      .app-container { flex-direction: row; }
    }

    .mobile-header {
      background: var(--teal-dark);
      padding: .85rem 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    @media (min-width: 768px) {
      .mobile-header { display: none; }
    }
    
    .mobile-header .brand {
      font-weight: 700;
      font-size: 1rem;
      color: #fff;
    }
    
    .mobile-header .menu-btn {
      background: rgba(255,255,255,.15);
      border: none;
      color: #fff;
      cursor: pointer;
      padding: 8px;
      border-radius: 4px;
      transition: background .2s;
    }
    
    .mobile-header .menu-btn:hover { background: rgba(255,255,255,.25); }
    
    .mobile-header .menu-btn svg { width: 22px; height: 22px; }

    /* Sidebar */
    .sidebar {
      display: none;
      width: 100%;
      background: #fff;
      flex-shrink: 0;
      transition: all 0.3s ease;
    }
    
    .sidebar.open { display: block; }
    
    @media (min-width: 768px) {
      .sidebar {
        display: block;
        width: 280px;
        min-height: calc(100vh - 56px);
        box-shadow: 1px 0 8px rgba(0,0,0,.06);
        border-right: 1px solid var(--slate-200);
      }
    }
    
    .sidebar-header {
      padding: 1.75rem 1.5rem 1.25rem;
      display: none;
      border-bottom: 1px solid var(--slate-200);
    }
    
    @media (min-width: 768px) {
      .sidebar-header { display: block; }
    }
    
    .sidebar-info {
      font-size: .75rem;
      background: var(--slate-100);
      color: var(--slate-600);
      padding: .75rem .95rem;
      border-radius: 4px;
      line-height: 1.55;
    }
    
    .sidebar-info strong { color: var(--teal-dark); }
    
    .sidebar-nav { padding: 1.25rem 1rem; }
    
    @media (min-width: 768px) {
      .sidebar-nav { margin-top: 0; }
    }
    
    .sidebar-nav ul { list-style: none; }
    .sidebar-nav li { margin-bottom: 6px; }
    
    .sidebar-nav a {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .85rem 1rem;
      border-radius: 4px;
      text-decoration: none;
      color: var(--slate-600);
      font-weight: 500;
      font-size: .88rem;
      transition: all .2s ease;
    }
    
    .sidebar-nav a:hover {
      background: var(--slate-100);
      color: var(--teal-dark);
    }
    
    .sidebar-nav a.active {
      background: var(--teal-dark);
      color: #fff;
      font-weight: 600;
    }
    
    .sidebar-nav a svg {
      width: 20px;
      height: 20px;
      flex-shrink: 0;
      opacity: .85;
    }
    
    .sidebar-nav a.active svg { opacity: 1; }

    /* Main Content */
    .main-content {
      flex: 1;
      padding: 2rem;
      overflow-y: auto;
    }
    
    @media (min-width: 768px) {
      .main-content { padding: 2.5rem 3.5rem; }
    }
    
    @media (min-width: 1024px) {
      .main-content { padding: 3rem 5rem; }
    }
    
    .content-card {
      max-width: 960px;
      margin: 0 auto;
      background: #fff;
      border-radius: 4px;
      box-shadow: 0 4px 30px rgba(0,0,0,.06), 0 1px 3px rgba(0,0,0,.04);
      border: 1px solid rgba(255,255,255,.8);
      padding: 2.5rem;
      min-height: calc(100vh - 56px - 6rem);
    }
    
    @media (max-width: 767px) {
      .content-card { padding: 1.5rem; border-radius: 4px; }
    }

    /* Typography */
    .section-title {
      font-size: 1.85rem;
      font-weight: 800;
      color: var(--teal-dark);
      border-bottom: 1px solid var(--slate-200);
      padding-bottom: .85rem;
      margin-bottom: 1.5rem;
      letter-spacing: -.3px;
    }
    
    .lead-text {
      font-size: 1rem;
      line-height: 1.75;
      color: var(--slate-600);
      margin-bottom: 1rem;
    }
    
    .lead-text strong {
      color: var(--teal-dark);
      font-weight: 600;
    }

    /* Objective Cards */
    .objectives-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
      margin-bottom: 2rem;
    }
    
    @media (min-width: 640px) {
      .objectives-grid { grid-template-columns: repeat(2, 1fr); }
    }
    
    .objective-card {
      background: var(--slate-50);
      border: 1px solid var(--slate-200);
      border-radius: 4px;
      padding: 1.25rem;
      transition: all .2s ease;
    }
    
    .objective-card:hover {
      border-color: var(--teal);
    }
    
    .objective-card .icon {
      width: 40px;
      height: 40px;
      background: var(--teal-dark);
      color: #fff;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: .75rem;
    }
    
    .objective-card .icon svg {
      width: 20px;
      height: 20px;
    }
    
    .objective-card h4 {
      font-size: .95rem;
      font-weight: 600;
      color: var(--teal-dark);
      margin-bottom: .35rem;
    }
    
    .objective-card p {
      font-size: .85rem;
      color: var(--slate-600);
      line-height: 1.5;
    }

    /* Phase Cards */
    .phase-card {
      background: #fff;
      border: 1px solid var(--slate-200);
      border-radius: 4px;
      margin-bottom: 1.5rem;
      overflow: hidden;
    }
    
    .phase-header {
      background: var(--teal-dark);
      padding: 1rem 1.25rem;
      display: flex;
      align-items: center;
      gap: .75rem;
    }
    
    .phase-number {
      background: #fff;
      color: var(--teal-dark);
      width: 32px;
      height: 32px;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: .9rem;
    }
    
    .phase-title {
      color: #fff;
      font-size: 1rem;
      font-weight: 600;
    }
    
    .phase-body {
      padding: 1.5rem;
    }
    
    .phase-section {
      margin-bottom: 1.25rem;
    }
    
    .phase-section:last-child {
      margin-bottom: 0;
    }
    
    .phase-section h5 {
      font-size: .85rem;
      font-weight: 600;
      color: var(--teal-dark);
      margin-bottom: .5rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    
    .phase-section h5 svg {
      width: 16px;
      height: 16px;
      color: var(--teal);
    }
    
    .phase-section ul {
      list-style: none;
      padding-left: 0;
    }
    
    .phase-section li {
      font-size: .88rem;
      color: var(--slate-600);
      padding: .4rem 0;
      padding-left: 1.25rem;
      position: relative;
      line-height: 1.5;
    }
    
    .phase-section li::before {
      content: '→';
      position: absolute;
      left: 0;
      color: var(--teal);
      font-weight: 600;
    }

    /* Highlight Box */
    .highlight-box {
      background: var(--orange-50);
      border: 1px solid var(--orange-500);
      border-left: 4px solid var(--orange-500);
      border-radius: 4px;
      padding: 1rem 1.25rem;
      margin-top: 1rem;
    }
    
    .highlight-box h5 {
      font-size: .85rem;
      font-weight: 600;
      color: var(--orange-600);
      margin-bottom: .35rem;
    }
    
    .highlight-box p {
      font-size: .85rem;
      color: var(--slate-700);
      line-height: 1.5;
    }

    /* Info Box */
    .info-box {
      background: var(--slate-50);
      border: 1px solid var(--slate-200);
      border-radius: 4px;
      padding: 1rem 1.25rem;
      margin-top: 1rem;
    }
    
    .info-box.success {
      background: var(--green-50);
      border-color: var(--green-600);
    }
    
    .info-box h5 {
      font-size: .85rem;
      font-weight: 600;
      color: var(--teal-dark);
      margin-bottom: .35rem;
    }
    
    .info-box.success h5 {
      color: var(--green-700);
    }
    
    .info-box p, .info-box ul {
      font-size: .85rem;
      color: var(--slate-600);
      line-height: 1.5;
    }

    /* Checklist */
    .checklist-container {
      background: var(--slate-50);
      border-radius: 4px;
      border: 1px solid var(--slate-200);
      overflow: hidden;
    }
    
    .checklist-item {
      display: flex;
      align-items: center;
      padding: 1rem 1.15rem;
      cursor: pointer;
      transition: all .2s ease;
      border-bottom: 1px solid var(--slate-200);
    }
    
    .checklist-item:last-child { border-bottom: none; }
    .checklist-item:hover { background: #fff; }
    
    .checklist-item.done {
      background: rgba(0,87,88,.04);
    }
    
    .checklist-checkbox {
      width: 24px;
      height: 24px;
      border: 2px solid var(--slate-300);
      border-radius: 4px;
      margin-right: 1rem;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all .2s ease;
    }
    
    .checklist-item:hover .checklist-checkbox { border-color: var(--teal); }
    
    .checklist-item.done .checklist-checkbox {
      background: var(--teal-dark);
      border-color: var(--teal-dark);
      color: #fff;
    }
    
    .checklist-checkbox svg {
      width: 14px;
      height: 14px;
      display: none;
    }
    
    .checklist-item.done .checklist-checkbox svg { display: block; }
    
    .checklist-text {
      font-size: .9rem;
      color: var(--slate-700);
      font-weight: 500;
    }
    
    .checklist-item.done .checklist-text {
      color: var(--teal-dark);
      text-decoration: line-through;
      opacity: .7;
    }

    /* Progress */
    .progress-bar {
      width: 100%;
      background: var(--slate-200);
      border-radius: 4px;
      height: 10px;
      margin-bottom: .5rem;
      overflow: hidden;
    }
    
    .progress-fill {
      height: 100%;
      background: var(--teal-dark);
      border-radius: 4px;
      transition: width .5s ease;
    }
    
    .progress-text {
      text-align: right;
      font-size: .9rem;
      font-weight: 600;
      color: var(--teal-dark);
      margin-bottom: 1.5rem;
    }

    /* API Cards */
    .api-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
    }
    
    @media (min-width: 640px) {
      .api-grid { grid-template-columns: repeat(2, 1fr); }
    }
    
    .api-card {
      background: var(--slate-50);
      border: 1px solid var(--slate-200);
      border-radius: 4px;
      padding: 1.25rem;
      transition: all .2s ease;
    }
    
    .api-card:hover { border-color: var(--teal); }
    
    .api-card h4 {
      font-size: .95rem;
      font-weight: 600;
      color: var(--teal-dark);
      margin-bottom: .5rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    
    .api-card h4 svg {
      width: 18px;
      height: 18px;
      color: var(--teal);
    }
    
    .api-card ul {
      list-style: none;
      font-size: .85rem;
      color: var(--slate-600);
    }
    
    .api-card li {
      padding: .3rem 0;
      padding-left: 1rem;
      position: relative;
    }
    
    .api-card li::before {
      content: '•';
      position: absolute;
      left: 0;
      color: var(--teal);
      font-weight: bold;
    }

    /* Precificacao Layout (inspirado em analise-financeira) */
    .pricing-head {
      display: flex;
      align-items: center;
      gap: .9rem;
      margin-bottom: 1rem;
    }

    .pricing-head-icon {
      background: #ccfbf1;
      padding: .6rem;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .pricing-head-icon svg {
      width: 22px;
      height: 22px;
      color: var(--teal-dark);
    }

    .pricing-head h3 {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--slate-800);
      margin-bottom: .1rem;
    }

    .pricing-head p {
      font-size: .82rem;
      color: var(--slate-500);
      margin: 0;
    }

    .pricing-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
      margin-bottom: 1.2rem;
    }

    @media (min-width: 992px) {
      .pricing-grid {
        grid-template-columns: 320px minmax(0, 1fr);
      }
    }

    .pricing-card {
      background: #fff;
      border: 1px solid var(--slate-200);
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(15,23,42,.05);
      overflow: hidden;
    }

    .pricing-card .card-head {
      background: #f8fafc;
      border-bottom: 1px solid var(--slate-200);
      padding: .8rem 1rem;
      font-size: .85rem;
      font-weight: 700;
      color: var(--teal-dark);
      display: flex;
      align-items: center;
      gap: .45rem;
    }

    .pricing-card .card-head svg {
      width: 16px;
      height: 16px;
      color: var(--teal);
    }

    .pricing-card .card-body {
      padding: 1rem;
    }

    .upload-zone-mini {
      border: 2px dashed var(--slate-300);
      border-radius: 8px;
      padding: .85rem;
      background: #f8fafc;
      margin-bottom: .75rem;
      transition: all .2s ease;
    }

    .upload-zone-mini:hover {
      border-color: var(--teal);
      background: rgba(0,87,88,.03);
    }

    .upload-zone-mini label {
      display: block;
      font-size: .78rem;
      font-weight: 700;
      color: var(--teal-dark);
      margin-bottom: .25rem;
    }

    .upload-zone-mini small {
      display: block;
      font-size: .72rem;
      color: var(--slate-500);
      margin-bottom: .4rem;
    }

    .upload-zone-mini input[type="file"] {
      width: 100%;
      font-size: .75rem;
      color: var(--slate-700);
    }

    .btn-upload {
      width: 100%;
      background: var(--teal-dark);
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: .62rem .9rem;
      font-size: .82rem;
      font-weight: 600;
      cursor: pointer;
      transition: background .2s ease;
    }

    .btn-upload:hover {
      background: var(--teal);
    }

    .upload-help {
      margin-top: .55rem;
      font-size: .72rem;
      color: var(--slate-500);
      text-align: center;
    }

    .pricing-stats {
      display: grid;
      grid-template-columns: 1fr;
      gap: .8rem;
      margin-bottom: .8rem;
    }

    @media (min-width: 640px) {
      .pricing-stats {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    .stat-mini {
      background: #fff;
      border: 1px solid var(--slate-200);
      border-top: 4px solid var(--teal);
      border-radius: 8px;
      padding: .8rem .75rem;
      text-align: center;
    }

    .stat-mini .value {
      font-size: 1.15rem;
      font-weight: 800;
      color: var(--slate-800);
      line-height: 1.1;
    }

    .stat-mini .label {
      margin-top: .35rem;
      font-size: .68rem;
      text-transform: uppercase;
      color: var(--slate-500);
      letter-spacing: .4px;
      font-weight: 600;
    }

    /* Support Cards */
    .support-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
    }
    
    @media (min-width: 768px) {
      .support-grid { grid-template-columns: repeat(2, 1fr); }
    }
    
    .support-card {
      background: var(--slate-50);
      border: 1px solid var(--slate-200);
      border-radius: 4px;
      padding: 1.25rem;
      transition: all .2s ease;
    }
    
    .support-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,.06); }
    
    .support-card h5 {
      font-weight: 600;
      color: var(--teal-dark);
      margin-bottom: .5rem;
      font-size: .95rem;
    }
    
    .support-card p {
      font-size: .85rem;
      color: var(--slate-600);
      line-height: 1.55;
    }
    
    .support-card .badge {
      display: inline-block;
      background: var(--teal-dark);
      color: #fff;
      padding: .2rem .6rem;
      border-radius: 4px;
      font-size: .75rem;
      font-weight: 600;
      margin-bottom: .5rem;
    }

    /* Back Link */
    .top-actions {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      color: var(--slate-500);
      text-decoration: none;
      font-size: .85rem;
      font-weight: 500;
      padding: .45rem .75rem .45rem .55rem;
      border-radius: 4px;
      transition: all .2s ease;
    }
    
    .back-link:hover {
      color: var(--teal-dark);
      background: var(--slate-100);
    }
    
    .back-link svg { width: 16px; height: 16px; }

    /* Executive Journey Timeline - Horizontal */
    .journey-section {
      margin: 2.5rem 0;
    }
    
    .journey-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
      gap: 1rem;
    }
    
    .journey-header h3 {
      font-size: 1.1rem;
      font-weight: 600;
      color: var(--teal-dark);
    }
    
    .journey-legend {
      display: flex;
      gap: 1.25rem;
      font-size: .75rem;
    }
    
    .legend-item {
      display: flex;
      align-items: center;
      gap: .35rem;
      color: var(--slate-500);
    }
    
    .legend-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
    }
    
    .legend-dot.completed { background: var(--teal); }
    .legend-dot.current { background: var(--blue); }
    .legend-dot.pending { background: var(--slate-300); }
    
    .journey-timeline {
      position: relative;
      padding: 0 .25rem;
      overflow: visible;
    }
    
    .journey-steps-wrapper {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      position: relative;
      gap: .35rem;
      padding: 0;
      padding-top: 120px;
      min-height: 300px;
    }
    
    .journey-line {
      position: absolute;
      top: 138px;
      left: 12px;
      right: 12px;
      height: 3px;
      background: var(--slate-200);
      z-index: 0;
    }
    
    .journey-line-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--teal) 0%, var(--blue) 100%);
      width: 0%;
      transition: width .5s ease;
    }
    
    .journey-step {
      display: flex;
      flex-direction: column;
      align-items: center;
      flex: 1;
      position: relative;
      cursor: pointer;
      min-width: 0;
      padding: 0 .1rem;
      z-index: 1;
    }

    .journey-step:hover,
    .journey-step:focus-within {
      z-index: 30;
    }
    
    /* Odd steps (1, 3, 5, 7): card below */
    .journey-step:nth-child(odd) {
      flex-direction: column;
    }
    
    .journey-step:nth-child(odd) .step-marker {
      order: 1;
    }
    
    .journey-step:nth-child(odd) .step-card {
      order: 2;
      margin-top: .5rem;
    }
    
    .journey-step:nth-child(odd) .step-card-expanded {
      top: calc(100% + .25rem);
      bottom: auto;
    }
    
    /* Even steps (2, 4, 6, 8): card above */
    .journey-step:nth-child(even) {
      flex-direction: column-reverse;
      margin-top: -120px;
    }
    
    .journey-step:nth-child(even) .step-marker {
      order: 2;
    }
    
    .journey-step:nth-child(even) .step-card {
      order: 1;
      margin-bottom: .5rem;
    }
    
    .journey-step:nth-child(even) .step-card-expanded {
      bottom: calc(100% + .25rem);
      top: auto;
    }
    
    .step-marker {
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
      z-index: 2;
    }
    
    .step-number {
      width: 32px;
      height: 32px;
      background: #fff;
      border: 3px solid var(--slate-300);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: .78rem;
      color: var(--slate-400);
      transition: all .3s ease;
    }
    
    .journey-step.completed .step-number {
      background: var(--teal);
      border-color: var(--teal);
      color: #fff;
    }
    
    .journey-step.current .step-number {
      background: var(--blue);
      border-color: var(--blue);
      color: #fff;
      box-shadow: 0 4px 15px rgba(0,120,212,.35);
      transform: scale(1.15);
    }
    
    .step-card {
      background: var(--slate-50);
      border: 1px solid var(--slate-200);
      border-radius: 4px;
      padding: .5rem .35rem;
      width: 100%;
      max-width: 96px;
      transition: all .2s ease;
      text-align: center;
    }
    
    .journey-step.current .step-card {
      background: #fff;
      border-color: var(--teal);
      box-shadow: 0 4px 20px rgba(0,87,88,.12);
    }
    
    .journey-step:hover .step-card {
      border-color: var(--teal);
    }
    
    .step-phase {
      display: inline-block;
      font-size: .5rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .3px;
      color: var(--slate-400);
      margin-bottom: .15rem;
    }
    
    .journey-step.current .step-phase {
      color: var(--teal);
    }
    
    .step-title {
      font-size: .66rem;
      font-weight: 600;
      color: var(--teal-dark);
      margin-bottom: .25rem;
      line-height: 1.2;
    }
    
    .step-description {
      display: none;
    }
    
    .step-meta {
      display: flex;
      flex-direction: column;
      gap: .15rem;
      align-items: center;
    }
    
    .meta-item {
      display: flex;
      align-items: center;
      gap: .2rem;
      font-size: .56rem;
      color: var(--slate-500);
    }
    
    .meta-item svg {
      width: 10px;
      height: 10px;
    }
    
    .meta-item.owner {
      color: var(--teal-dark);
      font-weight: 500;
    }
    
    .step-deliverables {
      display: none;
    }
    
    /* Expanded card on hover/focus */
    .journey-step .step-card-expanded {
      display: none;
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
      width: 300px;
      max-width: min(320px, calc(100vw - 2rem));
      background: #fff;
      border: 1px solid var(--teal);
      border-radius: 4px;
      padding: 1rem;
      box-sizing: border-box;
      box-shadow: 0 8px 30px rgba(0,0,0,.15);
      z-index: 100;
      text-align: left;
    }
    
    .journey-step:hover .step-card-expanded,
    .journey-step:focus .step-card-expanded {
      display: block;
    }
    
    .step-card-expanded .step-phase {
      font-size: .6rem;
    }
    
    .step-card-expanded .step-title {
      font-size: .88rem;
      margin-bottom: .4rem;
    }
    
    .step-card-expanded .step-description {
      display: block;
      font-size: .78rem;
      color: var(--slate-600);
      line-height: 1.45;
      margin-bottom: .6rem;
    }
    
    .step-card-expanded .step-meta {
      flex-direction: row;
      justify-content: flex-start;
      gap: .75rem;
    }
    
    .step-card-expanded .step-deliverables {
      display: block;
      margin-top: .6rem;
      padding: .75rem;
      border-top: 1px solid var(--slate-200);
      background: var(--slate-50);
      border-radius: 4px;
    }
    
    .step-card-expanded .step-deliverables h5 {
      font-size: .65rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .3px;
      color: var(--slate-400);
      margin-bottom: .35rem;
    }
    
    .step-card-expanded .step-deliverables ul {
      list-style: none;
      font-size: .72rem;
      color: var(--slate-600);
      display: flex;
      flex-direction: column;
      gap: .35rem;
    }
    
    .step-card-expanded .step-deliverables li {
      padding: 0;
      position: relative;
      background: #fff;
      border: 1px solid var(--slate-200);
      border-radius: 4px;
      line-height: 1.4;
    }

    .step-check-item {
      display: flex;
      align-items: flex-start;
      gap: .55rem;
      width: 100%;
      padding: .45rem .55rem;
      cursor: pointer;
    }

    .step-check-input {
      width: .95rem;
      height: .95rem;
      margin-top: .1rem;
      accent-color: var(--teal-dark);
      cursor: pointer;
      flex-shrink: 0;
    }

    .step-check-text {
      display: block;
      color: var(--slate-700);
      transition: color .2s ease, opacity .2s ease, text-decoration .2s ease;
    }

    .step-deliverables li.is-checked {
      background: rgba(0,87,88,.04);
      border-color: rgba(0, 87, 88, .22);
    }

    .step-deliverables li.is-checked .step-check-text {
      color: var(--teal-dark);
      text-decoration: line-through;
      opacity: .72;
    }

    /* Timeline */
    .timeline {
      position: relative;
      padding-left: 2rem;
    }
    
    .timeline::before {
      content: '';
      position: absolute;
      left: 6px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: var(--slate-200);
    }
    
    .timeline-item {
      position: relative;
      padding-bottom: 1.5rem;
    }
    
    .timeline-item:last-child { padding-bottom: 0; }
    
    .timeline-item::before {
      content: '';
      position: absolute;
      left: -2rem;
      top: 4px;
      width: 14px;
      height: 14px;
      background: var(--teal-dark);
      border-radius: 50%;
      border: 2px solid #fff;
      box-shadow: 0 0 0 2px var(--teal-dark);
    }
    
    .timeline-item h5 {
      font-size: .9rem;
      font-weight: 600;
      color: var(--teal-dark);
      margin-bottom: .25rem;
    }
    
    .timeline-item p {
      font-size: .85rem;
      color: var(--slate-600);
      line-height: 1.5;
    }

    /* Tips Box */
    .tips-box {
      background: var(--purple-50);
      border: 1px solid var(--purple-600);
      border-left: 4px solid var(--purple-600);
      border-radius: 4px;
      padding: 1.25rem;
      margin-top: 2rem;
    }
    
    .tips-box h4 {
      font-size: .95rem;
      font-weight: 600;
      color: var(--purple-600);
      margin-bottom: .75rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    
    .tips-box ul {
      list-style: none;
      font-size: .85rem;
      color: var(--slate-700);
    }
    
    .tips-box li {
      padding: .35rem 0;
      padding-left: 1.25rem;
      position: relative;
      line-height: 1.5;
    }
    
    .tips-box li::before {
      content: '💡';
      position: absolute;
      left: 0;
      font-size: .75rem;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(15px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fade { animation: fadeIn .4s ease-out; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../templates/topbar.php'; ?>
  
  <div class="page-wrapper">
    <div class="app-container">
      <!-- Mobile Header -->
      <div class="mobile-header">
        <div class="brand">Migração T1 → T2</div>
        <button class="menu-btn" id="menuToggle" aria-label="Toggle menu">
          <svg id="menuIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
          <svg id="closeIcon" style="display:none;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Sidebar -->
      <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
          <div class="sidebar-info">
            <strong>Migração CSP NCE</strong><br>
            Fluxo operacional para Parceiros Tier 1 migrando para Tier 2.
          </div>
        </div>
        <nav class="sidebar-nav">
          <ul>
            <?php foreach ($tabs as $tab): ?>
            <li>
              <a href="?tab=<?= $tab['id'] ?>" class="<?= $activeTab === $tab['id'] ? 'active' : '' ?>">
                <?php if ($tab['icon'] === 'eye'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                <?php elseif ($tab['icon'] === 'arrows'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                <?php elseif ($tab['icon'] === 'check-square'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                <?php elseif ($tab['icon'] === 'code'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" /></svg>
                <?php elseif ($tab['icon'] === 'currency'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m3-9.75H9.75a2.25 2.25 0 0 0 0 4.5h4.5a2.25 2.25 0 0 1 0 4.5H9m3 0v2.25m0-15V3.75" /></svg>
                <?php elseif ($tab['icon'] === 'download'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-16.5-6L12 15m0 0 7.5-7.5M12 15V3" /></svg>
                <?php elseif ($tab['icon'] === 'support'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                <?php endif; ?>
                <?= $tab['label'] ?>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </nav>
      </aside>

      <!-- Main Content -->
      <main class="main-content">
        <div class="content-card animate-fade">
          <div class="top-actions">
            <a href="home.php" class="back-link">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
              Voltar
            </a>
          </div>

          <?php if ($activeTab === 'visao'): ?>
          <!-- TAB: Visão Geral -->
          <h2 class="section-title">Migração de Assinaturas Microsoft CSP (NCE)</h2>
          
          <p class="lead-text">
            Fluxo operacional para <strong>Parceiros Tier 1 (T1)</strong> migrando assinaturas para o modelo <strong>New Commerce Experience</strong>.
          </p>

          <!-- Executive Journey Timeline - Horizontal -->
          <div class="journey-section">
            <div class="journey-header">
              <h3>Jornada de Migração</h3>
              <div class="journey-legend">
                <div class="legend-item"><span class="legend-dot completed"></span> Concluído</div>
                <div class="legend-item"><span class="legend-dot current"></span> Em andamento</div>
                <div class="legend-item"><span class="legend-dot pending"></span> Pendente</div>
              </div>
            </div>
            
            <div class="journey-timeline" data-journey-key="migracao-visao">
              <div class="journey-line">
                <div class="journey-line-fill"></div>
              </div>
              <div class="journey-steps-wrapper">
                <!-- Step 1 -->
                <div class="journey-step current">
                  <div class="step-marker">
                    <div class="step-number">1</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Comercial</span>
                    <h4 class="step-title">Benefícios TD SYNNEX</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">1-2 dias</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Comercial</span>
                    <h4 class="step-title">Benefícios TD SYNNEX</h4>
                    <p class="step-description">Apresentação das vantagens competitivas de migrar para o modelo Tier 2 com a TD SYNNEX como distribuidor.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">1-2 dias</span>
                    </div>
                  </div>
                </div>

                <!-- Step 2 -->
                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">2</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Discovery</span>
                    <h4 class="step-title">Envio da ReconFile</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro T1</span>
                      <span class="meta-item">1 dia</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Discovery</span>
                    <h4 class="step-title">Envio da ReconFile</h4>
                    <p class="step-description">Parceiro compartilha o arquivo de reconciliação (ReconFile).</p>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro T1</span>
                      <span class="meta-item">1 dia</span>
                    </div>
                  </div>
                </div>

                <!-- Step 3 -->
                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">3</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Análise</span>
                    <h4 class="step-title">Análise de Custos</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">2-3 dias</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Análise</span>
                    <h4 class="step-title">Análise de Custos</h4>
                    <p class="step-description">TD SYNNEX avaliará a operação atual e preparará uma proposta financeira.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">2-3 dias</span>
                    </div>
                  </div>
                </div>

                <!-- Step 4 -->
                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">4</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Proposta</span>
                    <h4 class="step-title">Proposta Comercial</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">1-2 dias</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Proposta</span>
                    <h4 class="step-title">Proposta Comercial</h4>
                    <p class="step-description">Apresentação da proposta comercial ao parceiro.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">1-2 dias</span>
                    </div>
                  </div>
                </div>

                <!-- Step 5 -->
                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">5</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Aceite</span>
                    <h4 class="step-title">Aceite do Parceiro</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro T1</span>
                      <span class="meta-item">-</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Aceite</span>
                    <h4 class="step-title">Aceite do Parceiro</h4>
                    <p class="step-description">Aceite do parceiro na proposta da TD SYNNEX.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro T1</span>
                      <span class="meta-item">-</span>
                    </div>
                  </div>
                </div>

                <!-- Step 6 -->
                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">6</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Onboarding</span>
                    <h4 class="step-title">Associação e Cadastro</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro + TD SYNNEX</span>
                      <span class="meta-item">1-2 dias</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Onboarding</span>
                    <h4 class="step-title">Associação e Cadastro</h4>
                    <p class="step-description">Associação de revendedor indireto + cadastro dos clientes e apresentação oficial da plataforma.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro + TD SYNNEX</span>
                      <span class="meta-item">1-2 dias</span>
                    </div>
                  </div>
                </div>

                <!-- Step 7 -->
                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">7</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Comunicação</span>
                    <h4 class="step-title">Associação dos clientes</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro + Cliente</span>
                      <span class="meta-item">3-5 dias</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Comunicação</span>
                    <h4 class="step-title">Associação dos clientes</h4>
                    <p class="step-description">Associação dos tenants dos clientes e aceite GDAP.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro + Cliente</span>
                      <span class="meta-item">3-5 dias</span>
                    </div>
                  </div>
                </div>

                <!-- Step 8 -->
                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">8</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Migração</span>
                    <h4 class="step-title">Solicitação de Transferência</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">-</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Migração</span>
                    <h4 class="step-title">Solicitação de Transferência</h4>
                    <p class="step-description">Abertura das solicitações de transferência.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">-</span>
                    </div>
                  </div>
                </div>

                <!-- Step 9 -->
                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">9</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Migração</span>
                    <h4 class="step-title">Aceite das Transferências</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">PARCEIRO T1</span>
                      <span class="meta-item">-</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Migração</span>
                    <h4 class="step-title">Aceite das Transferências</h4>
                    <p class="step-description">Aceite das solicitações de transferência no Partner Center.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">PARCEIRO T1</span>
                      <span class="meta-item">-</span>
                    </div>
                  </div>
                </div>

                <!-- Step 10 -->
                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">10</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Operação</span>
                    <h4 class="step-title">Go-Live</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">TDS + Parceiro</span>
                      <span class="meta-item">Contínuo</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Operação</span>
                    <h4 class="step-title">Go-Live</h4>
                    <p class="step-description">Entrada em operação contínua.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">TDS + Parceiro</span>
                      <span class="meta-item">Contínuo</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="highlight-box">
            <h5>Importante</h5>
            <p>A transferência NCE <strong>não gera downtime</strong> (queda de serviço) para o usuário final.</p>
          </div>

          <?php elseif ($activeTab === 'fases'): ?>
          <!-- TAB: Fases da Migração -->
          <h2 class="section-title">Fases da Migração</h2>
          
          <p class="lead-text">
            O processo de migração está dividido em <strong>4 fases principais</strong>. Siga cada etapa na ordem para garantir uma transição bem-sucedida.
          </p>

          <!-- Fase 1 -->
          <div class="phase-card">
            <div class="phase-header">
              <span class="phase-number">1</span>
              <span class="phase-title">Onboarding e Parceria</span>
            </div>
            <div class="phase-body">
              <div class="phase-section">
                <h5>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>
                  Relacionamento Microsoft
                </h5>
                <ul>
                  <li>Associação obrigatória do PartnerID do Parceiro T1 ao Distribuidor</li>
                  <li>Aceite anual do Microsoft Partner Agreement (MPA)</li>
                </ul>
              </div>
              <div class="phase-section">
                <h5>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" /></svg>
                  Acesso à Plataforma
                </h5>
                <ul>
                  <li>Configuração de credenciais no portal do Distribuidor para gestão de faturamento</li>
                </ul>
              </div>
              <div class="phase-section">
                <h5>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                  Cadastro de Clientes
                </h5>
                <ul>
                  <li>Validação de CNPJ e contatos de cobrança</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Fase 2 -->
          <div class="phase-card">
            <div class="phase-header">
              <span class="phase-number">2</span>
              <span class="phase-title">Governança (GDAP)</span>
            </div>
            <div class="phase-body">
              <div class="highlight-box" style="margin-top: 0; margin-bottom: 1rem;">
                <h5>Ponto Crítico</h5>
                <p>Substituição do antigo modelo DAP pelo Relacionamento Granular (GDAP).</p>
              </div>
              <div class="phase-section">
                <h5>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                  O Convite
                </h5>
                <ul>
                  <li>Envio de link de autorização ao cliente com tempo determinado (máx. 2 anos)</li>
                </ul>
              </div>
              <div class="phase-section">
                <h5>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                  Segurança
                </h5>
                <ul>
                  <li>Definição de papéis específicos (ex: Administrador de Dynamics 365, Suporte de Nível 1)</li>
                </ul>
              </div>
              <div class="phase-section">
                <h5>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                  Aprovação
                </h5>
                <ul>
                  <li>O Global Admin do cliente deve aprovar formalmente no Tenant</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Fase 3 -->
          <div class="phase-card">
            <div class="phase-header">
              <span class="phase-number">3</span>
              <span class="phase-title">Transferência NCE (O Coração do Processo)</span>
            </div>
            <div class="phase-body">
              <div class="phase-section">
                <h5>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>
                  Início da Transferência
                </h5>
                <ul>
                  <li>O Distribuidor solicita a migração via Partner Center</li>
                </ul>
              </div>
              <div class="phase-section">
                <h5>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" /></svg>
                  Assinaturas Elegíveis
                </h5>
                <ul>
                  <li>Apenas SKUs em New Commerce Experience (NCE)</li>
                </ul>
              </div>
              <div class="phase-section">
                <h5>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                  Manutenção de Termos
                </h5>
                <ul>
                  <li>O compromisso (mensal/anual) é mantido</li>
                  <li>A data de renovação original é preservada para evitar quebras de contrato</li>
                </ul>
              </div>
              <div class="phase-section">
                <h5>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                  Aprovação
                </h5>
                <ul>
                  <li>Notificação via Partner Center para aceite do Parceiro T1 e/ou Cliente</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Fase 4 -->
          <div class="phase-card">
            <div class="phase-header">
              <span class="phase-number">4</span>
              <span class="phase-title">Sincronização e Faturamento</span>
            </div>
            <div class="phase-body">
              <div class="phase-section">
                <h5>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                  Provisionamento
                </h5>
                <ul>
                  <li>Sincronização automática das licenças migradas com o painel do Distribuidor</li>
                </ul>
              </div>
              <div class="phase-section">
                <h5>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                  Configuração Comercial
                </h5>
                <ul>
                  <li>Aplicação de margens (Markups)</li>
                  <li>Definição de ciclos de faturamento (Mensal vs. Anual)</li>
                </ul>
              </div>
              <div class="phase-section">
                <h5>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                  Validação de Inventário
                </h5>
                <ul>
                  <li>Checagem de seats (quantidade) e SKUs ativos</li>
                </ul>
              </div>
              <div class="info-box success">
                <h5>Resultado Esperado</h5>
                <p>Licenças visíveis no Partner Center com regras de faturamento configuradas e cliente operando normalmente.</p>
              </div>
            </div>
          </div>

          <?php elseif ($activeTab === 'checklist'): ?>
          <!-- TAB: Checklist -->
          <h2 class="section-title">Checklist</h2>
          
          <p class="lead-text">
            Acompanhe cada etapa da migração e marque os itens conforme forem concluídos.
          </p>

          <div class="journey-section">
            <div class="journey-header">
              <h3>Checklist por Etapa</h3>
              <div class="journey-legend">
                <div class="legend-item"><span class="legend-dot completed"></span> Concluído</div>
                <div class="legend-item"><span class="legend-dot current"></span> Em andamento</div>
                <div class="legend-item"><span class="legend-dot pending"></span> Pendente</div>
              </div>
            </div>

            <div class="journey-timeline" data-journey-key="migracao-checklist">
              <div class="journey-line">
                <div class="journey-line-fill"></div>
              </div>
              <div class="journey-steps-wrapper">
                <div class="journey-step current">
                  <div class="step-marker">
                    <div class="step-number">1</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Comercial</span>
                    <h4 class="step-title">Benefícios TD SYNNEX</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">1-2 dias</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Comercial</span>
                    <h4 class="step-title">Benefícios TD SYNNEX</h4>
                    <p class="step-description">Apresentação da proposta de valor e alinhamento inicial da migração T1 para T2.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">1-2 dias</span>
                    </div>
                    <div class="step-deliverables">
                      <h5>Checklist da Etapa</h5>
                      <ul>
                        <li>Apresentar os benefícios comerciais da TD SYNNEX</li>
                        <li>Validar o interesse do parceiro na migração</li>
                        <li>Confirmar responsáveis comercial e técnico</li>
                      </ul>
                    </div>
                  </div>
                </div>

                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">2</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Discovery</span>
                    <h4 class="step-title">Envio da ReconFile</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro T1</span>
                      <span class="meta-item">1 dia</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Discovery</span>
                    <h4 class="step-title">Envio da ReconFile</h4>
                    <p class="step-description">Recebimento da base atual do parceiro para iniciar a avaliação da migração.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro T1</span>
                      <span class="meta-item">1 dia</span>
                    </div>
                    <div class="step-deliverables">
                      <h5>Checklist da Etapa</h5>
                      <ul>
                        <li>Solicitar a Recon File atualizada</li>
                        <li>Validar se o arquivo está completo</li>
                        <li>Confirmar o período de referência da base</li>
                      </ul>
                    </div>
                  </div>
                </div>

                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">3</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Análise</span>
                    <h4 class="step-title">Análise de Custos</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">2-3 dias</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Análise</span>
                    <h4 class="step-title">Análise de Custos</h4>
                    <p class="step-description">Consolidação financeira da base recebida para suportar a proposta comercial.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">2-3 dias</span>
                    </div>
                    <div class="step-deliverables">
                      <h5>Checklist da Etapa</h5>
                      <ul>
                        <li>Revisar SKUs, quantidades e vigências</li>
                        <li>Comparar custo atual e custo projetado</li>
                        <li>Registrar observações e variações relevantes</li>
                      </ul>
                    </div>
                  </div>
                </div>

                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">4</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Proposta</span>
                    <h4 class="step-title">Proposta Comercial</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">1-2 dias</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Proposta</span>
                    <h4 class="step-title">Proposta Comercial</h4>
                    <p class="step-description">Apresentação da proposta com premissas, cronograma e próximos passos.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">1-2 dias</span>
                    </div>
                    <div class="step-deliverables">
                      <h5>Checklist da Etapa</h5>
                      <ul>
                        <li>Montar a proposta comercial consolidada</li>
                        <li>Revisar condições comerciais e escopo</li>
                        <li>Apresentar cronograma macro da migração</li>
                      </ul>
                    </div>
                  </div>
                </div>

                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">5</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Aceite</span>
                    <h4 class="step-title">Aceite do Parceiro</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro T1</span>
                      <span class="meta-item">-</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Aceite</span>
                    <h4 class="step-title">Aceite do Parceiro</h4>
                    <p class="step-description">Formalização da aprovação do parceiro para iniciar a execução operacional.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro T1</span>
                      <span class="meta-item">-</span>
                    </div>
                    <div class="step-deliverables">
                      <h5>Checklist da Etapa</h5>
                      <ul>
                        <li>Confirmar aceite formal da proposta</li>
                        <li>Registrar aprovação interna do parceiro</li>
                        <li>Alinhar responsáveis da etapa de onboarding</li>
                      </ul>
                    </div>
                  </div>
                </div>

                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">6</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Onboarding</span>
                    <h4 class="step-title">Associação e Cadastro</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro + TD SYNNEX</span>
                      <span class="meta-item">1-2 dias</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Onboarding</span>
                    <h4 class="step-title">Associação e Cadastro</h4>
                    <p class="step-description">Preparação operacional para iniciar o relacionamento no modelo Tier 2.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro + TD SYNNEX</span>
                      <span class="meta-item">1-2 dias</span>
                    </div>
                    <div class="step-deliverables">
                      <h5>Checklist da Etapa</h5>
                      <ul>
                        <li>Associar o revendedor indireto</li>
                        <li>Validar cadastro dos clientes</li>
                        <li>Confirmar acessos e dados operacionais</li>
                      </ul>
                    </div>
                  </div>
                </div>

                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">7</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Comunicação</span>
                    <h4 class="step-title">Associação dos clientes</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro + Cliente</span>
                      <span class="meta-item">3-5 dias</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Comunicação</span>
                    <h4 class="step-title">Associação dos clientes</h4>
                    <p class="step-description">Conclusão da associação dos tenants e aprovações necessárias para avançar para a transferência.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro + Cliente</span>
                      <span class="meta-item">3-5 dias</span>
                    </div>
                    <div class="step-deliverables">
                      <h5>Checklist da Etapa</h5>
                      <ul>
                        <li>Associar os tenants dos clientes</li>
                        <li>Validar aprovações GDAP quando aplicável</li>
                        <li>Confirmar prontidão para transferência</li>
                      </ul>
                    </div>
                  </div>
                </div>

                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">8</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Migração</span>
                    <h4 class="step-title">Solicitação de Transferência</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">-</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Migração</span>
                    <h4 class="step-title">Solicitação de Transferência</h4>
                    <p class="step-description">Abertura e acompanhamento das solicitações de transferência no fluxo NCE.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">TD SYNNEX</span>
                      <span class="meta-item">-</span>
                    </div>
                    <div class="step-deliverables">
                      <h5>Checklist da Etapa</h5>
                      <ul>
                        <li>Selecionar clientes elegíveis para migração</li>
                        <li>Abrir as solicitações de transferência</li>
                        <li>Registrar o acompanhamento das solicitações</li>
                      </ul>
                    </div>
                  </div>
                </div>

                <div class="journey-step">
                  <div class="step-marker">
                    <div class="step-number">9</div>
                  </div>
                  <div class="step-card">
                    <span class="step-phase">Migração</span>
                    <h4 class="step-title">Aceite das Transferências</h4>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro T1</span>
                      <span class="meta-item">-</span>
                    </div>
                  </div>
                  <div class="step-card-expanded">
                    <span class="step-phase">Migração</span>
                    <h4 class="step-title">Aceite das Transferências</h4>
                    <p class="step-description">Confirmação final das transferências abertas no Partner Center.</p>
                    <div class="step-meta">
                      <span class="meta-item owner">Parceiro T1</span>
                      <span class="meta-item">-</span>
                    </div>
                    <div class="step-deliverables">
                      <h5>Checklist da Etapa</h5>
                      <ul>
                        <li>Monitorar notificações de transferência</li>
                        <li>Executar o aceite no Partner Center</li>
                        <li>Confirmar a conclusão da migração</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="tips-box">
            <h4>
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:20px;height:20px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" /></svg>
              Dicas Importantes
            </h4>
            <ul>
              <li>Use ícones da Microsoft (Azure, M365) para ilustrar os SKUs nas comunicações</li>
              <li>Reforce que a transferência NCE não gera "downtime" para o usuário final</li>
              <li>Destaque a janela de 7 dias do NCE para qualquer ajuste pós-migração</li>
            </ul>
          </div>

          <?php elseif ($activeTab === 'precificacao'): ?>
          <!-- TAB: Precificação CSP -->
          <h2 class="section-title">Precificação CSP</h2>

          <div class="pricing-head">
            <div class="pricing-head-icon">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m3-9.75H9.75a2.25 2.25 0 0 0 0 4.5h4.5a2.25 2.25 0 0 1 0 4.5H9m3 0v2.25m0-15V3.75" /></svg>
            </div>
            <div>
              <h3>Análise de Precificação CSP</h3>
              <p>Upload da Lista de Preço TD SYNNEX e da Recon File do Parceiro</p>
            </div>
          </div>

          <?php if ($uploadError): ?>
          <div class="highlight-box" style="margin-top: 0; margin-bottom: 1rem; border-color: var(--red-600); border-left-color: var(--red-600); background: var(--red-50);">
            <h5 style="color: var(--red-600);">Erro no Upload</h5>
            <p><?= htmlspecialchars($uploadError) ?></p>
          </div>
          <?php endif; ?>

          <?php if ($uploadSuccess): ?>
          <div class="info-box success" style="margin-top: 0; margin-bottom: 1rem;">
            <h5>Upload Concluido</h5>
            <p><?= htmlspecialchars($uploadSuccess) ?></p>
          </div>
          <?php endif; ?>

          <div class="pricing-grid">
            <div class="pricing-card">
              <div class="card-head">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-16.5-6L12 3m0 0 7.5 7.5M12 3v13.5" /></svg>
                Upload dos Arquivos
              </div>
              <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="action" value="upload_precificacao">

                  <div class="upload-zone-mini">
                    <label for="tdPriceList">1. Lista de Preço TD SYNNEX</label>
                    <small>Formatos: CSV, XLSX, XLS</small>
                    <input id="tdPriceList" type="file" name="tdPriceList" accept=".csv,.xlsx,.xls" required>
                  </div>

                  <div class="upload-zone-mini">
                    <label for="partnerReconFile">2. Recon File do Parceiro</label>
                    <small>Formatos: CSV, XLSX, XLS</small>
                    <input id="partnerReconFile" type="file" name="partnerReconFile" accept=".csv,.xlsx,.xls" required>
                  </div>

                  <button type="submit" class="btn-upload">Enviar Arquivos</button>
                  <div class="upload-help">Limite de 80 MB por arquivo</div>
                </form>
              </div>
            </div>

            <div>
              <div class="pricing-stats">
                <div class="stat-mini">
                  <div class="value">2</div>
                  <div class="label">Arquivos Obrigatórios</div>
                </div>
                <div class="stat-mini">
                  <div class="value">80 MB</div>
                  <div class="label">Limite por Arquivo</div>
                </div>
                <div class="stat-mini">
                  <div class="value">CSV/XLSX/XLS</div>
                  <div class="label">Formatos Permitidos</div>
                </div>
              </div>

              <?php if (!empty($precificacaoUploads['files'])): ?>
              <div class="info-box" style="margin-top: 0;">
                <h5>Último Upload Registrado</h5>
                <ul>
                  <li><strong>Data:</strong> <?= htmlspecialchars((string)$precificacaoUploads['uploadedAt']) ?></li>
                  <?php foreach ($precificacaoUploads['files'] as $fileData): ?>
                  <li>
                    <strong><?= htmlspecialchars((string)$fileData['label']) ?>:</strong>
                    <?= htmlspecialchars((string)$fileData['originalName']) ?>
                  </li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <?php else: ?>
              <div class="info-box" style="margin-top: 0;">
                <h5>Nenhum Upload Realizado</h5>
                <p>Envie os dois arquivos para registrar a base de análise de precificação CSP.</p>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="highlight-box" style="margin-top: 2rem;">
            <h5>Recomendação</h5>
            <p>Conduza a definição de preços por cliente com base em cenário de consumo, prazo e estratégia de renovação para garantir previsibilidade e margem saudável.</p>
          </div>

          <?php elseif ($activeTab === 'downloads'): ?>
          <!-- TAB: Download de Materiais -->
          <h2 class="section-title">Download de Materiais</h2>

          <p class="lead-text">
            Baixe materiais organizados conforme cada etapa da Jornada de Migração, seguindo a sequencia operacional do processo T1 para T2.
          </p>

          <h3 style="font-size: 1.05rem; font-weight: 600; color: var(--teal-dark); margin: 1.5rem 0 1rem;">Materiais por Etapa da Jornada</h3>

          <div class="api-grid">
            <div class="api-card">
              <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15A2.25 2.25 0 0 0 6.75 21.75h10.5A2.25 2.25 0 0 0 19.5 19.5V14.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 2.25V6a2.25 2.25 0 0 0 2.25 2.25h3.75M12 18.75V12m0 6.75-2.25-2.25M12 18.75l2.25-2.25" /></svg>
                Etapa 1: Comercial
              </h4>
              <ul>
                <li>Beneficios TD SYNNEX</li>
                <li>Responsavel: TD SYNNEX</li>
                <li>Prazo: 1-2 dias</li>
              </ul>
              <p><a href="assets/materials/jornada-01-beneficios-td-synnex.txt" download style="color: var(--teal-dark); font-weight: 600; text-decoration: none;">Baixar material da etapa</a></p>
            </div>

            <div class="api-card">
              <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15A2.25 2.25 0 0 0 6.75 21.75h10.5A2.25 2.25 0 0 0 19.5 19.5V14.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75V12m0 6.75-2.25-2.25M12 18.75l2.25-2.25" /></svg>
                Etapa 2: Discovery
              </h4>
              <ul>
                <li>Envio da ReconFile</li>
                <li>Responsavel: Parceiro T1</li>
                <li>Prazo: 1 dia</li>
              </ul>
              <p><a href="assets/materials/jornada-02-envio-reconfile.txt" download style="color: var(--teal-dark); font-weight: 600; text-decoration: none;">Baixar material da etapa</a></p>
            </div>

            <div class="api-card">
              <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15A2.25 2.25 0 0 0 6.75 21.75h10.5A2.25 2.25 0 0 0 19.5 19.5V14.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75V12m0 6.75-2.25-2.25M12 18.75l2.25-2.25" /></svg>
                Etapa 3: Analise
              </h4>
              <ul>
                <li>Analise de Custos</li>
                <li>Responsavel: TD SYNNEX</li>
                <li>Prazo: 2-3 dias</li>
                <li>Template para preenchimento da analise financeira</li>
              </ul>
              <p><a href="assets/materials/jornada-03-analise-de-custos-template.csv" download style="color: var(--teal-dark); font-weight: 600; text-decoration: none;">Baixar template de analise CSV</a></p>
            </div>

            <div class="api-card">
              <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15A2.25 2.25 0 0 0 6.75 21.75h10.5A2.25 2.25 0 0 0 19.5 19.5V14.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75V12m0 6.75-2.25-2.25M12 18.75l2.25-2.25" /></svg>
                Etapa 4: Proposta
              </h4>
              <ul>
                <li>Proposta Comercial</li>
                <li>Responsavel: TD SYNNEX</li>
                <li>Prazo: 1-2 dias</li>
              </ul>
              <p><a href="assets/materials/jornada-04-proposta-comercial.txt" download style="color: var(--teal-dark); font-weight: 600; text-decoration: none;">Baixar material da etapa</a></p>
            </div>

            <div class="api-card">
              <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15A2.25 2.25 0 0 0 6.75 21.75h10.5A2.25 2.25 0 0 0 19.5 19.5V14.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75V12m0 6.75-2.25-2.25M12 18.75l2.25-2.25" /></svg>
                Etapa 5: Aceite
              </h4>
              <ul>
                <li>Aceite do Parceiro</li>
                <li>Responsavel: Parceiro T1</li>
                <li>Prazo: -</li>
              </ul>
              <p><a href="assets/materials/jornada-05-aceite-do-parceiro.txt" download style="color: var(--teal-dark); font-weight: 600; text-decoration: none;">Baixar material da etapa</a></p>
            </div>

            <div class="api-card">
              <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15A2.25 2.25 0 0 0 6.75 21.75h10.5A2.25 2.25 0 0 0 19.5 19.5V14.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75V12m0 6.75-2.25-2.25M12 18.75l2.25-2.25" /></svg>
                Etapa 6: Onboarding
              </h4>
              <ul>
                <li>Associacao e Cadastro</li>
                <li>Responsavel: Parceiro + TD SYNNEX</li>
                <li>Prazo: 1-2 dias</li>
              </ul>
              <p><a href="assets/materials/jornada-06-associacao-e-cadastro.txt" download style="color: var(--teal-dark); font-weight: 600; text-decoration: none;">Baixar material da etapa</a></p>
            </div>

            <div class="api-card">
              <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15A2.25 2.25 0 0 0 6.75 21.75h10.5A2.25 2.25 0 0 0 19.5 19.5V14.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75V12m0 6.75-2.25-2.25M12 18.75l2.25-2.25" /></svg>
                Etapa 7: Comunicacao
              </h4>
              <ul>
                <li>Associacao dos clientes</li>
                <li>Responsavel: Parceiro + Cliente</li>
                <li>Prazo: 3-5 dias</li>
              </ul>
              <p><a href="assets/materials/jornada-07-associacao-dos-clientes.txt" download style="color: var(--teal-dark); font-weight: 600; text-decoration: none;">Baixar material da etapa</a></p>
            </div>

            <div class="api-card">
              <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15A2.25 2.25 0 0 0 6.75 21.75h10.5A2.25 2.25 0 0 0 19.5 19.5V14.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75V12m0 6.75-2.25-2.25M12 18.75l2.25-2.25" /></svg>
                Etapa 8: Migracao
              </h4>
              <ul>
                <li>Solicitacao de Transferencia</li>
                <li>Responsavel: TD SYNNEX</li>
                <li>Prazo: -</li>
              </ul>
              <p><a href="assets/materials/jornada-08-solicitacao-de-transferencia.txt" download style="color: var(--teal-dark); font-weight: 600; text-decoration: none;">Baixar material da etapa</a></p>
            </div>

            <div class="api-card">
              <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15A2.25 2.25 0 0 0 6.75 21.75h10.5A2.25 2.25 0 0 0 19.5 19.5V14.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75V12m0 6.75-2.25-2.25M12 18.75l2.25-2.25" /></svg>
                Etapa 9: Migracao
              </h4>
              <ul>
                <li>Aceite das Transferencias</li>
                <li>Responsavel: Parceiro T1</li>
                <li>Prazo: -</li>
              </ul>
              <p><a href="assets/materials/jornada-09-aceite-das-transferencias.txt" download style="color: var(--teal-dark); font-weight: 600; text-decoration: none;">Baixar material da etapa</a></p>
            </div>
          </div>

          <div class="info-box" style="margin-top: 2rem;">
            <h5>Uso Recomendado</h5>
            <p>Utilize os materiais por etapa para acompanhar o fluxo da jornada de migracao do discovery ate o aceite das transferencias.</p>
          </div>

          <?php elseif ($activeTab === 'api'): ?>
          <!-- TAB: Automação via API -->
          <h2 class="section-title">Automação via API</h2>
          
          <p class="lead-text">
            Utilize as APIs do Partner Center para automatizar operações e escalar a gestão de clientes e assinaturas.
          </p>

          <div class="api-grid">
            <div class="api-card">
              <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                Agilidade
              </h4>
              <ul>
                <li>Criação de clientes sem intervenção manual</li>
                <li>Atualização de dados em tempo real</li>
                <li>Integração com sistemas internos</li>
              </ul>
            </div>
            
            <div class="api-card">
              <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                Gestão de Ciclo de Vida
              </h4>
              <ul>
                <li>Upgrades de licença imediatos</li>
                <li>Suspensão automatizada por regras financeiras</li>
                <li>Reativação programática</li>
              </ul>
            </div>
            
            <div class="api-card">
              <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                Reporting
              </h4>
              <ul>
                <li>Extração de relatórios de consumo</li>
                <li>Integração com BI e Dashboards</li>
                <li>Métricas de uso em tempo real</li>
              </ul>
            </div>
            
            <div class="api-card">
              <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                Segurança
              </h4>
              <ul>
                <li>Gestão de GDAP via API</li>
                <li>Auditoria de acessos</li>
                <li>Compliance automatizado</li>
              </ul>
            </div>
          </div>

          <div class="info-box" style="margin-top: 2rem;">
            <h5>Documentação</h5>
            <p>Acesse a documentação oficial do Partner Center API em <a href="https://docs.microsoft.com/partner-center/develop/" target="_blank" style="color: var(--teal-dark); font-weight: 600;">docs.microsoft.com/partner-center/develop</a></p>
          </div>

          <?php elseif ($activeTab === 'suporte'): ?>
          <!-- TAB: Suporte -->
          <h2 class="section-title">Monitoramento e Suporte</h2>
          
          <p class="lead-text">
            Informações sobre SLA de migração e tratativa de falhas durante o processo.
          </p>

          <div class="support-grid">
            <div class="support-card">
              <span class="badge">SLA</span>
              <h5>Prazo de Migração</h5>
              <p>Prazo estimado de conclusão em até <strong>72 horas</strong> após a solicitação via Partner Center.</p>
            </div>
            
            <div class="support-card">
              <span class="badge" style="background: var(--orange-500);">Status</span>
              <h5>Partially Complete</h5>
              <p>Em caso de status "Partially Complete", o sistema executa re-execução automática do processo.</p>
            </div>
            
            <div class="support-card">
              <span class="badge" style="background: var(--red-600);">Nível 2</span>
              <h5>Erro de Provisionamento</h5>
              <p>Acionamento do time técnico do Distribuidor para resolução de erros de provisionamento.</p>
            </div>
            
            <div class="support-card">
              <span class="badge" style="background: var(--green-600);">7 Dias</span>
              <h5>Janela de Ajustes</h5>
              <p>O NCE oferece uma janela de 7 dias para qualquer ajuste pós-migração sem penalidades.</p>
            </div>
          </div>

          <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--teal-dark); margin: 2rem 0 1rem;">Fluxo de Suporte</h3>
          
          <div class="timeline">
            <div class="timeline-item">
              <h5>1. Monitoramento</h5>
              <p>Acompanhe o status da migração no Partner Center em tempo real.</p>
            </div>
            <div class="timeline-item">
              <h5>2. Identificação de Falhas</h5>
              <p>Verifique logs e notificações para identificar possíveis erros.</p>
            </div>
            <div class="timeline-item">
              <h5>3. Re-execução Automática</h5>
              <p>O sistema tenta re-executar automaticamente em caso de falhas parciais.</p>
            </div>
            <div class="timeline-item">
              <h5>4. Escalação</h5>
              <p>Se o problema persistir, acione o Suporte Nível 2 do Distribuidor.</p>
            </div>
            <div class="timeline-item">
              <h5>5. Resolução</h5>
              <p>Time técnico analisa e resolve o problema de provisionamento.</p>
            </div>
          </div>

          <div class="highlight-box" style="margin-top: 2rem;">
            <h5>Contato do Distribuidor</h5>
            <p>Para suporte técnico durante a migração, entre em contato com a equipe TD SYNNEX através dos canais oficiais.</p>
          </div>

          <?php endif; ?>
        </div>
      </main>
    </div>
  </div>

  <script>
    // Mobile menu toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const menuIcon = document.getElementById('menuIcon');
    const closeIcon = document.getElementById('closeIcon');

    menuToggle?.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      const isOpen = sidebar.classList.contains('open');
      menuIcon.style.display = isOpen ? 'none' : 'block';
      closeIcon.style.display = isOpen ? 'block' : 'none';
    });

    // Checklist functionality
    function toggleCheck(item) {
      item.classList.toggle('done');
      updateProgress();
      saveChecklist();
    }

    function updateProgress() {
      const items = document.querySelectorAll('.checklist-item');
      const done = document.querySelectorAll('.checklist-item.done').length;
      const total = items.length;
      const percent = total > 0 ? Math.round((done / total) * 100) : 0;
      
      const fill = document.getElementById('progressFill');
      const text = document.getElementById('progressPercent');
      
      if (fill) fill.style.width = percent + '%';
      if (text) text.textContent = percent;
    }

    function saveChecklist() {
      const items = document.querySelectorAll('.checklist-item');
      const states = Array.from(items).map(item => item.classList.contains('done'));
      localStorage.setItem('migracao_t1t2_checklist', JSON.stringify(states));
    }

    function loadChecklist() {
      const saved = localStorage.getItem('migracao_t1t2_checklist');
      if (saved) {
        const states = JSON.parse(saved);
        const items = document.querySelectorAll('.checklist-item');
        items.forEach((item, i) => {
          if (states[i]) item.classList.add('done');
        });
        updateProgress();
      }
    }

    // Load checklist on page load
    document.addEventListener('DOMContentLoaded', loadChecklist);

    function initExpandedCardCheckboxes() {
      const checklistTimeline = document.querySelector('.journey-timeline[data-journey-key="migracao-checklist"]');
      if (!checklistTimeline) return;

      const storageKey = 'migracao_checklist_expanded_cards';
      const savedState = JSON.parse(localStorage.getItem(storageKey) || '{}');
      const deliverableLists = checklistTimeline.querySelectorAll('.step-deliverables ul');

      deliverableLists.forEach((list, stepIndex) => {
        const items = Array.from(list.querySelectorAll('li'));

        items.forEach((item, itemIndex) => {
          if (item.querySelector('.step-check-input')) return;

          const text = item.textContent.trim();
          const itemKey = `${stepIndex}-${itemIndex}`;
          const checkboxId = `journey-check-${stepIndex}-${itemIndex}`;
          const label = document.createElement('label');
          const checkbox = document.createElement('input');
          const textSpan = document.createElement('span');

          label.className = 'step-check-item';
          label.setAttribute('for', checkboxId);

          checkbox.type = 'checkbox';
          checkbox.id = checkboxId;
          checkbox.className = 'step-check-input';
          checkbox.checked = Boolean(savedState[itemKey]);

          textSpan.className = 'step-check-text';
          textSpan.textContent = text;

          item.textContent = '';
          label.appendChild(checkbox);
          label.appendChild(textSpan);
          item.appendChild(label);

          if (checkbox.checked) {
            item.classList.add('is-checked');
          }

          checkbox.addEventListener('change', () => {
            item.classList.toggle('is-checked', checkbox.checked);
            savedState[itemKey] = checkbox.checked;
            localStorage.setItem(storageKey, JSON.stringify(savedState));
          });
        });
      });
    }

    // Executive Journey Timeline - Click to progress (Horizontal)
    function initJourneyTimeline() {
      const timelines = document.querySelectorAll('.journey-timeline');

      timelines.forEach((timeline, timelineIndex) => {
        const steps = timeline.querySelectorAll('.journey-step');
        const lineFill = timeline.querySelector('.journey-line-fill');
        const storageKey = timeline.dataset.journeyKey || `journey_step_${timelineIndex}`;

        if (steps.length === 0) return;

        function updateLineFill(currentIndex) {
          if (!lineFill) return;
          const totalSteps = steps.length;
          const percentage = totalSteps > 1 ? (currentIndex / (totalSteps - 1)) * 100 : 0;
          lineFill.style.width = percentage + '%';
        }

        steps.forEach((step, index) => {
          step.style.cursor = 'pointer';
          step.setAttribute('tabindex', '0');

          step.addEventListener('click', () => {
            steps.forEach(s => s.classList.remove('current', 'completed'));

            for (let i = 0; i < index; i++) {
              steps[i].classList.add('completed');
            }

            steps[index].classList.add('current');
            updateLineFill(index);
            localStorage.setItem(storageKey, index);
          });

          step.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              step.click();
            }
          });
        });

        const savedStep = localStorage.getItem(storageKey);
        if (savedStep !== null) {
          const stepIndex = parseInt(savedStep, 10);
          steps.forEach((step, index) => {
            step.classList.remove('current', 'completed');
            if (index < stepIndex) step.classList.add('completed');
            if (index === stepIndex) step.classList.add('current');
          });
          setTimeout(() => updateLineFill(stepIndex), 100);
        } else {
          updateLineFill(0);
        }
      });
    }
    
    document.addEventListener('DOMContentLoaded', initExpandedCardCheckboxes);
    document.addEventListener('DOMContentLoaded', initJourneyTimeline);
  </script>
</body>
</html>
