<?php
// filepath: public/welcome-kit-csp.php
session_start();

// Initialize i18n
require_once __DIR__ . '/../src/Shared/Services/i18n-bootstrap.php';

// Handle active tab
$activeTab = isset($_GET['tab']) ? htmlspecialchars($_GET['tab']) : 'resumo';
$validTabs = ['resumo', 'guia', 'checklist', 'templates', 'anexos'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'resumo';
}

$tabs = [
    ['id' => 'resumo', 'label' => 'Resumo Executivo', 'icon' => 'book'],
    ['id' => 'guia', 'label' => 'Guia Completo', 'icon' => 'document'],
    ['id' => 'checklist', 'label' => 'Checklist', 'icon' => 'check-square'],
    ['id' => 'templates', 'label' => 'Templates', 'icon' => 'message'],
    ['id' => 'anexos', 'label' => 'Anexos', 'icon' => 'paperclip'],
];
?>
<!DOCTYPE html>
<html lang="<?= getHtmlLang() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= __('pages.welcome_kit_csp') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* Reset para evitar conflitos com topbar */
    .page-wrapper {
      margin-top: 0;
    }
    :root {
      --teal: #005758;
      --teal-dark: #003031;
      --teal-light: #007a7b;
      --charcoal: #262626;
      --gray: #737373;
      --light-gray: #f5f5f7;
      --blue: #0078D4;
      --blue-900: #1e3a8a;
      --blue-700: #1d4ed8;
      --blue-600: #2563eb;
      --blue-500: #3b82f6;
      --blue-100: #dbeafe;
      --blue-50: #eff6ff;
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
      --yellow-400: #facc15;
      --yellow-600: #ca8a04;
      --yellow-800: #854d0e;
      --yellow-900: #713f12;
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

    /* ── Layout ──────────────────────────────────────────── */
    .app-container {
      min-height: calc(100vh - 56px);
      display: flex;
      flex-direction: column;
    }
    
    @media (min-width: 768px) {
      .app-container {
        flex-direction: row;
      }
    }

    /* ── Mobile Header ───────────────────────────────────── */
    .mobile-header {
      background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal) 100%);
      color: #fff;
      padding: 1rem 1.25rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 20px rgba(0,87,88,.25);
    }
    
    @media (min-width: 768px) {
      .mobile-header { display: none; }
    }
    
    .mobile-header .brand {
      font-weight: 700;
      font-size: 1.1rem;
      letter-spacing: -.3px;
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
    
    .mobile-header .menu-btn:hover {
      background: rgba(255,255,255,.25);
    }
    
    .mobile-header .menu-btn svg {
      width: 22px;
      height: 22px;
    }

    /* ── Sidebar ─────────────────────────────────────────── */
    .sidebar {
      display: none;
      width: 100%;
      background: #fff;
      flex-shrink: 0;
      transition: all 0.3s ease;
    }
    
    .sidebar.open {
      display: block;
    }
    
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
    
    .sidebar-info strong {
      color: var(--teal-dark);
    }
    
    .sidebar-nav {
      padding: 1.25rem 1rem;
    }
    
    @media (min-width: 768px) {
      .sidebar-nav { margin-top: 0; }
    }
    
    .sidebar-nav ul {
      list-style: none;
    }
    
    .sidebar-nav li {
      margin-bottom: 6px;
    }
    
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
    
    .sidebar-nav a.active svg {
      opacity: 1;
    }

    /* ── Main Content ────────────────────────────────────── */
    .main-content {
      flex: 1;
      padding: 2rem;
      overflow-y: auto;
    }
    
    @media (min-width: 768px) {
      .main-content {
        padding: 2.5rem 3.5rem;
      }
    }
    
    @media (min-width: 1200px) {
      .main-content {
        padding: 3rem 5rem;
      }
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
      .content-card {
        padding: 1.5rem;
        border-radius: 4px;
      }
    }

    /* ── Typography ──────────────────────────────────────── */
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
    
    .callout {
      background: var(--teal-dark);
      padding: 1.15rem 1.35rem;
      border-radius: 4px;
      margin-top: 1.5rem;
    }
    
    .callout p {
      font-weight: 500;
      color: #fff;
      margin: 0;
      font-size: .95rem;
    }
    
    .callout em {
      font-style: normal;
      color: rgba(255,255,255,.85);
    }

    /* ── Step Card ───────────────────────────────────────── */
    .step-card {
      background: #fff;
      border: 1px solid var(--slate-200);
      border-radius: 4px;
      box-shadow: 0 2px 12px rgba(0,0,0,.04);
      padding: 1.5rem;
      margin-bottom: 1.25rem;
      transition: all .2s ease;
      border-left: 4px solid var(--teal);
    }
    
    .step-card:hover {
      box-shadow: 0 8px 25px rgba(0,87,88,.1);
      transform: translateY(-2px);
    }
    
    .step-card h4 {
      font-weight: 700;
      font-size: 1.1rem;
      color: var(--teal-dark);
      margin-bottom: .75rem;
      display: flex;
      align-items: center;
      gap: .65rem;
    }
    
    .step-card h4 .step-num {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      background: var(--teal-dark);
      color: #fff;
      border-radius: 50%;
      font-size: .85rem;
      font-weight: 700;
    }
    
    .step-card .warning {
      font-size: .85rem;
      color: var(--red-600);
      margin-bottom: .75rem;
      padding: .6rem .85rem;
      background: var(--red-50);
      border-radius: 4px;
    }
    
    .step-card ol {
      padding-left: 1.35rem;
      color: var(--slate-600);
    }
    
    .step-card ol li {
      margin-bottom: .5rem;
      line-height: 1.55;
      font-size: .88rem;
    }
    
    .step-card ol li.success {
      color: var(--green-700);
      font-weight: 500;
      background: var(--green-50);
      margin-left: -1.35rem;
      padding: .55rem .85rem .55rem 1.5rem;
      border-radius: 4px;
      margin-top: .65rem;
    }

    /* ── Section Header ──────────────────────────────────── */
    .section-header {
      display: flex;
      align-items: center;
      gap: .75rem;
      margin-bottom: 1rem;
      margin-top: 2rem;
      padding-bottom: .65rem;
      border-bottom: 1px solid var(--slate-200);
    }
    
    .section-header:first-child {
      margin-top: 0;
    }
    
    .section-header .badge {
      background: var(--teal-dark);
      color: #fff;
      padding: .25rem .65rem;
      border-radius: 4px;
      font-size: .75rem;
      font-weight: 600;
    }
    
    .section-header h3 {
      font-size: 1.1rem;
      font-weight: 600;
      color: var(--teal-dark);
      letter-spacing: -.2px;
    }

    /* ── Scenario Box ────────────────────────────────────── */
    .scenario-box {
      background: #fff;
      padding: 1.15rem;
      border-radius: 4px;
      border: 1px solid var(--slate-200);
      margin-bottom: .85rem;
      transition: all .2s ease;
    }
    
    .scenario-box:hover {
      border-color: var(--teal);
    }
    
    .scenario-box h4 {
      font-weight: 600;
      color: var(--teal-dark);
      margin-bottom: .5rem;
      font-size: .9rem;
    }
    
    .scenario-box ol {
      padding-left: 1.35rem;
      color: var(--slate-600);
      font-size: .85rem;
    }
    
    .scenario-box ol li {
      margin-bottom: .35rem;
      line-height: 1.55;
    }

    /* ── Troubleshooting Table ───────────────────────────── */
    .trouble-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      background: #fff;
      border-radius: 4px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,.06);
      margin-bottom: 1.75rem;
      border: 1px solid var(--slate-200);
    }
    
    .trouble-table thead {
      background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal) 100%);
    }
    
    .trouble-table th {
      text-align: left;
      padding: 1rem 1.15rem;
      font-size: .85rem;
      font-weight: 600;
      color: #fff;
      text-transform: uppercase;
      letter-spacing: .5px;
    }
    
    .trouble-table td {
      padding: 1rem 1.15rem;
      font-size: .9rem;
      color: var(--slate-700);
      border-bottom: 1px solid var(--slate-100);
      vertical-align: top;
    }
    
    .trouble-table tr:last-child td {
      border-bottom: none;
    }
    
    .trouble-table tr:hover td {
      background: var(--slate-50);
    }
    
    .trouble-table .error-lock {
      color: var(--red-600);
      font-weight: 600;
    }
    
    .trouble-table .error-hold {
      color: var(--orange-600);
      font-weight: 600;
    }
    
    .trouble-table code {
      background: var(--slate-100);
      padding: 2px 6px;
      border-radius: 2px;
      font-size: .8rem;
      font-weight: 600;
      color: var(--slate-700);
    }

    /* ── Warning Box ─────────────────────────────────────── */
    .warning-box {
      background: var(--yellow-50);
      padding: 1.15rem 1.35rem;
      border-radius: 4px;
      margin-bottom: 1.5rem;
      border: 1px solid var(--yellow-100);
    }
    
    .warning-box h4 {
      display: flex;
      align-items: center;
      gap: .5rem;
      color: var(--yellow-800);
      font-weight: 600;
      margin-bottom: .5rem;
      font-size: .95rem;
    }
    
    .warning-box h4 svg {
      width: 20px;
      height: 20px;
      color: var(--yellow-600);
    }
    
    .warning-box p {
      font-size: .88rem;
      color: var(--yellow-900);
      margin-bottom: .5rem;
      line-height: 1.55;
    }
    
    .warning-box ul {
      font-size: .88rem;
      color: var(--yellow-900);
      padding-left: 1.35rem;
    }
    
    .warning-box ul li {
      margin-bottom: .3rem;
      line-height: 1.5;
    }

    /* ── Support Cards ───────────────────────────────────── */
    .support-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1.25rem;
      margin-bottom: 1.75rem;
    }
    
    @media (min-width: 768px) {
      .support-grid {
        grid-template-columns: 1fr 1fr;
      }
    }
    
    .support-card {
      background: var(--slate-50);
      border: 1px solid var(--slate-200);
      border-radius: 4px;
      padding: 1.25rem;
      transition: all .2s ease;
    }
    
    .support-card:hover {
      box-shadow: 0 8px 25px rgba(0,87,88,.06);
    }
    
    .support-card h5 {
      font-weight: 600;
      color: var(--teal-dark);
      margin-bottom: .3rem;
      font-size: .95rem;
    }
    
    .support-card .desc {
      font-size: .75rem;
      color: var(--slate-500);
      margin-bottom: .75rem;
    }
    
    .support-card ul {
      list-style: none;
      font-size: .85rem;
      color: var(--slate-600);
    }
    
    .support-card ul li {
      margin-bottom: .35rem;
      padding-left: 1.15rem;
      position: relative;
    }
    
    .support-card ul li::before {
      content: '→';
      position: absolute;
      left: 0;
      color: var(--teal);
      font-weight: 600;
    }

    /* ── Glossary ────────────────────────────────────────── */
    .glossary-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
    }
    
    @media (min-width: 768px) {
      .glossary-grid {
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
      }
    }
    
    .glossary-item {
      background: var(--slate-50);
      padding: .95rem 1.15rem;
      border-radius: 4px;
      border: 1px solid var(--slate-200);
      transition: all .2s ease;
    }
    
    .glossary-item:hover {
      border-color: var(--teal);
    }
    
    .glossary-item dt {
      font-weight: 600;
      color: var(--teal-dark);
      font-size: .9rem;
      margin-bottom: .2rem;
    }
    
    .glossary-item dd {
      color: var(--slate-600);
      font-size: .82rem;
      line-height: 1.5;
    }

    /* ── Checklist ───────────────────────────────────────── */
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
    
    .checklist-item:last-child {
      border-bottom: none;
    }
    
    .checklist-item:hover {
      background: #fff;
    }
    
    .checklist-item.done {
      background: rgba(0,87,88,.04);
    }
    
    .checklist-checkbox {
      width: 26px;
      height: 26px;
      border: 2px solid var(--slate-300);
      border-radius: 50%;
      margin-right: 1rem;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all .2s ease;
    }
    
    .checklist-item:hover .checklist-checkbox {
      border-color: var(--teal);
    }
    
    .checklist-item.done .checklist-checkbox {
      background: var(--teal-dark);
      border-color: var(--teal-dark);
      color: #fff;
      box-shadow: 0 2px 8px rgba(0,87,88,.25);
    }
    
    .checklist-checkbox svg {
      width: 16px;
      height: 16px;
      display: none;
    }
    
    .checklist-item.done .checklist-checkbox svg {
      display: block;
    }
    
    .checklist-content {
      flex: 1;
      display: grid;
      grid-template-columns: 1fr;
      gap: .4rem;
    }
    
    @media (min-width: 768px) {
      .checklist-content {
        grid-template-columns: 1.5fr 1fr 1.5fr;
        gap: 1rem;
      }
    }
    
    .checklist-step {
      font-weight: 600;
      color: var(--slate-800);
      font-size: .95rem;
    }
    
    .checklist-item.done .checklist-step {
      color: var(--teal-dark);
      text-decoration: line-through;
      opacity: .7;
    }
    
    .checklist-resp {
      font-size: .88rem;
      color: var(--slate-500);
    }
    
    .checklist-action {
      font-size: .88rem;
      color: var(--slate-600);
    }

    /* ── Template Cards ──────────────────────────────────── */
    .template-card {
      background: var(--slate-50);
      border: 1px solid var(--slate-200);
      border-radius: 4px;
      overflow: hidden;
      margin-bottom: 1.25rem;
      transition: all .2s ease;
    }
    
    .template-card:hover {
      box-shadow: 0 8px 25px rgba(0,0,0,.06);
    }
    
    .template-header {
      background: var(--teal-dark);
      padding: .85rem 1.15rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: .65rem;
    }
    
    .template-header h4 {
      font-weight: 500;
      color: #fff;
      font-size: .88rem;
    }
    
    .copy-btn {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      background: #fff;
      color: var(--teal-dark);
      border: none;
      padding: .45rem .85rem;
      border-radius: 4px;
      font-size: .8rem;
      font-weight: 600;
      cursor: pointer;
      transition: all .2s ease;
      box-shadow: 0 2px 8px rgba(0,0,0,.1);
    }
    
    .copy-btn:hover {
      background: var(--slate-100);
    }
    
    .copy-btn svg {
      width: 14px;
      height: 14px;
    }
    
    .copy-btn.copied {
      background: var(--green-600);
      color: #fff;
    }
    
    .template-body {
      padding: 1.15rem;
      background: #fff;
      font-family: 'SF Mono', 'Fira Code', Consolas, monospace;
      font-size: .82rem;
      color: var(--slate-600);
      white-space: pre-wrap;
      line-height: 1.65;
    }
    
    .template-body strong {
      color: var(--teal-dark);
      font-weight: 600;
    }

    /* ── Attachment Items ────────────────────────────────── */
    .attachment-list {
      list-style: none;
    }
    
    .attachment-item {
      display: flex;
      align-items: flex-start;
      gap: .85rem;
      padding: 1rem 1.15rem;
      background: var(--slate-50);
      border: 1px solid var(--slate-200);
      border-radius: 4px;
      margin-bottom: .75rem;
      transition: all .2s ease;
    }
    
    .attachment-item:hover {
      box-shadow: 0 8px 20px rgba(0,87,88,.06);
      border-color: var(--teal);
    }
    
    .attachment-icon {
      font-size: 1.25rem;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--teal-dark);
      border-radius: 50%;
      flex-shrink: 0;
    }
    
    .attachment-info h5 {
      font-weight: 600;
      color: var(--teal-dark);
      font-size: .9rem;
      margin-bottom: .2rem;
    }
    
    .attachment-info p {
      font-size: .82rem;
      color: var(--slate-500);
      line-height: 1.45;
    }

    /* ── Back Link ───────────────────────────────────────── */
    .top-actions {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    
    @media (max-width: 640px) {
      .top-actions {
        flex-direction: column;
        align-items: stretch;
      }
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
    
    .back-link svg {
      width: 16px;
      height: 16px;
    }
    
    .download-pdf-btn {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      background: var(--teal-dark);
      color: #fff;
      text-decoration: none;
      font-size: .85rem;
      font-weight: 600;
      padding: .55rem 1.15rem;
      border-radius: 4px;
      transition: all .2s ease;
    }
    
    .download-pdf-btn:hover {
      background: var(--teal);
    }
    
    .download-pdf-btn svg {
      width: 16px;
      height: 16px;
    }

    /* ── Animation ───────────────────────────────────────── */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(15px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fade {
      animation: fadeIn .4s ease-out;
    }

    /* ── Responsive Table ────────────────────────────────── */
    .table-responsive {
      overflow-x: auto;
      border-radius: 4px;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../templates/topbar.php'; ?>
  
  <div class="page-wrapper">
    <div class="app-container">
      <!-- Mobile Header for Sidebar Toggle (only shows on mobile when topbar is visible) -->
      <div class="mobile-header">
        <div class="brand">Onboarding CSP</div>
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
            <strong>Versão:</strong> 1.0<br>
            <strong>Público:</strong> Revendas<br>
            <strong>Owner:</strong> Enablement
          </div>
        </div>
        <nav class="sidebar-nav">
          <ul>
            <?php foreach ($tabs as $tab): ?>
            <li>
              <a href="?tab=<?= $tab['id'] ?>" class="<?= $activeTab === $tab['id'] ? 'active' : '' ?>">
                <?php if ($tab['icon'] === 'book'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                <?php elseif ($tab['icon'] === 'document'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                <?php elseif ($tab['icon'] === 'check-square'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                <?php elseif ($tab['icon'] === 'message'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" /></svg>
                <?php elseif ($tab['icon'] === 'paperclip'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" /></svg>
                <?php endif; ?>
                <span><?= htmlspecialchars($tab['label']) ?></span>
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
              Voltar para Home
            </a>
            <a href="generate-csp-guide-pdf.php" class="download-pdf-btn" target="_blank">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
              Baixar Guia em PDF
            </a>
          </div>

        <?php if ($activeTab === 'resumo'): ?>
        <!-- Resumo Executivo -->
        <h2 class="section-title">Resumo Executivo</h2>
        <p class="lead-text">
          Bem-vindo à parceria Microsoft CSP com a TD SYNNEX! Este kit foi desenhado para guiar sua revenda nos primeiros passos como um <strong>Indirect Reseller</strong>, garantindo autonomia operacional para transacionar licenças (NCE) e consumo (Azure Plan).
        </p>
        <p class="lead-text">
          Aqui você aprenderá como acessar o portal CloudSolv/ECExpress, oficializar o vínculo com a TD SYNNEX no Microsoft Partner Center, realizar o cadastro de clientes (com e sem tenant prévio), colocar seu primeiro pedido e acionar as trilhas de capacitação comercial e técnica.
        </p>
        <div class="callout">
          <p>O objetivo é acelerar seu <em>time-to-market</em> e garantir receita recorrente sem atritos.</p>
        </div>

        <?php elseif ($activeTab === 'guia'): ?>
        <!-- Guia Completo -->
        <h2 class="section-title">Guia de Onboarding CSP</h2>

        <div class="section-header">
          <span class="badge">1</span>
          <h3>Visão Geral</h3>
        </div>
        <div class="step-card" style="border-radius: 4px; background: var(--slate-50);">
          <p style="font-size: .9rem; color: var(--slate-600); line-height: 1.65; margin-bottom: .75rem;">
            Processo de habilitação para comercializar soluções Microsoft Cloud via TD SYNNEX.
          </p>
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .75rem; margin-top: 1rem;">
            <div style="background: #fff; padding: .85rem; border-radius: 4px; border: 1px solid var(--slate-200);">
              <div style="font-size: .7rem; color: var(--slate-500); text-transform: uppercase; letter-spacing: .5px; margin-bottom: .2rem;">Quando usar</div>
              <div style="font-size: .85rem; color: var(--teal-dark); font-weight: 500;">Após assinatura do contrato</div>
            </div>
            <div style="background: #fff; padding: .85rem; border-radius: 4px; border: 1px solid var(--slate-200);">
              <div style="font-size: .7rem; color: var(--slate-500); text-transform: uppercase; letter-spacing: .5px; margin-bottom: .2rem;">Pré-requisitos</div>
              <div style="font-size: .85rem; color: var(--teal-dark); font-weight: 500;">PartnerID + Cadastro ECExpress</div>
            </div>
            <div style="background: #fff; padding: .85rem; border-radius: 4px; border: 1px solid var(--slate-200);">
              <div style="font-size: .7rem; color: var(--slate-500); text-transform: uppercase; letter-spacing: .5px; margin-bottom: .2rem;">Resultado</div>
              <div style="font-size: .85rem; color: var(--teal-dark); font-weight: 500;">CloudSolv + Licenças ativas</div>
            </div>
          </div>
        </div>

        <div class="section-header">
          <span class="badge">2</span>
          <h3>Passo a Passo</h3>
        </div>

        <div class="step-card">
          <h4><span class="step-num">1</span> Aceite de Parceria</h4>
          <p class="warning">Sem este aceite, a Microsoft não reconhece a TD SYNNEX como seu provedor indireto.</p>
          <ol>
            <li>Acesse o <strong>Microsoft Partner Center</strong> com usuário Global Admin</li>
            <li>Clique no link de Aceite de Parceria (Westcon Brasil Ltda)</li>
            <li>Marque concordância e clique em <strong>Autorizar fornecedor indireto</strong></li>
          </ol>
          <div style="background: var(--green-50); color: var(--green-700); padding: .65rem .85rem; border-radius: 4px; margin-top: .75rem; font-size: .85rem; font-weight: 500;">
            ✓ Sucesso: TD SYNNEX listada na aba Indirect Providers
          </div>
        </div>

        <div class="step-card">
          <h4><span class="step-num">2</span> Acesso ao ECExpress e CloudSolv</h4>
          <ol>
            <li>Acesse o portal <strong>ECExpress</strong> com suas credenciais</li>
            <li>Clique no botão <strong>CLOUDSolv</strong> no canto superior direito</li>
            <li>Navegue em <strong>Portfólio → Microsoft</strong> para explorar produtos</li>
          </ol>
        </div>

        <div class="step-card">
          <h4><span class="step-num">3</span> Cadastro do Cliente Final</h4>
          <div class="scenario-box">
            <h4>Cliente Novo (Sem Tenant)</h4>
            <ol>
              <li>CloudSolv → Clientes → Criar Cliente</li>
              <li>Insira CNPJ e clique em Validar</li>
              <li>Deixe Microsoft Account em branco → Gravar</li>
            </ol>
          </div>
          <div class="scenario-box">
            <h4>Cliente com Tenant Existente</h4>
            <ol>
              <li>Partner Center: gere link de Solicitar relacionamento</li>
              <li>CloudSolv: preencha CNPJ + Validar</li>
              <li>Insira ID do Tenant + e-mail Admin → Gravar</li>
            </ol>
          </div>
          <div style="background: var(--green-50); color: var(--green-700); padding: .65rem .85rem; border-radius: 4px; margin-top: .75rem; font-size: .85rem; font-weight: 500;">
            ✓ Sucesso: Status do cliente muda de HOLD para VALID
          </div>
        </div>

        <div class="step-card">
          <h4><span class="step-num">4</span> Primeiro Pedido</h4>
          <ol>
            <li><strong>Licenças NCE:</strong> Produto + Faturamento (Mensal/Anual) + Quantidade</li>
            <li><strong>Azure Plan:</strong> Standard (8%) ou Premium (5% + serviços gerenciados)</li>
            <li>Checkout: Markup + Nº PO + Condição de pagamento</li>
            <li>Confirme e ative</li>
          </ol>
        </div>

        <div class="step-card">
          <h4><span class="step-num">5</span> Capacitação</h4>
          <ol>
            <li><strong>Microsoft Learn:</strong> Trilhas de certificação (Fundamentals, Associate)</li>
            <li><strong>TD SYNNEX:</strong> Microsoft Brazil Sales Academy + Workshops M365</li>
          </ol>
        </div>

        <div class="section-header">
          <span class="badge">3</span>
          <h3>Troubleshooting</h3>
        </div>

        <div class="table-responsive">
          <table class="trouble-table">
            <thead>
              <tr>
                <th>Problema</th>
                <th>Causa</th>
                <th>Solução</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="error-lock">Status <code>LOCK</code></td>
                <td>Dados cadastrais incorretos ou desatualizados</td>
                <td>Editar dados no CloudSolv. Se persistir, abrir chamado Jira.</td>
              </tr>
              <tr>
                <td class="error-hold">Status <code>HOLD</code></td>
                <td>Cliente na fila de validação da mesa de crédito</td>
                <td>Aguardar ou acionar e-mail de suporte se urgente.</td>
              </tr>
              <tr>
                <td>Erro ao faturar tenant</td>
                <td>Cliente não aceitou Relacionamento de Revendedor</td>
                <td>Solicitar ao Global Admin que aceite o link.</td>
              </tr>
              <tr>
                <td>Custo Azure não visível</td>
                <td>Falta do recurso Cost Management</td>
                <td>Solicitar ativação via chamado Jira.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="warning-box">
          <h4>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
            Atenção
          </h4>
          <p>Se não possui PartnerID (MPN ID), crie-o primeiro em <code>partner.microsoft.com</code> antes de iniciar o onboarding.</p>
        </div>

        <div class="section-header">
          <span class="badge">4</span>
          <h3>Canais de Suporte</h3>
        </div>

        <div class="support-grid">
          <div class="support-card">
            <h5>Suporte Técnico (24x7)</h5>
            <p class="desc">Incidentes técnicos no ambiente do cliente</p>
            <ul>
              <li><strong>E-mail:</strong> wcbrsuporte@tdsynnex.com</li>
              <li><strong>Tel:</strong> 0800-940-2910</li>
            </ul>
          </div>
          <div class="support-card">
            <h5>Suporte Operacional</h5>
            <p class="desc">Faturamento e plataforma CloudSolv</p>
            <ul>
              <li><strong>Acesso:</strong> Ticket via Jira</li>
            </ul>
          </div>
          <div class="support-card">
            <h5>Suporte Comercial</h5>
            <p class="desc">Pré-vendas e sizing</p>
            <ul>
              <li><strong>E-mail:</strong> salescloudbr@tdsynnex.com</li>
            </ul>
          </div>
        </div>

        <div class="section-header">
          <span class="badge">5</span>
          <h3>Glossário</h3>
        </div>

        <dl class="glossary-grid">
          <div class="glossary-item">
            <dt>Indirect Reseller</dt>
            <dd>Revenda que vende Microsoft via Distribuidor</dd>
          </div>
          <div class="glossary-item">
            <dt>Indirect Provider</dt>
            <dd>TD SYNNEX, o distribuidor oficial</dd>
          </div>
          <div class="glossary-item">
            <dt>CloudSolv / ECExpress</dt>
            <dd>Plataformas de E-commerce TD SYNNEX</dd>
          </div>
          <div class="glossary-item">
            <dt>NCE</dt>
            <dd>New Commerce Experience - novo modelo de licenciamento</dd>
          </div>
          <div class="glossary-item">
            <dt>Aceite Delegado</dt>
            <dd>Permissão para administrar tenant do cliente</dd>
          </div>
          <div class="glossary-item">
            <dt>Cost Management</dt>
            <dd>Ferramenta Azure de visibilidade de custos</dd>
          </div>
        </dl>

        <?php elseif ($activeTab === 'checklist'): ?>
        <!-- Checklist -->
        <h2 class="section-title">Checklist de Onboarding</h2>
        <p style="color:var(--slate-600); margin-bottom:1.5rem;">Utilize esta tabela interativa para acompanhar a prontidão da sua revenda.</p>

        <div class="progress-bar">
          <div class="progress-fill" id="progressFill" style="width:0%;"></div>
        </div>
        <p class="progress-text"><span id="progressText">0</span>% Concluído</p>

        <div class="checklist-container" id="checklistContainer">
          <div class="checklist-item" data-id="1">
            <div class="checklist-checkbox">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
            </div>
            <div class="checklist-content">
              <div class="checklist-step">1. Cadastro ECExpress</div>
              <div class="checklist-resp"><span style="font-weight:600;">Resp:</span> Revenda</div>
              <div class="checklist-action">Login realizado com sucesso.</div>
            </div>
          </div>
          <div class="checklist-item" data-id="2">
            <div class="checklist-checkbox">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
            </div>
            <div class="checklist-content">
              <div class="checklist-step">2. Aceite Provedor Indireto</div>
              <div class="checklist-resp"><span style="font-weight:600;">Resp:</span> Revenda</div>
              <div class="checklist-action">"Westcon Brasil Ltda" listada no Partner Center.</div>
            </div>
          </div>
          <div class="checklist-item" data-id="3">
            <div class="checklist-checkbox">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
            </div>
            <div class="checklist-content">
              <div class="checklist-step">3. Cadastro 1º Cliente</div>
              <div class="checklist-resp"><span style="font-weight:600;">Resp:</span> Revenda</div>
              <div class="checklist-action">Cliente com status VALID no CloudSolv.</div>
            </div>
          </div>
          <div class="checklist-item" data-id="4">
            <div class="checklist-checkbox">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
            </div>
            <div class="checklist-content">
              <div class="checklist-step">4. Aceite Delegado (Se aplicável)</div>
              <div class="checklist-resp"><span style="font-weight:600;">Resp:</span> Cliente Final</div>
              <div class="checklist-action">Cliente clicou no link e aceitou relacionamento.</div>
            </div>
          </div>
          <div class="checklist-item" data-id="5">
            <div class="checklist-checkbox">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
            </div>
            <div class="checklist-content">
              <div class="checklist-step">5. Pedido / Provisionamento</div>
              <div class="checklist-resp"><span style="font-weight:600;">Resp:</span> Revenda</div>
              <div class="checklist-action">Licença ativa no portal do cliente / Tenant criado.</div>
            </div>
          </div>
          <div class="checklist-item" data-id="6">
            <div class="checklist-checkbox">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
            </div>
            <div class="checklist-content">
              <div class="checklist-step">6. Treinamento de Vendas</div>
              <div class="checklist-resp"><span style="font-weight:600;">Resp:</span> Revenda</div>
              <div class="checklist-action">Inscrição no "Microsoft Brazil Sales Academy".</div>
            </div>
          </div>
        </div>

        <?php elseif ($activeTab === 'templates'): ?>
        <!-- Templates -->
        <h2 class="section-title">Templates Prontos</h2>
        <p style="color:var(--slate-600); margin-bottom:1.5rem;">Textos pré-aprovados para facilitar a sua comunicação. Clique em copiar e cole no seu e-mail ou chat.</p>

        <div class="template-card">
          <div class="template-header">
            <h4>Template 1: E-mail de Boas-Vindas Interno (Para a sua equipe)</h4>
            <button class="copy-btn" data-template="template1">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
              <span>Copiar</span>
            </button>
          </div>
          <div class="template-body" id="template1"><strong>Assunto:</strong> 🚀 Estamos prontos para vender Microsoft Cloud!

Olá time,

É com grande satisfação que anuncio a finalização do nosso onboarding Microsoft CSP com a TD SYNNEX. A partir de hoje, nossa plataforma CloudSolv está ativa para provisionamento de licenças NCE e consumo Azure.

* Para vendas: Consultem os planos NCE e os modelos Azure Plan (Standard e Premium).
* Para pré-vendas: Já podemos acionar os especialistas da TD SYNNEX via nosso Gerente de Contas.

Vamos juntos acelerar nossos negócios em nuvem!

Abraços,</div>
        </div>

        <div class="template-card">
          <div class="template-header">
            <h4>Template 2: E-mail para o Cliente Final (Aceite Delegado)</h4>
            <button class="copy-btn" data-template="template2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
              <span>Copiar</span>
            </button>
          </div>
          <div class="template-body" id="template2"><strong>Assunto:</strong> Ação Necessária: Autorização de Gerenciamento Microsoft CSP

Olá [Nome do Cliente],

Para seguirmos com o provisionamento das suas licenças/ambiente Azure com os melhores benefícios, precisamos que você nos autorize como sua revenda oficial no portal da Microsoft.

Por favor, peça ao seu Administrador Global que clique no link abaixo, faça o login e clique em "Aceitar":
👉 [Inserir Link do Partner Center aqui]

Essa ação é rápida, segura e necessária apenas uma vez. Qualquer dúvida, estou à disposição!

Atenciosamente,</div>
        </div>

        <div class="template-card">
          <div class="template-header">
            <h4>Template 3: Mensagem Rápida no Teams (Para acionar a TD SYNNEX)</h4>
            <button class="copy-btn" data-template="template3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
              <span>Copiar</span>
            </button>
          </div>
          <div class="template-body" id="template3">Olá [Nome do Gerente de Contas], finalizamos o cadastro do cliente [Nome do Cliente / CNPJ] no CloudSolv, mas o status está em HOLD. Conseguem pedir prioridade na mesa de crédito para colocarmos o pedido NCE ainda hoje? Obrigado!</div>
        </div>

        <?php elseif ($activeTab === 'anexos'): ?>
        <!-- Anexos -->
        <h2 class="section-title">Lista de Anexos/Prints</h2>
        <p style="color:var(--slate-600); margin-bottom:1.5rem;">Recomendamos que você anexe ou salve na intranet da sua empresa para compor o material da sua equipe:</p>

        <ul class="attachment-list">
          <li class="attachment-item">
            <span class="attachment-icon">🔗</span>
            <div class="attachment-info">
              <h5>Tutorial CloudSolv TD SYNNEX (PDF)</h5>
              <p>Contém os prints tela a tela de como navegar no ECExpress.</p>
            </div>
          </li>
          <li class="attachment-item">
            <span class="attachment-icon">🔗</span>
            <div class="attachment-info">
              <h5>Guia de Condições Comerciais v4 (PDF)</h5>
              <p>Regras de comissionamento Azure (8% e 5%) e tabela de serviços do Azure Plan Premium.</p>
            </div>
          </li>
          <li class="attachment-item">
            <span class="attachment-icon">🔗</span>
            <div class="attachment-info">
              <h5>Calculadora Azure TD SYNNEX (Excel)</h5>
              <p>Planilha oficial de precificação.</p>
            </div>
          </li>
          <li class="attachment-item">
            <span class="attachment-icon">📸</span>
            <div class="attachment-info">
              <h5>Print do Partner Center</h5>
              <p>Tela de Indirect Providers demonstrando onde conferir o aceite da "Westcon Brasil Ltda".</p>
            </div>
          </li>
          <li class="attachment-item">
            <span class="attachment-icon">🔗</span>
            <div class="attachment-info">
              <h5>Link oficial Microsoft Learn</h5>
              <p>docs.microsoft.com/learn para início das certificações do seu time técnico.</p>
            </div>
          </li>
        </ul>
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

    menuToggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      if (sidebar.classList.contains('open')) {
        menuIcon.style.display = 'none';
        closeIcon.style.display = 'block';
      } else {
        menuIcon.style.display = 'block';
        closeIcon.style.display = 'none';
      }
    });

    // Checklist functionality
    const checklistContainer = document.getElementById('checklistContainer');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');

    if (checklistContainer) {
      // Load saved state from localStorage
      const savedState = JSON.parse(localStorage.getItem('cspChecklist') || '{}');
      
      document.querySelectorAll('.checklist-item').forEach(item => {
        const id = item.dataset.id;
        if (savedState[id]) {
          item.classList.add('done');
        }
      });
      
      updateProgress();

      checklistContainer.addEventListener('click', (e) => {
        const item = e.target.closest('.checklist-item');
        if (item) {
          item.classList.toggle('done');
          
          // Save state
          const state = {};
          document.querySelectorAll('.checklist-item').forEach(i => {
            state[i.dataset.id] = i.classList.contains('done');
          });
          localStorage.setItem('cspChecklist', JSON.stringify(state));
          
          updateProgress();
        }
      });
    }

    function updateProgress() {
      const total = document.querySelectorAll('.checklist-item').length;
      const done = document.querySelectorAll('.checklist-item.done').length;
      const percentage = Math.round((done / total) * 100);
      
      if (progressFill) progressFill.style.width = percentage + '%';
      if (progressText) progressText.textContent = percentage;
    }

    // Template copy functionality
    document.querySelectorAll('.copy-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const templateId = btn.dataset.template;
        const templateBody = document.getElementById(templateId);
        
        if (templateBody) {
          navigator.clipboard.writeText(templateBody.textContent).then(() => {
            const originalText = btn.querySelector('span').textContent;
            btn.classList.add('copied');
            btn.querySelector('span').textContent = 'Copiado!';
            
            // Change icon to check
            btn.querySelector('svg').innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />';
            
            setTimeout(() => {
              btn.classList.remove('copied');
              btn.querySelector('span').textContent = originalText;
              btn.querySelector('svg').innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />';
            }, 2000);
          });
        }
      });
    });
  </script>
</body>
</html>
