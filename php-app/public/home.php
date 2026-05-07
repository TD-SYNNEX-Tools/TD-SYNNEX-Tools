<?php
// filepath: public/home.php
session_start();

// Lê preços do skus.json
$jsonFile = __DIR__ . '/skus.json';
$prices = [];
if (file_exists($jsonFile)) {
    $prices = json_decode(file_get_contents($jsonFile), true) ?: [];
}
// Sincroniza
$_SESSION['prices'] = $prices;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Home - TD SYNNEX Tools</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- Carregamento assíncrono das fontes para evitar bloqueio de renderização -->
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"></noscript>
  <style>
    :root {
      --teal: #005758;
      --teal-dark: #003031;
      --charcoal: #262626;
      --gray: #737373;
      --light-gray: #f5f5f7;
      --blue: #0078D4;
      --green: #009600;
      --red: #DC2626;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: var(--light-gray);
      color: var(--charcoal);
      min-height: 100vh;
    }

    /* ── Top Bar ─────────────────────────────────────────── */
    .topbar {
      background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal) 100%);
      padding: 0 2rem;
      height: 56px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 12px rgba(0,48,49,.25);
    }
    .topbar-brand {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .topbar-brand img { height: 32px; filter: brightness(0) invert(1); }
    .topbar-brand span {
      color: rgba(255,255,255,.55);
      font-size: .75rem;
      font-weight: 500;
      letter-spacing: 1.6px;
      text-transform: uppercase;
    }
    .topbar-nav {
      display: flex;
      align-items: center;
      gap: 0;
    }
    .topbar-nav > a,
    .topbar-nav > .nav-dropdown > .nav-trigger {
      color: rgba(255,255,255,.7);
      font-size: .82rem;
      font-weight: 500;
      text-decoration: none;
      padding: 16px 18px;
      transition: all .2s;
      position: relative;
      cursor: pointer;
      background: none;
      border: none;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .topbar-nav > a:hover,
    .topbar-nav > .nav-dropdown:hover > .nav-trigger { color: #fff; background: rgba(255,255,255,.08); }
    .topbar-nav > a.active {
      color: #fff;
      font-weight: 600;
    }
    .topbar-nav > a.active::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 18px;
      right: 18px;
      height: 2px;
      background: #fff;
      border-radius: 2px 2px 0 0;
    }

    /* ── Mega-menu dropdown ──────────────────────────────── */
    .nav-dropdown { position: relative; }
    .nav-trigger svg.chevron {
      width: 14px; height: 14px;
      transition: transform .2s;
    }
    .nav-dropdown:hover .nav-trigger svg.chevron { transform: rotate(180deg); }
    .mega-menu {
      position: absolute;
      top: 100%;
      left: 50%;
      transform: translateX(-50%);
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 20px 60px rgba(0,48,49,.18), 0 2px 8px rgba(0,0,0,.08);
      padding: 1.25rem;
      min-width: 580px;
      opacity: 0;
      visibility: hidden;
      transition: all .2s ease;
      pointer-events: none;
      z-index: 200;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }
    .nav-dropdown:hover .mega-menu {
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
    }
    .mega-vendor {
      padding: 0;
    }
    .mega-vendor-header {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      margin-bottom: 4px;
    }
    .mega-vendor-header .vendor-icon {
      width: 28px;
      height: 28px;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .mega-vendor-header span {
      font-size: .78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .8px;
      color: var(--gray);
    }
    .mega-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 12px;
      border-radius: 8px;
      text-decoration: none;
      color: var(--charcoal);
      font-size: .84rem;
      font-weight: 500;
      transition: all .15s;
    }
    .mega-link:hover {
      background: rgba(0,87,88,.06);
      color: var(--teal);
    }
    .mega-link .ml-icon {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .mega-link .ml-icon svg { width: 16px; height: 16px; }
    .mega-link .ml-text small {
      display: block;
      font-size: .72rem;
      font-weight: 400;
      color: var(--gray);
      margin-top: 1px;
    }
    .mega-divider {
      border-left: 1px solid #f0f0f0;
      padding-left: 1rem;
    }

    /* ── Simple dropdown ─────────────────────────────────── */
    .simple-dropdown {
      position: absolute;
      top: 100%;
      left: 50%;
      transform: translateX(-50%);
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 12px 40px rgba(0,48,49,.15), 0 2px 6px rgba(0,0,0,.06);
      padding: .5rem;
      min-width: 240px;
      opacity: 0;
      visibility: hidden;
      transition: all .2s ease;
      pointer-events: none;
      z-index: 200;
    }
    .nav-dropdown:hover .simple-dropdown {
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
    }
    .simple-dropdown a,
    .simple-dropdown button {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 14px;
      border-radius: 8px;
      text-decoration: none;
      color: var(--charcoal);
      font-size: .84rem;
      font-weight: 500;
      transition: all .15s;
      width: 100%;
      border: none;
      background: none;
      cursor: pointer;
      text-align: left;
    }
    .simple-dropdown a:hover,
    .simple-dropdown button:hover {
      background: rgba(0,87,88,.06);
      color: var(--teal);
    }
    .simple-dropdown .sd-icon {
      width: 30px;
      height: 30px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .simple-dropdown .sd-icon svg { width: 15px; height: 15px; }
    .simple-dropdown .sd-sep {
      height: 1px;
      background: #f0f0f0;
      margin: 4px 8px;
    }

    /* ── About modal ─────────────────────────────────────── */
    .about-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.4);
      backdrop-filter: blur(4px);
      z-index: 300;
      display: none;
      align-items: center;
      justify-content: center;
    }
    .about-overlay.open { display: flex; }
    .about-box {
      background: #fff;
      border-radius: 16px;
      width: 520px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 24px 64px rgba(0,48,49,.2);
      position: relative;
    }
    .about-header {
      background: linear-gradient(135deg, var(--teal-dark), var(--teal));
      padding: 2rem 2rem 1.5rem;
      border-radius: 16px 16px 0 0;
      color: #fff;
    }
    .about-header img { height: 28px; filter: brightness(0) invert(1); margin-bottom: 1rem; }
    .about-header h3 { font-size: 1.2rem; font-weight: 700; margin-bottom: .25rem; }
    .about-header p { font-size: .84rem; color: rgba(255,255,255,.7); }
    .about-close {
      position: absolute;
      top: 16px;
      right: 16px;
      background: rgba(255,255,255,.15);
      border: none;
      color: #fff;
      border-radius: 8px;
      padding: 6px;
      cursor: pointer;
      transition: background .15s;
    }
    .about-close:hover { background: rgba(255,255,255,.25); }
    .about-body { padding: 1.5rem 2rem 2rem; }
    .about-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid #f5f5f5;
    }
    .about-item:last-child { border-bottom: none; }
    .about-item .ai-icon {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background: #e6f4f4;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      color: var(--teal);
    }
    .about-item .ai-icon svg { width: 16px; height: 16px; }
    .about-item .ai-label { font-size: .75rem; color: var(--gray); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
    .about-item .ai-value { font-size: .88rem; color: var(--charcoal); font-weight: 500; }


    /* ── Hero ─────────────────────────────────────────────── */
    .hero {
      background: linear-gradient(135deg, var(--teal) 0%, #007a7b 50%, var(--blue) 100%);
      padding: 3rem 2rem 2.5rem;
      color: #fff;
      position: relative;
      overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute;
      top: -60%;
      right: -10%;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(255,255,255,.06) 0%, transparent 70%);
      border-radius: 50%;
    }
    .hero-inner {
      max-width: 1320px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }
    .hero h2 {
      font-size: 1.85rem;
      font-weight: 700;
      margin-bottom: .5rem;
      letter-spacing: -.3px;
    }
    .hero p {
      font-size: .95rem;
      color: rgba(255,255,255,.75);
      max-width: 520px;
    }

    /* ── Cards Grid ──────────────────────────────────────── */
    .cards-section {
      max-width: 1320px;
      margin: 1rem auto 3rem;
      padding: 0 2rem;
      position: relative;
      z-index: 2;
    }
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
      gap: 1.5rem;
    }

    /* ── Card ────────────────────────────────────────────── */
    .tool-card {
      background: #fff;
      border-radius: 14px;
      border: 1px solid #e5e5e5;
      overflow: hidden;
      transition: all .25s ease;
      display: flex;
      flex-direction: column;
      position: relative;
    }
    .tool-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 36px rgba(0,87,88,.12), 0 4px 12px rgba(0,0,0,.06);
      border-color: rgba(0,87,88,.2);
    }
    .card-accent {
      height: 4px;
      width: 100%;
    }
    .card-body {
      padding: 1.5rem;
      flex: 1;
      display: flex;
      flex-direction: column;
    }
    .card-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1rem;
      flex-shrink: 0;
    }
    .card-icon svg { width: 24px; height: 24px; }
    .card-badge {
      position: absolute;
      top: 16px;
      right: 16px;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: .68rem;
      font-weight: 700;
      letter-spacing: .5px;
      text-transform: uppercase;
    }
    .card-title {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--charcoal);
      margin-bottom: .5rem;
    }
    .card-desc {
      font-size: .84rem;
      color: var(--gray);
      line-height: 1.55;
      margin-bottom: 1.25rem;
    }
    .card-links {
      list-style: none;
      margin-top: auto;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .card-links a {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 12px;
      font-size: .83rem;
      font-weight: 500;
      color: #404040;
      text-decoration: none;
      border-radius: 8px;
      transition: all .15s;
    }
    .card-links a:hover {
      background: rgba(0,87,88,.06);
      color: var(--teal);
    }
    .card-links a svg {
      width: 16px;
      height: 16px;
      color: #b0b0b0;
      transition: color .15s;
      flex-shrink: 0;
    }
    .card-links a:hover svg { color: var(--teal); }

    /* ── Card color themes ────────────────────────────────── */
    .theme-azure .card-accent  { background: linear-gradient(90deg, var(--teal), var(--blue)); }
    .theme-azure .card-icon    { background: #e6f4f4; color: var(--teal); }

    .theme-sql .card-accent    { background: linear-gradient(90deg, var(--blue), #4da6ff); }
    .theme-sql .card-icon      { background: #e8f2fc; color: var(--blue); }
    .theme-sql .card-links a:hover { background: rgba(0,120,212,.06); color: var(--blue); }
    .theme-sql .card-links a:hover svg { color: var(--blue); }

    .theme-m365 .card-accent   { background: linear-gradient(90deg, #ea580c, #f97316); }
    .theme-m365 .card-icon     { background: #fff3e8; color: #ea580c; }
    .theme-m365 .card-links a:hover { background: rgba(234,88,12,.06); color: #ea580c; }
    .theme-m365 .card-links a:hover svg { color: #ea580c; }

    .theme-hub .card-accent    { background: linear-gradient(90deg, var(--teal-dark), var(--teal)); }
    .theme-hub .card-icon      { background: #e6f4f4; color: var(--teal-dark); }

    /* ── Vendor section headers ─────────────────────────── */
    .vendor-section {
      margin-bottom: 2.5rem;
    }
    .vendor-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 1.25rem;
    }
    .vendor-header .vh-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .vendor-header h3 {
      font-size: 1rem;
      font-weight: 700;
      color: var(--charcoal);
      letter-spacing: -.2px;
    }
    .vendor-header h3 small {
      display: block;
      font-size: .76rem;
      font-weight: 500;
      color: var(--gray);
      margin-top: 1px;
    }
    .vendor-divider {
      height: 1px;
      background: #e5e5e5;
      margin: 0 0 2.5rem;
    }

    /* ── Footer ──────────────────────────────────────────── */
    .page-footer {
      text-align: center;
      padding: 1.5rem;
      color: var(--gray);
      font-size: .75rem;
    }
  </style>
</head>
<body>

    <!-- Top Bar -->
    <header class="topbar">
        <div class="topbar-brand">
            <img src="assets/images/logo.png" alt="TD SYNNEX">
            <span>Tools</span>
        </div>
        <nav class="topbar-nav">
            <a href="home.php" class="active">Home</a>

            <!-- Cloud Mega Menu -->
            <div class="nav-dropdown">
                <button class="nav-trigger">
                    Cloud
                    <svg class="chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>
                <div class="mega-menu">
                    <!-- Microsoft -->
                    <div class="mega-vendor">
                        <div class="mega-vendor-header">
                            <div class="vendor-icon" style="background:#e8f2fc;">
                                <svg style="width:16px;height:16px;color:#0078D4;" viewBox="0 0 24 24" fill="currentColor"><path d="M11.4 2H2v9.4h9.4V2zm0 10.6H2V22h9.4V12.6zM22 2h-9.4v9.4H22V2zm0 10.6h-9.4V22H22V12.6z"/></svg>
                            </div>
                            <span>Microsoft</span>
                        </div>
                        <a href="analise-financeira.php" class="mega-link">
                            <div class="ml-icon" style="background:#e8f2fc; color:var(--blue);">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                            </div>
                            <div class="ml-text">Análise Financeira<small>MOSP, CSP, Enterprise</small></div>
                        </a>
                        <a href="analise-migracao.php" class="mega-link">
                            <div class="ml-icon" style="background:#e6f4f4; color:var(--teal);">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" /></svg>
                            </div>
                            <div class="ml-text">Análise Técnica<small>Cenários MOSP/EA</small></div>
                        </a>
                        <a href="migracao-m365.php" class="mega-link">
                            <div class="ml-icon" style="background:#fff3e8; color:#ea580c;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                            </div>
                            <div class="ml-text">Migração M365 - T1<small>Tier 1 &rarr; Tier 2</small></div>
                        </a>
                        <a href="sql-advisor.php" class="mega-link">
                            <div class="ml-icon" style="background:#e8f2fc; color:var(--blue);">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" /></svg>
                            </div>
                            <div class="ml-text">SQL Server Advisor<small>Comparativo de licenciamento</small></div>
                        </a>
                        <a href="#cloud-partner-hub" class="mega-link">
                            <div class="ml-icon" style="background:#e6f4f4; color:var(--teal-dark);">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                            </div>
                            <div class="ml-text">Cloud Partner HUB<small>Portal de parceiros</small></div>
                        </a>
                    </div>

                    <!-- Google -->
                    <div class="mega-vendor mega-divider">
                        <div class="mega-vendor-header">
                            <div class="vendor-icon" style="background:#fef3e8;">
                                <svg style="width:16px;height:16px;" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                            </div>
                            <span>Google Cloud</span>
                        </div>
                        <div class="mega-link" style="opacity:.45; cursor:default; pointer-events:none;">
                            <div class="ml-icon" style="background:#f5f5f7;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color:#b0b0b0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            </div>
                            <div class="ml-text">Em breve<small>Ferramentas GCP</small></div>
                        </div>

                        <div class="mega-vendor-header" style="margin-top:16px;">
                            <div class="vendor-icon" style="background:#fff3e0;">
                                <svg style="width:16px;height:16px;color:#FF9900;" viewBox="0 0 24 24" fill="currentColor"><path d="M6.763 10.036c0 .296.032.535.088.71.064.176.144.368.256.576a.391.391 0 0 1 .064.2.36.36 0 0 1-.176.288l-.592.384a.291.291 0 0 1-.16.064c-.064 0-.128-.032-.192-.08a3.97 3.97 0 0 1-.384-.496 2.326 2.326 0 0 1-.264-.608c-.672.784-1.504 1.176-2.512 1.176-.72 0-1.296-.208-1.712-.608-.416-.4-.632-.928-.632-1.584 0-.704.248-1.272.76-1.712.512-.44 1.192-.656 2.056-.656.288 0 .576.016.88.064.304.048.608.112.928.192v-.608c0-.624-.128-1.064-.4-1.312-.264-.256-.72-.376-1.36-.376-.288 0-.584.032-.888.112a6.458 6.458 0 0 0-.888.288 2.374 2.374 0 0 1-.28.112.384.384 0 0 1-.112.016c-.16 0-.24-.112-.24-.352V5.2a.72.72 0 0 1 .08-.352c.048-.064.144-.128.304-.192.288-.128.64-.24 1.04-.336a5.316 5.316 0 0 1 1.28-.144c.976 0 1.696.224 2.144.672.448.448.672 1.136.672 2.064v2.72h.016zm-3.472 1.296c.272 0 .56-.048.864-.16.304-.112.576-.304.8-.56a1.39 1.39 0 0 0 .288-.512c.048-.192.08-.416.08-.672v-.32a6.478 6.478 0 0 0-.736-.144 6.15 6.15 0 0 0-.752-.048c-.56 0-.976.112-1.248.336-.272.224-.4.544-.4.96 0 .384.096.672.304.88.192.208.48.312.832.24h-.032zm6.88.912c-.208 0-.336-.032-.416-.112-.08-.064-.16-.208-.224-.4L7.456 5.2a1.748 1.748 0 0 1-.096-.416c0-.16.08-.256.24-.256h.928c.208 0 .352.032.424.112.08.064.144.208.208.4l1.488 5.888 1.376-5.888c.048-.208.112-.336.208-.4.08-.064.24-.112.432-.112h.752c.208 0 .352.032.432.112.08.064.16.208.208.4l1.392 5.968 1.536-5.968c.064-.208.144-.336.208-.4.08-.064.224-.112.424-.112h.88c.16 0 .256.08.256.256 0 .048-.016.096-.032.16a1.433 1.433 0 0 1-.064.272l-2.144 6.528c-.064.208-.144.336-.224.4-.08.064-.224.112-.416.112h-.816c-.208 0-.352-.032-.432-.112-.08-.08-.16-.208-.208-.416l-1.376-5.744-1.36 5.728c-.048.208-.128.336-.208.416-.08.08-.24.112-.432.112h-.816v.016zm11.008.272c-.432 0-.864-.048-1.28-.16-.416-.112-.736-.224-.944-.352-.128-.08-.208-.16-.24-.256a.622.622 0 0 1-.048-.24v-.4c0-.24.096-.352.272-.352a.69.69 0 0 1 .208.032c.064.032.16.08.272.128.368.176.768.32 1.184.416.432.096.848.144 1.28.144.672 0 1.2-.112 1.568-.352.368-.24.56-.576.56-1.008 0-.288-.096-.528-.288-.72-.192-.192-.56-.368-1.088-.528l-1.568-.48c-.784-.24-1.36-.592-1.712-1.064a2.388 2.388 0 0 1-.528-1.488c0-.432.096-.816.288-1.136.192-.32.448-.592.768-.816.32-.224.688-.384 1.104-.496a4.884 4.884 0 0 1 1.344-.176c.24 0 .48.016.736.048.24.032.48.08.704.128.208.064.416.128.608.192.192.08.336.16.432.24.128.08.224.16.272.256a.505.505 0 0 1 .064.272v.368c0 .24-.096.368-.272.368a1.252 1.252 0 0 1-.448-.16 5.463 5.463 0 0 0-2.272-.464c-.608 0-1.088.096-1.424.304-.336.208-.512.528-.512.976 0 .304.112.56.32.752.208.192.608.384 1.168.544l1.536.464c.768.24 1.328.576 1.664 1.016.336.44.496.944.496 1.504 0 .448-.096.848-.272 1.184a2.86 2.86 0 0 1-.768.88c-.336.24-.72.432-1.168.56-.464.128-.96.192-1.504.192z"/><path fill="#FF9900" d="M21.384 18.736c-2.648 1.968-6.504 3.008-9.816 3.008-4.64 0-8.824-1.712-11.984-4.56-.256-.224-.016-.528.272-.352 3.408 1.984 7.632 3.168 11.992 3.168 2.944 0 6.176-.608 9.152-1.872.448-.192.832.304.384.608z"/></svg>
                            </div>
                            <span>AWS</span>
                        </div>
                        <div class="mega-link" style="opacity:.45; cursor:default; pointer-events:none;">
                            <div class="ml-icon" style="background:#f5f5f7;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color:#b0b0b0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            </div>
                            <div class="ml-text">Em breve<small>Ferramentas AWS</small></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ferramentas dropdown -->
            <div class="nav-dropdown">
                <button class="nav-trigger">
                    Ferramentas
                    <svg class="chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>
                <div class="simple-dropdown">
                    <a href="analise-financeira.php">
                        <div class="sd-icon" style="background:#e8f2fc; color:var(--blue);">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                        </div>
                        Análise Financeira Azure
                    </a>
                    <a href="analise-migracao.php">
                        <div class="sd-icon" style="background:#e6f4f4; color:var(--teal);">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" /></svg>
                        </div>
                        Análise Técnica de Recursos
                    </a>
                    <div class="sd-sep"></div>
                    <a href="sql-advisor.php">
                        <div class="sd-icon" style="background:#ede9fe; color:#7c3aed;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" /></svg>
                        </div>
                        SQL Licensing Advisor
                    </a>
                    <a href="sku-management.php">
                        <div class="sd-icon" style="background:#fff3e8; color:#ea580c;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" /></svg>
                        </div>
                        Gestão de SKUs
                    </a>
                </div>
            </div>

            <!-- Recursos dropdown -->
            <div class="nav-dropdown">
                <button class="nav-trigger">
                    Recursos
                    <svg class="chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>
                <div class="simple-dropdown">
                    <a href="chat-api.php" onclick="event.preventDefault(); alert('Use o chat lateral dentro do SQL Advisor.');">
                        <div class="sd-icon" style="background:#e6f4f4; color:var(--teal);">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" /></svg>
                        </div>
                        Chat IA Especialista
                    </a>
                    <div class="sd-sep"></div>
                    <a href="guia-exportacao-custos.php">
                        <div class="sd-icon" style="background:#e8f2fc; color:var(--blue);">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                        </div>
                        Guia Exportação de Custos (PDF)
                    </a>
                    <a href="assets/examples/exemplo-cost-management.csv" download>
                        <div class="sd-icon" style="background:#e8f2fc; color:var(--blue);">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                        </div>
                        Exemplo CSV (Cost Management)
                    </a>

                </div>
            </div>

            <!-- Sobre -->
            <a href="#" id="openAbout" onclick="event.preventDefault(); document.getElementById('aboutModal').classList.add('open');">Sobre</a>

        </nav>
    </header>

    <!-- Hero -->
    <section class="hero">
        <div class="hero-inner">
            <h2>Ferramentas Internas</h2>
            <p>Calculadoras, comparativos de licenciamento e utilitários de migração para o time de vendas e engenharia cloud.</p>
        </div>
    </section>

    <!-- Sales Team Banner -->
    <div style="max-width:1320px; margin:0 auto; padding:2rem 2rem 0;">
        <div style="background:#fff; border-radius:14px; border:1px solid #e5e5e5; padding:1.5rem 2rem; display:flex; align-items:center; gap:2rem; flex-wrap:wrap;">
            <div style="flex:1; min-width:280px;">
                <p style="font-size:.82rem; font-weight:600; color:var(--teal); text-transform:uppercase; letter-spacing:1px; margin-bottom:.35rem;">Para o Time de Vendas</p>
                <p style="font-size:.92rem; color:var(--charcoal); line-height:1.6;">Utilize nossas ferramentas para criar propostas, comparar modelos de licenciamento e apoiar os clientes nas melhores decisões de investimento em tecnologia.</p>
            </div>
            <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
                <span style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; background:#e8f2fc; border-radius:20px; font-size:.78rem; font-weight:600; color:#0078D4;">
                    <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="currentColor"><path d="M11.4 2H2v9.4h9.4V2zm0 10.6H2V22h9.4V12.6zM22 2h-9.4v9.4H22V2zm0 10.6h-9.4V22H22V12.6z"/></svg>
                    Microsoft
                </span>
                <span style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; background:#e6f4f4; border-radius:20px; font-size:.78rem; font-weight:600; color:var(--teal);">
                    <svg style="width:14px;height:14px;" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5.33492 1.37491C5.44717 1.04229 5.75909 0.818359 6.11014 0.818359H11.25L5.91513 16.6255C5.80287 16.9581 5.49095 17.182 5.13991 17.182H1.13968C0.579936 17.182 0.185466 16.6325 0.364461 16.1022L5.33492 1.37491Z" fill="url(#badge-az0)"/><path d="M13.5517 11.4546H5.45126C5.1109 11.4546 4.94657 11.8715 5.19539 12.1037L10.4005 16.9618C10.552 17.1032 10.7515 17.1819 10.9587 17.1819H15.5453L13.5517 11.4546Z" fill="#0078D4"/><path d="M6.11014 0.818359C5.75909 0.818359 5.44717 1.04229 5.33492 1.37491L0.364461 16.1022C0.185466 16.6325 0.579936 17.182 1.13968 17.182H5.13991C5.49095 17.182 5.80287 16.9581 5.91513 16.6255L6.90327 13.6976L10.4005 16.9617C10.552 17.1032 10.7515 17.1818 10.9588 17.1818H15.5454L13.5517 11.4545H7.66032L11.25 0.818359H6.11014Z" fill="url(#badge-az1)"/><path d="M12.665 1.37478C12.5528 1.04217 12.2409 0.818237 11.8898 0.818237H6.13629H6.16254C6.51358 0.818237 6.82551 1.04217 6.93776 1.37478L11.9082 16.1021C12.0872 16.6324 11.6927 17.1819 11.133 17.1819H11.0454H16.8603C17.42 17.1819 17.8145 16.6324 17.6355 16.1021L12.665 1.37478Z" fill="url(#badge-az2)"/><defs><linearGradient id="badge-az0" x1="6.07512" y1="1.38476" x2="0.738178" y2="17.1514" gradientUnits="userSpaceOnUse"><stop stop-color="#114A8B"/><stop offset="1" stop-color="#0669BC"/></linearGradient><linearGradient id="badge-az1" x1="10.3402" y1="11.4564" x2="9.107" y2="11.8734" gradientUnits="userSpaceOnUse"><stop stop-opacity="0.3"/><stop offset="0.0711768" stop-opacity="0.2"/><stop offset="0.321031" stop-opacity="0.1"/><stop offset="0.623053" stop-opacity="0.05"/><stop offset="1" stop-opacity="0"/></linearGradient><linearGradient id="badge-az2" x1="9.45858" y1="1.38467" x2="15.3168" y2="16.9926" gradientUnits="userSpaceOnUse"><stop stop-color="#3CCBF4"/><stop offset="1" stop-color="#2892DF"/></linearGradient></defs></svg>
                    Azure
                </span>
                <span style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; background:#fff3e8; border-radius:20px; font-size:.78rem; font-weight:600; color:#ea580c;">
                    <svg style="width:14px;height:14px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                    M365
                </span>
                <span style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; background:#ede9fe; border-radius:20px; font-size:.78rem; font-weight:600; color:#7c3aed;">
                    <svg style="width:14px;height:14px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18"><defs><linearGradient id="badge-sql-grad" x1="9.908" y1="15.943" x2="7.516" y2="2.383" gradientUnits="userSpaceOnUse"><stop offset="0.15" stop-color="#0078d4"/><stop offset="0.8" stop-color="#5ea0ef"/><stop offset="1" stop-color="#83b9f9"/></linearGradient></defs><path d="M14.49,7.15A5.147,5.147,0,0,0,9.24,2.164,5.272,5.272,0,0,0,4.216,5.653,4.869,4.869,0,0,0,0,10.4a4.946,4.946,0,0,0,5.068,4.814H13.82A4.292,4.292,0,0,0,18,11.127,4.105,4.105,0,0,0,14.49,7.15Z" fill="url(#badge-sql-grad)"/><path d="M12.9,11.4V8H12v4.13h2.46V11.4ZM5.76,9.73a1.825,1.825,0,0,1-.51-.31.441.441,0,0,1-.12-.32.342.342,0,0,1,.15-.3.683.683,0,0,1,.42-.12,1.62,1.62,0,0,1,1,.29V8.11a2.58,2.58,0,0,0-1-.16,1.641,1.641,0,0,0-1.09.34,1.08,1.08,0,0,0-.42.89c0,.51.32.91,1,1.21a2.907,2.907,0,0,1,.62.36.419.419,0,0,1,.15.32.381.381,0,0,1-.16.31.806.806,0,0,1-.45.11,1.66,1.66,0,0,1-1.09-.42V12a2.173,2.173,0,0,0,1.07.24,1.877,1.877,0,0,0,1.18-.33A1.08,1.08,0,0,0,6.84,11a1.048,1.048,0,0,0-.25-.7A2.425,2.425,0,0,0,5.76,9.73ZM11,11.32A2.191,2.191,0,0,0,11,9a1.808,1.808,0,0,0-.7-.75,2,2,0,0,0-1-.26,2.112,2.112,0,0,0-1.08.27A1.856,1.856,0,0,0,7.49,9a2.465,2.465,0,0,0-.26,1.14,2.256,2.256,0,0,0,.24,1,1.766,1.766,0,0,0,.69.74,2.056,2.056,0,0,0,1,.3l.86,1h1.21L10,12.08A1.79,1.79,0,0,0,11,11.32Zm-1-.25a.941.941,0,0,1-.76.35.916.916,0,0,1-.76-.36,1.523,1.523,0,0,1-.29-1,1.529,1.529,0,0,1,.29-1,1,1,0,0,1,.78-.37.869.869,0,0,1,.75.37,1.619,1.619,0,0,1,.27,1A1.459,1.459,0,0,1,10,11.07Z" fill="#f2f2f2"/></svg>
                    SQL Server
                </span>
                <span style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; background:#e6f4f4; border-radius:20px; font-size:.78rem; font-weight:600; color:var(--teal-dark);">
                    <svg style="width:14px;height:14px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                    Parceiros
                </span>
            </div>
        </div>
    </div>

    <!-- Cards -->
    <section class="cards-section">

        <!-- ═══ Microsoft ═══ -->
        <div class="vendor-section">
            <div class="vendor-header">
                <div class="vh-icon" style="background:#e8f2fc;">
                    <svg style="width:18px;height:18px;color:#0078D4;" viewBox="0 0 24 24" fill="currentColor"><path d="M11.4 2H2v9.4h9.4V2zm0 10.6H2V22h9.4V12.6zM22 2h-9.4v9.4H22V2zm0 10.6h-9.4V22H22V12.6z"/></svg>
                </div>
                <h3>Microsoft<small>Azure, M365, SQL Server, Parceiros</small></h3>
            </div>
            <div class="cards-grid">

                <!-- Card: Migração Azure -->
                <div id="migracao-azure" class="tool-card theme-azure">
                    <div class="card-accent"></div>
                    <div class="card-body">
                        <div class="card-icon">
                            <svg viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:24px;height:24px;"><path d="M5.33492 1.37491C5.44717 1.04229 5.75909 0.818359 6.11014 0.818359H11.25L5.91513 16.6255C5.80287 16.9581 5.49095 17.182 5.13991 17.182H1.13968C0.579936 17.182 0.185466 16.6325 0.364461 16.1022L5.33492 1.37491Z" fill="url(#paint0_linear_azure_card)"/><path d="M13.5517 11.4546H5.45126C5.1109 11.4546 4.94657 11.8715 5.19539 12.1037L10.4005 16.9618C10.552 17.1032 10.7515 17.1819 10.9587 17.1819H15.5453L13.5517 11.4546Z" fill="#0078D4"/><path d="M6.11014 0.818359C5.75909 0.818359 5.44717 1.04229 5.33492 1.37491L0.364461 16.1022C0.185466 16.6325 0.579936 17.182 1.13968 17.182H5.13991C5.49095 17.182 5.80287 16.9581 5.91513 16.6255L6.90327 13.6976L10.4005 16.9617C10.552 17.1032 10.7515 17.1818 10.9588 17.1818H15.5454L13.5517 11.4545H7.66032L11.25 0.818359H6.11014Z" fill="url(#paint1_linear_azure_card)"/><path d="M12.665 1.37478C12.5528 1.04217 12.2409 0.818237 11.8898 0.818237H6.13629H6.16254C6.51358 0.818237 6.82551 1.04217 6.93776 1.37478L11.9082 16.1021C12.0872 16.6324 11.6927 17.1819 11.133 17.1819H11.0454H16.8603C17.42 17.1819 17.8145 16.6324 17.6355 16.1021L12.665 1.37478Z" fill="url(#paint2_linear_azure_card)"/><defs><linearGradient id="paint0_linear_azure_card" x1="6.07512" y1="1.38476" x2="0.738178" y2="17.1514" gradientUnits="userSpaceOnUse"><stop stop-color="#114A8B"/><stop offset="1" stop-color="#0669BC"/></linearGradient><linearGradient id="paint1_linear_azure_card" x1="10.3402" y1="11.4564" x2="9.107" y2="11.8734" gradientUnits="userSpaceOnUse"><stop stop-opacity="0.3"/><stop offset="0.0711768" stop-opacity="0.2"/><stop offset="0.321031" stop-opacity="0.1"/><stop offset="0.623053" stop-opacity="0.05"/><stop offset="1" stop-opacity="0"/></linearGradient><linearGradient id="paint2_linear_azure_card" x1="9.45858" y1="1.38467" x2="15.3168" y2="16.9926" gradientUnits="userSpaceOnUse"><stop stop-color="#3CCBF4"/><stop offset="1" stop-color="#2892DF"/></linearGradient></defs></svg>
                        </div>
                        <h3 class="card-title">Migração Azure</h3>
                        <p class="card-desc">Ferramentas para auxiliar na migração entre diferentes modelos de licenciamento Azure.</p>
                        <ul class="card-links">
                            <li><a href="analise-financeira.php">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                Análise Financeira
                            </a></li>
                            <li><a href="analise-migracao.php">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                Análise Técnica (Cenários MOSP/EA)
                            </a></li>
                        </ul>
                    </div>
                </div>

                <!-- Card: SQL Licensing Advisor -->
                <div class="tool-card theme-sql">
                    <div class="card-accent"></div>
                    <span class="card-badge" style="background:var(--blue); color:#fff;">NOVO</span>
                    <div class="card-body">
                        <div class="card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" style="width:24px;height:24px;"><defs><linearGradient id="sql-card-grad" x1="9.908" y1="15.943" x2="7.516" y2="2.383" gradientUnits="userSpaceOnUse"><stop offset="0.15" stop-color="#0078d4"/><stop offset="0.8" stop-color="#5ea0ef"/><stop offset="1" stop-color="#83b9f9"/></linearGradient></defs><path d="M14.49,7.15A5.147,5.147,0,0,0,9.24,2.164,5.272,5.272,0,0,0,4.216,5.653,4.869,4.869,0,0,0,0,10.4a4.946,4.946,0,0,0,5.068,4.814H13.82A4.292,4.292,0,0,0,18,11.127,4.105,4.105,0,0,0,14.49,7.15Z" fill="url(#sql-card-grad)"/><path d="M12.9,11.4V8H12v4.13h2.46V11.4ZM5.76,9.73a1.825,1.825,0,0,1-.51-.31.441.441,0,0,1-.12-.32.342.342,0,0,1,.15-.3.683.683,0,0,1,.42-.12,1.62,1.62,0,0,1,1,.29V8.11a2.58,2.58,0,0,0-1-.16,1.641,1.641,0,0,0-1.09.34,1.08,1.08,0,0,0-.42.89c0,.51.32.91,1,1.21a2.907,2.907,0,0,1,.62.36.419.419,0,0,1,.15.32.381.381,0,0,1-.16.31.806.806,0,0,1-.45.11,1.66,1.66,0,0,1-1.09-.42V12a2.173,2.173,0,0,0,1.07.24,1.877,1.877,0,0,0,1.18-.33A1.08,1.08,0,0,0,6.84,11a1.048,1.048,0,0,0-.25-.7A2.425,2.425,0,0,0,5.76,9.73ZM11,11.32A2.191,2.191,0,0,0,11,9a1.808,1.808,0,0,0-.7-.75,2,2,0,0,0-1-.26,2.112,2.112,0,0,0-1.08.27A1.856,1.856,0,0,0,7.49,9a2.465,2.465,0,0,0-.26,1.14,2.256,2.256,0,0,0,.24,1,1.766,1.766,0,0,0,.69.74,2.056,2.056,0,0,0,1,.3l.86,1h1.21L10,12.08A1.79,1.79,0,0,0,11,11.32Zm-1-.25a.941.941,0,0,1-.76.35.916.916,0,0,1-.76-.36,1.523,1.523,0,0,1-.29-1,1.529,1.529,0,0,1,.29-1,1,1,0,0,1,.78-.37.869.869,0,0,1,.75.37,1.619,1.619,0,0,1,.27,1A1.459,1.459,0,0,1,10,11.07Z" fill="#f2f2f2"/></svg>
                        </div>
                        <h3 class="card-title">SQL Licensing Advisor</h3>
                        <p class="card-desc">Compare 6 modelos de licenciamento SQL Server 2022 e gere propostas comerciais em PDF.</p>
                        <ul class="card-links">
                            <li><a href="sql-advisor.php">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                Comparar Modelos (ARC, SPLA, CSP, OVS...)
                            </a></li>
                            <li><a href="sql-advisor.php">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                Gerar Proposta Comercial PDF
                            </a></li>
                            <li><a href="sql-advisor.php">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                Chat com Especialista IA
                            </a></li>
                        </ul>
                    </div>
                </div>

                <!-- Card: Migração M365 -->
                <div id="migracao-m365" class="tool-card theme-m365">
                    <div class="card-accent"></div>
                    <span class="card-badge" style="background:#ea580c; color:#fff;">EM BREVE</span>
                    <div class="card-body">
                        <div class="card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                            </svg>
                        </div>
                        <h3 class="card-title">Migração M365 - T1</h3>
                        <p class="card-desc">Ferramenta para migração de assinaturas Microsoft 365 de Tier 1 para Tier 2 (Indirect Reseller).</p>
                        <ul class="card-links">
                            <li><a href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                Migração Tier 1 &rarr; Tier 2
                            </a></li>
                            <li><a href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                Mapeamento de SKUs M365
                            </a></li>
                            <li><a href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                Proposta Comercial M365
                            </a></li>
                        </ul>
                    </div>
                </div>

                <!-- Card: Cloud Partner HUB -->
                <div id="cloud-partner-hub" class="tool-card theme-hub">
                    <div class="card-accent"></div>
                    <div class="card-body">
                        <div class="card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                        </div>
                        <h3 class="card-title">Cloud Partner HUB</h3>
                        <p class="card-desc">Portal de recursos e ferramentas para parceiros de nuvem.</p>
                        <ul class="card-links">
                            <li><a href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                Acessar Portal
                            </a></li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>

        <div class="vendor-divider"></div>

        <!-- ═══ Google Cloud ═══ -->
        <div class="vendor-section">
            <div class="vendor-header">
                <div class="vh-icon" style="background:#fef3e8;">
                    <svg style="width:18px;height:18px;" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                </div>
                <h3>Google Cloud<small>Em breve</small></h3>
            </div>
            <div style="background:#fff; border:1px dashed #d5d5d5; border-radius:12px; padding:2rem; text-align:center; color:var(--gray); font-size:.85rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:32px;height:32px;color:#d5d5d5;margin:0 auto .75rem;display:block;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                Ferramentas para Google Cloud Platform em desenvolvimento.
            </div>
        </div>

        <div class="vendor-divider"></div>

        <!-- ═══ AWS ═══ -->
        <div class="vendor-section">
            <div class="vendor-header">
                <div class="vh-icon" style="background:#fff3e0;">
                    <svg style="width:18px;height:18px;color:#FF9900;" viewBox="0 0 24 24" fill="currentColor"><path d="M6.763 10.036c0 .296.032.535.088.71.064.176.144.368.256.576a.391.391 0 0 1 .064.2.36.36 0 0 1-.176.288l-.592.384a.291.291 0 0 1-.16.064c-.064 0-.128-.032-.192-.08a3.97 3.97 0 0 1-.384-.496 2.326 2.326 0 0 1-.264-.608c-.672.784-1.504 1.176-2.512 1.176-.72 0-1.296-.208-1.712-.608-.416-.4-.632-.928-.632-1.584 0-.704.248-1.272.76-1.712.512-.44 1.192-.656 2.056-.656.288 0 .576.016.88.064.304.048.608.112.928.192v-.608c0-.624-.128-1.064-.4-1.312-.264-.256-.72-.376-1.36-.376-.288 0-.584.032-.888.112a6.458 6.458 0 0 0-.888.288 2.374 2.374 0 0 1-.28.112.384.384 0 0 1-.112.016c-.16 0-.24-.112-.24-.352V5.2a.72.72 0 0 1 .08-.352c.048-.064.144-.128.304-.192.288-.128.64-.24 1.04-.336a5.316 5.316 0 0 1 1.28-.144c.976 0 1.696.224 2.144.672.448.448.672 1.136.672 2.064v2.72h.016zm-3.472 1.296c.272 0 .56-.048.864-.16.304-.112.576-.304.8-.56a1.39 1.39 0 0 0 .288-.512c.048-.192.08-.416.08-.672v-.32a6.478 6.478 0 0 0-.736-.144 6.15 6.15 0 0 0-.752-.048c-.56 0-.976.112-1.248.336-.272.224-.4.544-.4.96 0 .384.096.672.304.88.192.208.48.312.832.24h-.032zm6.88.912c-.208 0-.336-.032-.416-.112-.08-.064-.16-.208-.224-.4L7.456 5.2a1.748 1.748 0 0 1-.096-.416c0-.16.08-.256.24-.256h.928c.208 0 .352.032.424.112.08.064.144.208.208.4l1.488 5.888 1.376-5.888c.048-.208.112-.336.208-.4.08-.064.24-.112.432-.112h.752c.208 0 .352.032.432.112.08.064.16.208.208.4l1.392 5.968 1.536-5.968c.064-.208.144-.336.208-.4.08-.064.224-.112.424-.112h.88c.16 0 .256.08.256.256 0 .048-.016.096-.032.16a1.433 1.433 0 0 1-.064.272l-2.144 6.528c-.064.208-.144.336-.224.4-.08.064-.224.112-.416.112h-.816c-.208 0-.352-.032-.432-.112-.08-.08-.16-.208-.208-.416l-1.376-5.744-1.36 5.728c-.048.208-.128.336-.208.416-.08.08-.24.112-.432.112h-.816v.016zm11.008.272c-.432 0-.864-.048-1.28-.16-.416-.112-.736-.224-.944-.352-.128-.08-.208-.16-.24-.256a.622.622 0 0 1-.048-.24v-.4c0-.24.096-.352.272-.352a.69.69 0 0 1 .208.032c.064.032.16.08.272.128.368.176.768.32 1.184.416.432.096.848.144 1.28.144.672 0 1.2-.112 1.568-.352.368-.24.56-.576.56-1.008 0-.288-.096-.528-.288-.72-.192-.192-.56-.368-1.088-.528l-1.568-.48c-.784-.24-1.36-.592-1.712-1.064a2.388 2.388 0 0 1-.528-1.488c0-.432.096-.816.288-1.136.192-.32.448-.592.768-.816.32-.224.688-.384 1.104-.496a4.884 4.884 0 0 1 1.344-.176c.24 0 .48.016.736.048.24.032.48.08.704.128.208.064.416.128.608.192.192.08.336.16.432.24.128.08.224.16.272.256a.505.505 0 0 1 .064.272v.368c0 .24-.096.368-.272.368a1.252 1.252 0 0 1-.448-.16 5.463 5.463 0 0 0-2.272-.464c-.608 0-1.088.096-1.424.304-.336.208-.512.528-.512.976 0 .304.112.56.32.752.208.192.608.384 1.168.544l1.536.464c.768.24 1.328.576 1.664 1.016.336.44.496.944.496 1.504 0 .448-.096.848-.272 1.184a2.86 2.86 0 0 1-.768.88c-.336.24-.72.432-1.168.56-.464.128-.96.192-1.504.192z"/><path fill="#FF9900" d="M21.384 18.736c-2.648 1.968-6.504 3.008-9.816 3.008-4.64 0-8.824-1.712-11.984-4.56-.256-.224-.016-.528.272-.352 3.408 1.984 7.632 3.168 11.992 3.168 2.944 0 6.176-.608 9.152-1.872.448-.192.832.304.384.608z"/></svg>
                </div>
                <h3>AWS<small>Em breve</small></h3>
            </div>
            <div style="background:#fff; border:1px dashed #d5d5d5; border-radius:12px; padding:2rem; text-align:center; color:var(--gray); font-size:.85rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:32px;height:32px;color:#d5d5d5;margin:0 auto .75rem;display:block;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                Ferramentas para Amazon Web Services em desenvolvimento.
            </div>
        </div>

    <footer class="page-footer">TD SYNNEX &mdash; Uso interno</footer>

    <!-- About Modal -->
    <div class="about-overlay" id="aboutModal">
        <div class="about-box">
            <button class="about-close" onclick="document.getElementById('aboutModal').classList.remove('open');">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
            <div class="about-header">
                <img src="assets/images/logo.png" alt="TD SYNNEX">
                <h3>TD SYNNEX Tools</h3>
                <p>Plataforma interna de ferramentas para o time de vendas/engenharia Cloud</p>
            </div>
            <div class="about-body">
                <div class="about-item">
                    <div class="ai-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                    </div>
                    <div>
                        <div class="ai-label">Versão</div>
                        <div class="ai-value">3.0.0</div>
                    </div>
                </div>
                <div class="about-item">
                    <div class="ai-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    </div>
                    <div>
                        <div class="ai-label">Stack</div>
                        <div class="ai-value">PHP 8.0+ &bull; Tailwind CSS &bull; jsPDF &bull; OpenAI GPT-4o</div>
                    </div>
                </div>
                <div class="about-item">
                    <div class="ai-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z" /></svg>
                    </div>
                    <div>
                        <div class="ai-label">Funcionalidades</div>
                        <div class="ai-value">Migração Azure &bull; SQL Licensing Advisor &bull; Análise Financeira &bull; Migração M365 &bull; Análise Técnica de Recursos</div>
                    </div>
                </div>
                <div class="about-item">
                    <div class="ai-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                    </div>
                    <div>
                        <div class="ai-label">Equipe</div>
                        <div class="ai-value">TD SYNNEX &mdash; Time de Vendas Cloud</div>
                    </div>
                </div>
                <div class="about-item">
                    <div class="ai-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                    </div>
                    <div>
                        <div class="ai-label">Suporte</div>
                        <div class="ai-value">Entre em contato com a equipe de engenharia Cloud</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

  <script>
    // About Modal
    const aboutModal = document.getElementById('aboutModal');
    aboutModal.addEventListener('click', (e) => {
        if (e.target === aboutModal) aboutModal.classList.remove('open');
    });
  </script>
</body>
</html>
