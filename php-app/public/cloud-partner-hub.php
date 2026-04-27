<?php
/**
 * Cloud Partner Hub - Dashboard Principal
 * Gestão de parceiros Microsoft com análise de PCS e benefícios
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/Features/CloudPartnerHub/Services/PartnerService.php';
require_once __DIR__ . '/../src/Features/CloudPartnerHub/Config/constants.php';
require_once __DIR__ . '/../src/Features/CloudPartnerHub/Config/benefits.php';

use Features\CloudPartnerHub\Services\PartnerService;
use Features\CloudPartnerHub\Config\Constants;
use Features\CloudPartnerHub\Config\Benefits;

$service = new PartnerService();
$partners = $service->getAll();
$stats = $service->getStats();
$solutionAreas = Constants::SOLUTION_AREAS;
$edgeStages = Constants::EDGE_STAGES;
$journeySteps = Constants::JOURNEY_STEPS;
$benefits = Benefits::BENEFITS;
$benefitCategories = Constants::BENEFIT_CATEGORIES;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloud Partner Hub | TD SYNNEX</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --teal: #005758;
            --teal-dark: #003031;
            --blue: #0078D4;
            --charcoal: #262626;
            --gray: #6b7280;
            --light-gray: #f8fafc;
            --border: #e5e7eb;
            --green: #059669;
            --orange: #d97706;
            --purple: #7c3aed;
        }

        .page-content { 
            padding: 1.5rem 2rem 3rem; 
            background: var(--light-gray); 
            min-height: calc(100vh - 56px);
            max-width: 1100px;
            margin: 0 auto;
        }

        /* Header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        .page-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--charcoal);
        }
        .page-subtitle {
            color: var(--gray);
            font-size: .8rem;
            margin-top: 2px;
        }

        /* Tabs */
        .hub-tabs {
            display: flex;
            gap: 0;
            background: #fff;
            border-radius: 6px 6px 0 0;
            border: 1px solid var(--border);
            border-bottom: none;
        }
        .hub-tab {
            padding: .875rem 1.25rem;
            font-size: .8rem;
            font-weight: 600;
            color: var(--gray);
            background: transparent;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all .15s;
            border-bottom: 2px solid transparent;
        }
        .hub-tab:hover { background: #fafafa; color: var(--charcoal); }
        .hub-tab.active {
            color: var(--teal);
            border-bottom-color: var(--teal);
            background: #fff;
        }
        .hub-tab svg { width: 16px; height: 16px; }

        .hub-panel {
            display: none;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 0 0 6px 6px;
            padding: 1.25rem;
        }
        .hub-panel.active { display: block; }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .kpi-card {
            background: #fff;
            border-radius: 8px;
            padding: 1.25rem 1.5rem;
            border: 1px solid var(--border);
            border-left: 4px solid var(--teal);
        }
        .kpi-card.blue { border-left-color: var(--blue); }
        .kpi-card.green { border-left-color: var(--green); }
        .kpi-card.purple { border-left-color: var(--purple); }
        .kpi-value {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 4px;
            color: var(--charcoal);
        }
        .kpi-label { font-size: .75rem; color: var(--gray); font-weight: 500; text-transform: uppercase; letter-spacing: .4px; }
        .kpi-trend {
            font-size: .72rem;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .kpi-trend.up { color: var(--green); }

        /* Pipeline */
        .pipeline-section {
            background: #fff;
            border-radius: 8px;
            border: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .pipeline-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: .75rem;
            border-bottom: 1px solid var(--border);
        }
        .pipeline-header h3 {
            font-size: .88rem;
            font-weight: 600;
            color: var(--charcoal);
        }
        .pipeline-stages {
            display: flex;
            gap: 12px;
        }
        .pipeline-stage {
            flex: 1;
            padding: 1rem 1.25rem;
            text-align: center;
            cursor: pointer;
            transition: all .15s;
            background: #fafafa;
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        .pipeline-stage:hover { background: #f0f0f0; border-color: #d0d0d0; }
        .pipeline-stage.engage { border-top: 3px solid #f59e0b; }
        .pipeline-stage.develop { border-top: 3px solid #3b82f6; }
        .pipeline-stage.growth { border-top: 3px solid #10b981; }
        .pipeline-stage.extend { border-top: 3px solid #8b5cf6; }
        .pipeline-stage-name {
            font-size: .72rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 6px;
        }
        .pipeline-stage-count {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--charcoal);
        }
        .pipeline-stage-pct {
            font-size: .72rem;
            color: var(--gray);
            margin-top: 2px;
        }

        /* Filtros */
        .filters-bar {
            display: flex;
            gap: .75rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .search-box {
            flex: 1;
            min-width: 220px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: .82rem;
            font-family: inherit;
        }
        .search-box input:focus {
            outline: none;
            border-color: var(--teal);
        }
        .search-box svg {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            color: var(--gray);
        }
        .filter-select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: .82rem;
            font-family: inherit;
            background: #fff;
            min-width: 140px;
        }
        .btn-primary {
            background: var(--teal);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
        }
        .btn-primary:hover { background: var(--teal-dark); }
        .btn-primary svg { width: 16px; height: 16px; }

        /* Tabela */
        .partners-table-wrapper {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 6px;
        }
        .partners-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .82rem;
        }
        .partners-table th,
        .partners-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        .partners-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--gray);
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            white-space: nowrap;
            position: sticky;
            top: 0;
        }
        .partners-table tbody tr { transition: background .1s; }
        .partners-table tbody tr:hover { background: #f8fafc; }
        .partners-table .company-cell {
            font-weight: 600;
            color: var(--charcoal);
            font-size: .85rem;
        }
        .partners-table .email-cell {
            color: var(--gray);
            font-size: .78rem;
            margin-top: 2px;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: .7rem;
            font-weight: 600;
        }
        .badge-engage { background: #fef3c7; color: #92400e; }
        .badge-develop { background: #dbeafe; color: #1e40af; }
        .badge-growth { background: #d1fae5; color: #065f46; }
        .badge-extend { background: #ede9fe; color: #5b21b6; }
        .badge-azure { background: #eff6ff; color: var(--blue); }
        .badge-modern { background: #fdf2f8; color: #9d174d; }
        .badge-security { background: #fef2f2; color: #991b1b; }
        .badge-data { background: #f5f3ff; color: #6d28d9; }
        .badge-digital { background: #f0fdf4; color: #166534; }
        .badge-business { background: #fefce8; color: #854d0e; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }

        /* PCS Bar */
        .pcs-bar {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pcs-bar-track {
            flex: 1;
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
            min-width: 50px;
        }
        .pcs-bar-fill {
            height: 100%;
            border-radius: 3px;
        }
        .pcs-bar-value {
            font-weight: 600;
            font-size: .8rem;
            min-width: 28px;
        }

        /* Actions */
        .action-btn {
            padding: 5px;
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: 4px;
            color: var(--gray);
        }
        .action-btn:hover {
            background: #f0f0f0;
            color: var(--charcoal);
        }
        .action-btn svg { width: 16px; height: 16px; }

        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.4);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all .15s;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal {
            background: #fff;
            border-radius: 8px;
            max-width: 720px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
        }
        .modal-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h2 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--charcoal);
        }
        .modal-close {
            padding: 6px;
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: 4px;
            color: var(--gray);
        }
        .modal-close:hover { background: #f0f0f0; }
        .modal-close svg { width: 18px; height: 18px; }
        .modal-body { padding: 1.25rem; }
        .modal-footer {
            padding: .875rem 1.25rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: .5rem;
        }

        /* Detail Grid */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: .75rem;
        }
        .detail-item {
            padding: .875rem;
            background: #fafafa;
            border-radius: 4px;
        }
        .detail-label {
            font-size: .68rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 3px;
        }
        .detail-value {
            font-size: .85rem;
            font-weight: 600;
            color: var(--charcoal);
        }

        /* Benefits */
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: .875rem;
        }
        .benefit-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 1rem;
            transition: border-color .15s;
        }
        .benefit-card:hover { border-color: var(--teal); }
        .benefit-icon {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: .75rem;
        }
        .benefit-icon svg { width: 18px; height: 18px; color: #fff; }
        .benefit-icon.tool { background: var(--blue); }
        .benefit-icon.training { background: var(--purple); }
        .benefit-icon.funding { background: var(--green); }
        .benefit-icon.support { background: var(--orange); }
        .benefit-icon.marketing { background: #db2777; }
        .benefit-name {
            font-size: .85rem;
            font-weight: 600;
            color: var(--charcoal);
            margin-bottom: .375rem;
        }
        .benefit-desc {
            font-size: .75rem;
            color: var(--gray);
            line-height: 1.5;
            margin-bottom: .5rem;
        }
        .benefit-value {
            font-size: .78rem;
            font-weight: 600;
            color: var(--teal);
        }
        .benefit-new {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--orange);
            color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: .65rem;
            font-weight: 700;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .page-content { padding: 1rem; }
            .kpi-grid { grid-template-columns: 1fr; }
            .pipeline-stages { flex-wrap: wrap; gap: 8px; }
            .pipeline-stage { flex: 1 1 45%; min-width: 120px; }
            .detail-grid { grid-template-columns: 1fr; }
            .filters-bar { flex-direction: column; }
            .search-box { min-width: 100%; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../templates/topbar.php'; ?>

<main class="page-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Cloud Partner Hub</h1>
            <p class="page-subtitle">Gestão de parceiros Microsoft com análise de PCS Score</p>
        </div>
        <button class="btn-primary" onclick="openNewPartnerModal()">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/>
            </svg>
            Novo Parceiro
        </button>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-value"><?= $stats['totalPartners'] ?></div>
            <div class="kpi-label">Total de Parceiros</div>
            <div class="kpi-trend up">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 7-7 7 7"/></svg>
                +12% este mês
            </div>
        </div>
        <div class="kpi-card blue">
            <div class="kpi-value">R$ <?= number_format($stats['totalRevenue'], 0, ',', '.') ?></div>
            <div class="kpi-label">Receita Total CSP</div>
            <div class="kpi-trend up">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 7-7 7 7"/></svg>
                +8% este mês
            </div>
        </div>
        <div class="kpi-card green">
            <div class="kpi-value"><?= $stats['avgPCS'] ?></div>
            <div class="kpi-label">PCS Score Médio</div>
        </div>
        <div class="kpi-card purple">
            <div class="kpi-value"><?= $stats['byStage']['Extend'] + $stats['byStage']['Growth'] ?></div>
            <div class="kpi-label">Growth + Extend</div>
        </div>
    </div>

    <!-- Pipeline Edge -->
    <div class="pipeline-section">
        <div class="pipeline-header">
            <h3>Pipeline de Parceiros - Programa Edge</h3>
        </div>
        <div class="pipeline-stages">
            <?php 
            $total = max(1, $stats['totalPartners']);
            foreach ($edgeStages as $stage): 
                $count = $stats['byStage'][$stage] ?? 0;
                $pct = round(($count / $total) * 100, 1);
                $stageClass = strtolower($stage);
            ?>
            <div class="pipeline-stage <?= $stageClass ?>" onclick="filterByStage('<?= $stage ?>')">
                <div class="pipeline-stage-name"><?= $stage ?></div>
                <div class="pipeline-stage-count"><?= $count ?></div>
                <div class="pipeline-stage-pct"><?= $pct ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tabs -->
    <div class="hub-tabs">
        <button class="hub-tab active" onclick="switchTab('partners')">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Parceiros
        </button>
        <button class="hub-tab" onclick="switchTab('benefits')">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2 2 7l10 5 10-5-10-5Z"/>
                <path d="m2 17 10 5 10-5"/>
                <path d="m2 12 10 5 10-5"/>
            </svg>
            Benefícios
        </button>
        <button class="hub-tab" onclick="switchTab('skilling')">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                <path d="M6 12v5c3 3 9 3 12 0v-5"/>
            </svg>
            Skilling
        </button>
    </div>

    <!-- Tab: Parceiros -->
    <div class="hub-panel active" id="panel-partners">
        <div class="filters-bar">
            <div class="search-box">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.3-4.3"/>
                </svg>
                <input type="text" id="searchInput" placeholder="Buscar parceiro por nome ou email..." oninput="filterPartners()">
            </div>
            <select class="filter-select" id="filterStage" onchange="filterPartners()">
                <option value="all">Todos os Estágios</option>
                <?php foreach ($edgeStages as $stage): ?>
                <option value="<?= $stage ?>"><?= $stage ?></option>
                <?php endforeach; ?>
            </select>
            <select class="filter-select" id="filterArea" onchange="filterPartners()">
                <option value="all">Todas as Áreas</option>
                <?php foreach ($solutionAreas as $area): ?>
                <option value="<?= $area ?>"><?= $area ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="partners-table-wrapper">
            <table class="partners-table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Contato</th>
                        <th>Solution Area</th>
                        <th>PCS Score</th>
                        <th>Estágio Edge</th>
                        <th>Receita Total</th>
                        <th>Etapa</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="partnersTableBody">
                    <?php foreach ($partners as $p): 
                        $pcsScore = $p['pcsScore'] ?? 0;
                        $stage = $p['edgeStage'] ?? 'Engage';
                        $stageClass = strtolower($stage);
                        $areaClass = match($p['solutionArea'] ?? '') {
                            'Azure Infra' => 'azure',
                            'Modern Work' => 'modern',
                            'Security' => 'security',
                            'Data & AI' => 'data',
                            'Digital & App Innovation' => 'digital',
                            'Business Applications' => 'business',
                            default => ''
                        };
                        $statusClass = match($p['status'] ?? '') {
                            'Completed' => 'success',
                            'Stalled' => 'danger',
                            default => 'warning'
                        };
                        $pcsColor = $pcsScore >= 70 ? 'var(--green)' : ($pcsScore >= 40 ? 'var(--blue)' : 'var(--orange)');
                    ?>
                    <tr data-id="<?= htmlspecialchars($p['id']) ?>" 
                        data-stage="<?= $stage ?>" 
                        data-area="<?= htmlspecialchars($p['solutionArea'] ?? '') ?>"
                        data-search="<?= strtolower(htmlspecialchars($p['companyName'] ?? '')) ?> <?= strtolower(htmlspecialchars($p['email'] ?? '')) ?>">
                        <td>
                            <div class="company-cell"><?= htmlspecialchars($p['companyName'] ?? '') ?></div>
                            <div class="email-cell"><?= htmlspecialchars($p['email'] ?? '') ?></div>
                        </td>
                        <td><?= htmlspecialchars($p['contactName'] ?? '') ?></td>
                        <td><span class="badge badge-<?= $areaClass ?>"><?= htmlspecialchars($p['solutionArea'] ?? '-') ?></span></td>
                        <td>
                            <div class="pcs-bar">
                                <div class="pcs-bar-track">
                                    <div class="pcs-bar-fill" style="width:<?= min(100, $pcsScore) ?>%;background:<?= $pcsColor ?>;"></div>
                                </div>
                                <span class="pcs-bar-value" style="color:<?= $pcsColor ?>;"><?= $pcsScore ?></span>
                            </div>
                        </td>
                        <td><span class="badge badge-<?= $stageClass ?>"><?= $stage ?></span></td>
                        <td>R$ <?= number_format($p['totalRevenue'] ?? 0, 0, ',', '.') ?></td>
                        <td><?= $p['currentStep'] ?? 1 ?>/6</td>
                        <td><span class="badge badge-<?= $statusClass ?>"><?= $p['status'] ?? 'In Progress' ?></span></td>
                        <td>
                            <button class="action-btn" onclick="viewPartner('<?= htmlspecialchars($p['id']) ?>')" title="Ver detalhes">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab: Benefícios -->
    <div class="hub-panel" id="panel-benefits">
        <div class="filters-bar" style="margin-bottom:1.5rem;">
            <select class="filter-select" id="filterBenefitCategory" onchange="filterBenefits()">
                <option value="all">Todas as Categorias</option>
                <?php foreach ($benefitCategories as $key => $label): ?>
                <option value="<?= $key ?>"><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <select class="filter-select" id="filterBenefitArea" onchange="filterBenefits()">
                <option value="all">Todas as Áreas</option>
                <?php foreach ($solutionAreas as $area): ?>
                <option value="<?= $area ?>"><?= $area ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="benefits-grid" id="benefitsGrid">
            <?php foreach ($benefits as $b): ?>
            <div class="benefit-card" 
                 data-category="<?= $b['category'] ?>" 
                 data-areas="<?= implode(',', $b['solutionAreas']) ?>">
                <?php if ($b['isNew'] ?? false): ?>
                <div class="benefit-new">NOVO</div>
                <?php endif; ?>
                <div class="benefit-icon <?= $b['category'] ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <?php 
                        $iconPath = match($b['icon']) {
                            'cloud' => '<path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>',
                            'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
                            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                            'zap' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
                            'sparkles' => '<path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/>',
                            'graduation-cap' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
                            'building' => '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
                            'code' => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
                            'dollar-sign' => '<line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
                            'megaphone' => '<path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
                            'trending-up' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
                            'headphones' => '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>',
                            'user-check' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
                            'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/>',
                            'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
                            'external-link' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" x2="21" y1="14" y2="3"/>',
                            'video' => '<path d="m22 8-6 4 6 4V8Z"/><rect width="14" height="12" x="2" y="6" rx="2" ry="2"/>',
                            default => '<circle cx="12" cy="12" r="10"/>'
                        };
                        echo $iconPath;
                        ?>
                    </svg>
                </div>
                <div class="benefit-name"><?= htmlspecialchars($b['name']) ?></div>
                <div class="benefit-desc"><?= htmlspecialchars($b['description']) ?></div>
                <div class="benefit-value"><?= htmlspecialchars($b['value'] ?? '') ?></div>
                <div style="margin-top:.75rem;display:flex;flex-wrap:wrap;gap:4px;">
                    <?php foreach ($b['solutionAreas'] as $area): 
                        $areaClass = match($area) {
                            'Azure Infra' => 'azure',
                            'Modern Work' => 'modern',
                            'Security' => 'security',
                            'Data & AI' => 'data',
                            'Digital & App Innovation' => 'digital',
                            'Business Applications' => 'business',
                            default => ''
                        };
                    ?>
                    <span class="badge badge-<?= $areaClass ?>" style="font-size:.68rem;"><?= $area ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tab: Skilling -->
    <div class="hub-panel" id="panel-skilling">
        <div style="text-align:center;padding:3rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gray);margin-bottom:1rem;">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                <path d="M6 12v5c3 3 9 3 12 0v-5"/>
            </svg>
            <h3 style="font-size:1.1rem;font-weight:700;color:var(--charcoal);margin-bottom:.5rem;">Trilhas de Certificação</h3>
            <p style="color:var(--gray);font-size:.88rem;max-width:500px;margin:0 auto;">
                Visualize as certificações recomendadas por Solution Area e acompanhe o progresso dos parceiros.
            </p>
        </div>
    </div>
</main>

<!-- Modal: Detalhes do Parceiro -->
<div class="modal-overlay" id="partnerDetailModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modalPartnerName">Detalhes do Parceiro</h2>
            <button class="modal-close" onclick="closeModal('partnerDetailModal')">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="modalPartnerBody">
            <!-- Conteúdo carregado via JS -->
        </div>
    </div>
</div>

<!-- Modal: Novo Parceiro -->
<div class="modal-overlay" id="newPartnerModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Novo Parceiro</h2>
            <button class="modal-close" onclick="closeModal('newPartnerModal')">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="newPartnerForm" onsubmit="createPartner(event)">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label class="detail-label">Nome da Empresa *</label>
                        <input type="text" name="companyName" required style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px;font-size:.88rem;">
                    </div>
                    <div class="detail-item">
                        <label class="detail-label">Nome do Contato *</label>
                        <input type="text" name="contactName" required style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px;font-size:.88rem;">
                    </div>
                    <div class="detail-item">
                        <label class="detail-label">Email *</label>
                        <input type="email" name="email" required style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px;font-size:.88rem;">
                    </div>
                    <div class="detail-item">
                        <label class="detail-label">Telefone</label>
                        <input type="text" name="phone" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px;font-size:.88rem;">
                    </div>
                    <div class="detail-item">
                        <label class="detail-label">MPN ID</label>
                        <input type="text" name="mpnId" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px;font-size:.88rem;">
                    </div>
                    <div class="detail-item">
                        <label class="detail-label">Solution Area</label>
                        <select name="solutionArea" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px;font-size:.88rem;">
                            <option value="">Selecione...</option>
                            <?php foreach ($solutionAreas as $area): ?>
                            <option value="<?= $area ?>"><?= $area ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="closeModal('newPartnerModal')" style="padding:10px 20px;border:1px solid #e5e7eb;background:#fff;border-radius:8px;cursor:pointer;font-family:inherit;">Cancelar</button>
            <button type="submit" form="newPartnerForm" class="btn-primary">Criar Parceiro</button>
        </div>
    </div>
</div>

<script>
// Dados do PHP para JS
const partnersData = <?= json_encode($partners) ?>;
const solutionAreas = <?= json_encode($solutionAreas) ?>;
const edgeStages = <?= json_encode($edgeStages) ?>;

// Tabs
function switchTab(tabId) {
    document.querySelectorAll('.hub-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.hub-panel').forEach(p => p.classList.remove('active'));
    
    document.querySelector(`.hub-tab[onclick="switchTab('${tabId}')"]`).classList.add('active');
    document.getElementById(`panel-${tabId}`).classList.add('active');
}

// Filtro de parceiros
function filterPartners() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const stage = document.getElementById('filterStage').value;
    const area = document.getElementById('filterArea').value;
    
    document.querySelectorAll('#partnersTableBody tr').forEach(row => {
        const rowSearch = row.dataset.search || '';
        const rowStage = row.dataset.stage || '';
        const rowArea = row.dataset.area || '';
        
        const matchSearch = search === '' || rowSearch.includes(search);
        const matchStage = stage === 'all' || rowStage === stage;
        const matchArea = area === 'all' || rowArea === area;
        
        row.style.display = (matchSearch && matchStage && matchArea) ? '' : 'none';
    });
}

// Filtro por stage (clicando no pipeline)
function filterByStage(stage) {
    document.getElementById('filterStage').value = stage;
    filterPartners();
    switchTab('partners');
}

// Filtro de benefícios
function filterBenefits() {
    const category = document.getElementById('filterBenefitCategory').value;
    const area = document.getElementById('filterBenefitArea').value;
    
    document.querySelectorAll('#benefitsGrid .benefit-card').forEach(card => {
        const cardCategory = card.dataset.category || '';
        const cardAreas = (card.dataset.areas || '').split(',');
        
        const matchCategory = category === 'all' || cardCategory === category;
        const matchArea = area === 'all' || cardAreas.includes(area);
        
        card.style.display = (matchCategory && matchArea) ? '' : 'none';
    });
}

// Modais
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function openNewPartnerModal() {
    document.getElementById('newPartnerForm').reset();
    openModal('newPartnerModal');
}

// Ver detalhes do parceiro
function viewPartner(id) {
    const partner = partnersData.find(p => p.id === id);
    if (!partner) return;
    
    document.getElementById('modalPartnerName').textContent = partner.companyName || 'Parceiro';
    
    const pcsScore = partner.pcsScore || 0;
    const stage = partner.edgeStage || 'Engage';
    const pcsColor = pcsScore >= 70 ? 'var(--green)' : (pcsScore >= 40 ? 'var(--blue)' : 'var(--orange)');
    
    const html = `
        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">Empresa</div>
                <div class="detail-value">${partner.companyName || '-'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Contato</div>
                <div class="detail-value">${partner.contactName || '-'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Email</div>
                <div class="detail-value">${partner.email || '-'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Telefone</div>
                <div class="detail-value">${partner.phone || partner.contactPhone || '-'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">MPN ID</div>
                <div class="detail-value">${partner.mpnId || '-'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Solution Area</div>
                <div class="detail-value">${partner.solutionArea || '-'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">PCS Score</div>
                <div class="detail-value">
                    <div class="pcs-bar">
                        <div class="pcs-bar-track" style="width:120px;">
                            <div class="pcs-bar-fill" style="width:${Math.min(100, pcsScore)}%;background:${pcsColor};"></div>
                        </div>
                        <span class="pcs-bar-value" style="color:${pcsColor};">${pcsScore}</span>
                    </div>
                </div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Estágio Edge</div>
                <div class="detail-value"><span class="badge badge-${stage.toLowerCase()}">${stage}</span></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Receita M365</div>
                <div class="detail-value">R$ ${(partner.revenueM365 || 0).toLocaleString('pt-BR')}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Receita Azure</div>
                <div class="detail-value">R$ ${(partner.revenueAzure || 0).toLocaleString('pt-BR')}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Receita Security</div>
                <div class="detail-value">R$ ${(partner.revenueSecurity || 0).toLocaleString('pt-BR')}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Receita Total</div>
                <div class="detail-value" style="color:var(--teal);font-size:1.1rem;">R$ ${(partner.totalRevenue || 0).toLocaleString('pt-BR')}</div>
            </div>
        </div>
        
        <div style="margin-top:1.5rem;">
            <div class="detail-label" style="margin-bottom:.75rem;">Certificações</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                ${(partner.certifications || []).map(c => `<span class="badge badge-azure">${c}</span>`).join('') || '<span style="color:var(--gray);font-size:.82rem;">Nenhuma certificação registrada</span>'}
            </div>
        </div>
        
        <div style="margin-top:1.5rem;">
            <div class="detail-label" style="margin-bottom:.75rem;">Progresso na Jornada</div>
            <div style="display:flex;gap:8px;align-items:center;">
                ${[1,2,3,4,5,6].map(step => `
                    <div style="width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;${step <= (partner.currentStep || 1) ? 'background:var(--teal);color:#fff;' : 'background:#e5e7eb;color:var(--gray);'}">${step}</div>
                `).join('<div style="flex:1;height:2px;background:#e5e7eb;"></div>')}
            </div>
        </div>
    `;
    
    document.getElementById('modalPartnerBody').innerHTML = html;
    openModal('partnerDetailModal');
}

// Criar parceiro
async function createPartner(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const response = await fetch('cloud-partner-hub-api.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeModal('newPartnerModal');
            window.location.reload();
        } else {
            alert('Erro: ' + (result.error || 'Falha ao criar parceiro'));
        }
    } catch (err) {
        alert('Erro de conexão: ' + err.message);
    }
}

// Fechar modal ao clicar fora
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.classList.remove('active');
        }
    });
});
</script>
</body>
</html>
