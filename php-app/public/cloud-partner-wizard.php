<?php
/**
 * Cloud Partner Hub - Wizard de Onboarding
 * Fluxo completo de cadastro de parceiros em 6 etapas
 */

declare(strict_types=1);
session_start();

// Initialize i18n
require_once __DIR__ . '/../src/Shared/Services/i18n-bootstrap.php';

require_once __DIR__ . '/../src/Features/CloudPartnerHub/Config/constants.php';
require_once __DIR__ . '/../src/Features/CloudPartnerHub/Config/benefits.php';
require_once __DIR__ . '/../src/Features/CloudPartnerHub/Services/RoadmapGenerator.php';

use Features\CloudPartnerHub\Config\Constants;
use Features\CloudPartnerHub\Config\Benefits;
use Features\CloudPartnerHub\Services\RoadmapGenerator;

// Verificar se veio do onboarding
$company = $_SESSION['onboarding_company'] ?? ($_GET['company'] ?? '');
$email = $_SESSION['onboarding_email'] ?? '';

// Passo atual (padrão: 2, range: 2-6)
$step = isset($_GET['step']) ? max(2, min(6, (int)$_GET['step'])) : 2;

// Dados do formulário (persistidos na sessão)
$formData = $_SESSION['wizard_form_data'] ?? [
    'companyName' => $company,
    'email' => $email,
    'isTdSynnexRegistered' => false,
    'isMicrosoftPartner' => false,
    'partnerTypeInterest' => '',
    'selectedSolutionArea' => '',
    'cspRevenue' => '',
    'clientCount' => '',
    'pcsPerformance' => 0,
    'pcsSkilling' => 0,
    'pcsCustomerSuccess' => 0,
    'certifications' => [],
    'contactName' => '',
    'contactRole' => '',
    'contactPhone' => '',
    'mpnId' => '',
];

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Atualizar dados do formulário
    foreach ($_POST as $key => $value) {
        if ($key !== 'action' && $key !== 'step') {
            if ($key === 'certifications' && is_array($value)) {
                $formData['certifications'] = array_map('intval', $value);
            } elseif (in_array($key, ['isTdSynnexRegistered', 'isMicrosoftPartner'])) {
                $formData[$key] = $value === '1' || $value === 'true';
            } elseif (in_array($key, ['pcsPerformance', 'pcsSkilling', 'pcsCustomerSuccess'])) {
                $formData[$key] = (float)$value;
            } else {
                $formData[$key] = is_string($value) ? trim($value) : $value;
            }
        }
    }
    
    $_SESSION['wizard_form_data'] = $formData;
    
    // Navegação
    if ($action === 'next' && $step < 6) {
        header('Location: cloud-partner-wizard.php?step=' . ($step + 1));
        exit;
    } elseif ($action === 'prev' && $step > 2) {
        header('Location: cloud-partner-wizard.php?step=' . ($step - 1));
        exit;
    } elseif ($action === 'finish') {
        // Salvar e redirecionar para o dashboard
        $_SESSION['wizard_completed'] = true;
        header('Location: cloud-partner-hub.php?completed=1');
        exit;
    }
    
    // Refresh na mesma página
    header('Location: cloud-partner-wizard.php?step=' . $step);
    exit;
}

// Calcular scores
$totalScore = $formData['pcsPerformance'] + $formData['pcsSkilling'] + $formData['pcsCustomerSuccess'];
$isPassing = $totalScore >= 70;

// Obter dados das constantes
$solutionAreas = Constants::SOLUTION_AREAS;
$pcsMaxScores = Constants::PCS_MAX_SCORES;
$skillingRules = Constants::SKILLING_RULES;
$journeySteps = Constants::JOURNEY_STEPS;

// Timeline steps
$timelineSteps = [
    2 => ['title' => 'Triagem', 'icon' => 'clipboard-check'],
    3 => ['title' => 'Solução', 'icon' => 'puzzle'],
    4 => ['title' => 'PCS Score', 'icon' => 'chart-bar'],
    5 => ['title' => 'Roadmap', 'icon' => 'map'],
    6 => ['title' => 'GTM', 'icon' => 'flag'],
];

// Gerar roadmap inteligente (apenas no step 5+)
$roadmap = null;
if ($step >= 5 && $formData['selectedSolutionArea']) {
    $roadmapGenerator = new RoadmapGenerator($formData);
    $roadmap = $roadmapGenerator->generate();
}
?>
<!DOCTYPE html>
<html lang="<?= getHtmlLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('pages.cloud_partner_hub') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --teal: #005758;
            --teal-dark: #003031;
            --blue: #0078D4;
            --blue-dark: #005587;
            --charcoal: #262626;
            --gray: #737373;
            --light-gray: #f5f5f7;
            --purple: #8B5CF6;
            --green: #16A34A;
            --orange: #EA580C;
            --sky: #00A4EF;
            --ms-green: #7FBA00;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--light-gray);
            color: var(--charcoal);
            min-height: 100vh;
        }

        .page-content {
            padding: 1.5rem 2rem 3rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Timeline */
        .timeline {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .timeline-step {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: .85rem;
            font-weight: 500;
            color: var(--gray);
            transition: all .2s;
        }

        .timeline-step.active {
            background: linear-gradient(135deg, var(--blue-dark), var(--blue));
            color: #fff;
            box-shadow: 0 4px 12px rgba(0,120,212,0.3);
        }

        .timeline-step.completed {
            color: var(--green);
        }

        .timeline-step .step-icon {
            width: 28px;
            height: 28px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.05);
        }

        .timeline-step.active .step-icon {
            background: rgba(255,255,255,0.2);
        }

        .timeline-step.completed .step-icon {
            background: rgba(22,163,74,0.1);
        }

        .timeline-connector {
            width: 40px;
            height: 2px;
            background: #e5e7eb;
        }

        .timeline-connector.completed {
            background: var(--green);
        }

        /* Layout */
        .wizard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 1024px) {
            .wizard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Card */
        .wizard-card {
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 2rem;
            border-top: 4px solid var(--blue-dark);
        }

        .wizard-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--charcoal);
            margin-bottom: .5rem;
        }

        .wizard-card .subtitle {
            font-size: .9rem;
            color: var(--gray);
            margin-bottom: 1.5rem;
        }

        /* Checkbox Options */
        .checkbox-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            border: 2px solid #e5e7eb;
            border-radius: 5px;
            cursor: pointer;
            transition: all .2s;
            margin-bottom: .75rem;
        }

        .checkbox-option:hover {
            background: #f9fafb;
        }

        .checkbox-option.checked {
            background: #f0f9ff;
            border-color: var(--sky);
        }

        .checkbox-option .check-box {
            width: 24px;
            height: 24px;
            border: 2px solid #d1d5db;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .2s;
            margin-right: 12px;
        }

        .checkbox-option.checked .check-box {
            background: var(--blue-dark);
            border-color: var(--blue-dark);
        }

        .checkbox-option .check-box svg {
            opacity: 0;
        }

        .checkbox-option.checked .check-box svg {
            opacity: 1;
        }

        /* Solution Cards */
        .solution-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .solution-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .solution-card {
            padding: 1.25rem;
            border: 2px solid #e5e7eb;
            border-radius: 5px;
            cursor: pointer;
            transition: all .2s;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .75rem;
            min-height: 110px;
        }

        .solution-card:hover {
            border-color: var(--sky);
            background: #f9fafb;
        }

        .solution-card.selected {
            border-color: var(--blue-dark);
            background: #f0f9ff;
        }

        .solution-card .solution-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .solution-card .solution-name {
            font-size: .8rem;
            font-weight: 600;
            color: var(--charcoal);
            line-height: 1.3;
        }

        /* Score Slider */
        .score-section {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .score-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: .75rem;
        }

        .score-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--charcoal);
        }

        .info-tooltip {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .info-tooltip::after {
            content: attr(title);
            position: absolute;
            left: 50%;
            bottom: calc(100% + 8px);
            transform: translateX(-50%);
            background: var(--charcoal);
            color: #fff;
            font-size: .75rem;
            font-weight: 400;
            padding: 10px 14px;
            border-radius: 6px;
            width: 280px;
            line-height: 1.5;
            white-space: normal;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 1000;
            pointer-events: none;
        }

        .info-tooltip::before {
            content: '';
            position: absolute;
            left: 50%;
            bottom: calc(100% + 2px);
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: var(--charcoal);
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 1001;
        }

        .info-tooltip:hover::after,
        .info-tooltip:hover::before {
            opacity: 1;
            visibility: visible;
        }

        .score-max {
            font-size: .75rem;
            background: #f3f4f6;
            padding: 4px 10px;
            border-radius: 5px;
            color: var(--gray);
            font-family: monospace;
        }

        .score-slider {
            width: 100%;
            height: 8px;
            -webkit-appearance: none;
            background: #e5e7eb;
            border-radius: 5px;
            outline: none;
        }

        .score-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 5px;
            background: var(--blue-dark);
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }

        .score-slider.performance::-webkit-slider-thumb { background: var(--blue-dark); }
        .score-slider.skilling::-webkit-slider-thumb { background: var(--ms-green); }
        .score-slider.customer::-webkit-slider-thumb { background: var(--sky); }

        .score-value {
            text-align: right;
            font-weight: 700;
            margin-top: .5rem;
        }

        .score-value.performance { color: var(--blue-dark); }
        .score-value.skilling { color: var(--ms-green); }
        .score-value.customer { color: var(--sky); }

        /* Certifications */
        .cert-section {
            background: linear-gradient(135deg, #faf5ff, #eef2ff);
            border: 2px solid #e9d5ff;
            border-radius: 5px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .cert-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .cert-header h4 {
            font-weight: 700;
            color: var(--charcoal);
        }

        .cert-level {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: .75rem;
            padding-bottom: .5rem;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .cert-level.intermediate { color: var(--blue); }
        .cert-level.advanced { color: var(--purple); }

        .cert-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .75rem;
            background: #fff;
            border-radius: 5px;
            border: 1px solid #e9d5ff;
            margin-bottom: .5rem;
        }

        .cert-name {
            font-size: .8rem;
            font-weight: 500;
            color: var(--charcoal);
        }

        .cert-input {
            width: 60px;
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
            font-size: .85rem;
            text-align: center;
        }

        .cert-input:focus {
            outline: none;
            border-color: var(--blue-dark);
            box-shadow: 0 0 0 3px rgba(0,85,135,0.1);
        }

        /* Result Section */
        .result-hero {
            text-align: center;
            padding: 2rem 0;
        }

        .result-trophy {
            width: 80px;
            height: 80px;
            background: var(--light-gray);
            border: 4px solid var(--blue-dark);
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .result-score {
            font-size: 3rem;
            font-weight: 900;
            color: var(--charcoal);
        }

        .result-score span {
            font-size: 1.25rem;
            color: var(--gray);
            font-weight: 400;
        }

        .result-label {
            color: var(--gray);
            font-weight: 500;
            margin-top: .25rem;
        }

        .result-badge {
            display: inline-block;
            padding: .5rem 1.25rem;
            border-radius: 5px;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-top: 1rem;
        }

        .result-badge.passing {
            background: rgba(127,186,0,0.1);
            color: var(--ms-green);
        }

        .result-badge.developing {
            background: rgba(245,158,11,0.1);
            color: #f59e0b;
        }

        /* Sidebar */
        .sidebar-card {
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .sidebar-card h4 {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            color: var(--charcoal);
            margin-bottom: 1rem;
        }

        .sidebar-card.benefits {
            background: linear-gradient(135deg, #faf5ff, #eef2ff);
            border: 1px solid #e9d5ff;
        }

        /* Buttons */
        .btn-row {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 5px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--blue-dark), var(--blue));
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,120,212,0.3);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-ghost {
            background: transparent;
            color: var(--gray);
        }

        .btn-ghost:hover {
            background: #f3f4f6;
            color: var(--charcoal);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--green), #15803d);
            color: #fff;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22,163,74,0.3);
        }

        /* Alert Box */
        .alert-box {
            padding: 1rem 1.25rem;
            border-radius: 5px;
            margin-top: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .alert-box.info {
            background: #eff6ff;
            border-left: 4px solid var(--blue);
        }

        .alert-box.warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
        }

        .alert-box.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        .alert-box h4 {
            font-size: .9rem;
            font-weight: 700;
            margin-bottom: .25rem;
        }

        .alert-box p {
            font-size: .8rem;
            color: var(--gray);
            line-height: 1.5;
        }

        .alert-box .btn-sm {
            margin-top: .75rem;
            padding: 8px 16px;
            font-size: .8rem;
        }

        /* Metrics Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
            padding: 1.25rem;
            background: #f9fafb;
            border-radius: 5px;
            border: 1px solid #e5e7eb;
        }

        .metrics-grid label {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--gray);
            letter-spacing: .5px;
        }

        .metrics-grid input {
            width: 100%;
            margin-top: .5rem;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
            font-size: .9rem;
        }

        .metrics-grid input:focus {
            outline: none;
            border-color: var(--blue-dark);
            box-shadow: 0 0 0 3px rgba(0,85,135,0.1);
        }

        /* Partner Types */
        .partner-types {
            display: flex;
            gap: .75rem;
            margin-top: .75rem;
        }

        .partner-type-btn {
            padding: 8px 16px;
            border: 1px solid #fde68a;
            background: #fff;
            border-radius: 5px;
            font-size: .8rem;
            cursor: pointer;
            transition: all .15s;
        }

        .partner-type-btn:hover {
            border-color: #fbbf24;
        }

        .partner-type-btn.selected {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #92400e;
        }

        /* GTM Planner */
        .gtm-section {
            text-align: center;
            padding: 2rem 0;
        }

        .gtm-icon {
            width: 80px;
            height: 80px;
            background: var(--light-gray);
            border: 4px solid var(--blue-dark);
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .gtm-title {
            font-size: 1.75rem;
            font-weight: 900;
            color: var(--charcoal);
            margin-bottom: .5rem;
        }

        .gtm-subtitle {
            color: var(--gray);
            font-weight: 500;
        }

        .readiness-card {
            background: #fff;
            border-left: 4px solid var(--blue-dark);
            border-radius: 5px;
            padding: 1.5rem;
            margin-top: 2rem;
            text-align: left;
        }

        .readiness-score {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--charcoal);
        }

        .readiness-score span {
            font-size: 1rem;
            color: var(--gray);
            font-weight: 400;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../templates/topbar.php'; ?>

    <div class="page-content">
        <!-- Timeline -->
        <div class="timeline">
            <?php foreach ($timelineSteps as $stepNum => $stepInfo): ?>
                <?php if ($stepNum > 2): ?>
                    <div class="timeline-connector <?= $step > $stepNum - 1 ? 'completed' : '' ?>"></div>
                <?php endif; ?>
                <div class="timeline-step <?= $step === $stepNum ? 'active' : ($step > $stepNum ? 'completed' : '') ?>">
                    <div class="step-icon">
                        <?php if ($step > $stepNum): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                        <?php else: ?>
                            <span style="font-size:.75rem;font-weight:700;"><?= $stepNum ?></span>
                        <?php endif; ?>
                    </div>
                    <span><?= $stepInfo['title'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="wizard-grid">
            <!-- Main Content -->
            <div>
                <form method="POST" class="wizard-card">
                    <input type="hidden" name="step" value="<?= $step ?>">

                    <?php if ($step === 2): ?>
                    <!-- STEP 2: TRIAGEM -->
                    <h3>Triagem Inicial</h3>
                    <p class="subtitle">Vamos validar seus cadastros fundamentais.</p>

                    <input type="hidden" name="isTdSynnexRegistered" value="<?= $formData['isTdSynnexRegistered'] ? '1' : '0' ?>" id="tdsynnex-hidden">
                    <input type="hidden" name="isMicrosoftPartner" value="<?= $formData['isMicrosoftPartner'] ? '1' : '0' ?>" id="microsoft-hidden">

                    <div class="checkbox-option <?= $formData['isTdSynnexRegistered'] ? 'checked' : '' ?>" onclick="toggleCheck('tdsynnex')">
                        <div style="display:flex;align-items:center;">
                            <div class="check-box">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="white" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                            </div>
                            <span style="font-weight:500;color:var(--charcoal);">Cadastro Ativo TD SYNNEX</span>
                        </div>
                    </div>

                    <div class="checkbox-option <?= $formData['isMicrosoftPartner'] ? 'checked' : '' ?>" onclick="toggleCheck('microsoft')">
                        <div style="display:flex;align-items:center;">
                            <div class="check-box">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="white" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                            </div>
                            <span style="font-weight:500;color:var(--charcoal);">Parceiro Microsoft (MPN ID)</span>
                        </div>
                    </div>

                    <?php if ($formData['isMicrosoftPartner'] && !$formData['isTdSynnexRegistered']): ?>
                    <div class="alert-box info">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="var(--blue)" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                        <div>
                            <h4 style="color:var(--blue);">Associe-se à TD SYNNEX</h4>
                            <p>Você possui MPN ID mas não está associado à TD SYNNEX. Complete a associação para desbloquear benefícios exclusivos.</p>
                            <a href="https://www.tdsynnex.com/br/parceiro-microsoft/" target="_blank" class="btn btn-primary btn-sm">
                                Associar-se à TD SYNNEX
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6-6-6z"/></svg>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!$formData['isMicrosoftPartner']): ?>
                    <div class="alert-box warning">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#f59e0b" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                        <div>
                            <h4 style="color:#92400e;">Material de Apoio</h4>
                            <p>Selecione seu perfil para receber o guia de cadastro:</p>
                            <div class="partner-types">
                                <?php foreach (['Revenda CSP', 'ISV / Dev', 'Services'] as $type): ?>
                                <button type="button" class="partner-type-btn <?= $formData['partnerTypeInterest'] === $type ? 'selected' : '' ?>" onclick="selectPartnerType('<?= $type ?>')">
                                    <?= $type ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="partnerTypeInterest" id="partnerTypeInterest" value="<?= htmlspecialchars($formData['partnerTypeInterest']) ?>">
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!$formData['isTdSynnexRegistered'] && !$formData['isMicrosoftPartner']): ?>
                    <div class="alert-box error" style="margin-top:1.5rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#dc2626" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                        <p style="color:#dc2626;font-size:.85rem;">Por favor, selecione pelo menos uma das opções acima para continuar.</p>
                    </div>
                    <?php endif; ?>

                    <div class="btn-row">
                        <a href="cloud-partner-onboarding.php" class="btn btn-ghost">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12l4.58-4.59z"/></svg>
                            Voltar
                        </a>
                        <button type="submit" name="action" value="next" class="btn btn-primary" <?= (!$formData['isTdSynnexRegistered'] && !$formData['isMicrosoftPartner']) ? 'disabled' : '' ?>>
                            Continuar
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6-6-6z"/></svg>
                        </button>
                    </div>

                    <?php elseif ($step === 3): ?>
                    <!-- STEP 3: SOLUÇÃO -->
                    <h3>Trilha de Solução</h3>
                    <p class="subtitle">Em qual área você deseja focar sua Designação?</p>

                    <input type="hidden" name="selectedSolutionArea" id="selectedSolutionArea" value="<?= htmlspecialchars($formData['selectedSolutionArea']) ?>">

                    <div class="solution-grid">
                        <?php
                        $solutions = [
                            // Microsoft 365 Logo oficial
                            'Modern Work' => '<svg width="32" height="32" viewBox="0 0 2500 2500" xmlns="http://www.w3.org/2000/svg"><path fill="#EA3E23" d="M1187.9 1187.9H0V0h1187.9z"/><path fill="#7FBA00" d="M2499.6 1187.9h-1188V0h1187.9v1187.9z"/><path fill="#00A4EF" d="M1187.9 2499.6H0v-1188h1187.9z"/><path fill="#FFB900" d="M2499.6 2499.6h-1188v-1188h1187.9v1188z"/></svg>',
                            // Azure Logo oficial
                            'Infrastructure' => '<svg width="32" height="32" viewBox="0 0 96 96" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="az1" x1="58.97" y1="9.37" x2="26.96" y2="84.91" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#114a8b"/><stop offset="1" stop-color="#0669bc"/></linearGradient><linearGradient id="az2" x1="60.25" y1="49.38" x2="53.03" y2="51.75" gradientUnits="userSpaceOnUse"><stop offset="0" stop-opacity=".3"/><stop offset=".07" stop-opacity=".2"/><stop offset=".32" stop-opacity=".1"/><stop offset=".62" stop-opacity=".05"/><stop offset="1" stop-opacity="0"/></linearGradient><linearGradient id="az3" x1="37.28" y1="6.15" x2="68.88" y2="85.02" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#3ccbf4"/><stop offset="1" stop-color="#2892df"/></linearGradient></defs><path fill="url(#az1)" d="M33.34 8.62h26.04l-27.03 80.44a4.15 4.15 0 0 1-3.94 2.82H8.15a4.15 4.15 0 0 1-3.93-5.47L29.41 11.44a4.15 4.15 0 0 1 3.93-2.82z"/><path fill="#0078d4" d="M71.18 60.26H41.29a1.91 1.91 0 0 0-1.3 3.31l26.53 24.76a4.17 4.17 0 0 0 2.85 1.12h23.59z"/><path fill="url(#az2)" d="M33.34 8.62a4.12 4.12 0 0 0-3.95 2.88L4.26 86.36a4.14 4.14 0 0 0 3.91 5.52h20.79a4.44 4.44 0 0 0 3.53-2.9l9.61-26.74 17.78 28.19h29.08l-12.51-37.78-45.11.01z"/><path fill="url(#az3)" d="M66.6 11.44a4.14 4.14 0 0 0-3.93-2.82H33.65a4.15 4.15 0 0 1 3.93 2.82l25.19 74.97a4.15 4.15 0 0 1-3.93 5.47h29.02a4.15 4.15 0 0 0 3.93-5.47z"/></svg>',
                            // Microsoft Security Logo oficial
                            'Security' => '<svg width="32" height="32" viewBox="0 0 96 96" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="sec1" x1="48" y1="8.5" x2="48" y2="87.5" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#5ea0ef"/><stop offset="1" stop-color="#0078d4"/></linearGradient></defs><path fill="url(#sec1)" d="M48.01 8.5L14 22.22v25.9c0 18.71 13.04 36.24 30.52 40.63l3.49.93 3.49-.93c17.48-4.39 30.52-21.92 30.52-40.63v-25.9L48.01 8.5z"/><path fill="#fff" d="M42.94 58.18L32.12 47.36l4.24-4.24 6.58 6.58 16.7-16.7 4.24 4.24z"/></svg>',
                            // Data & AI Logo
                            'Data & AI' => '<svg width="32" height="32" viewBox="0 0 96 96" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="ai1" x1="48" y1="8" x2="48" y2="88" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#50e6ff"/><stop offset="1" stop-color="#0078d4"/></linearGradient></defs><circle cx="48" cy="48" r="40" fill="url(#ai1)"/><path fill="#fff" d="M48 28a20 20 0 1 0 20 20 20 20 0 0 0-20-20zm0 34a14 14 0 1 1 14-14 14 14 0 0 1-14 14z"/><circle cx="48" cy="48" r="6" fill="#fff"/><path stroke="#fff" stroke-width="3" stroke-linecap="round" fill="none" d="M48 28v-8M48 76v-8M28 48h-8M76 48h-8M34.3 34.3l-5.7-5.7M67.4 67.4l-5.7-5.7M34.3 61.7l-5.7 5.7M67.4 28.6l-5.7 5.7"/></svg>',
                            // App Innovation Logo
                            'Digital & App Innovation' => '<svg width="32" height="32" viewBox="0 0 96 96" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="app1" x1="48" y1="8" x2="48" y2="88" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#773adc"/><stop offset="1" stop-color="#552f99"/></linearGradient></defs><rect x="12" y="16" width="72" height="52" rx="4" fill="url(#app1)"/><rect x="20" y="24" width="24" height="16" rx="2" fill="#50e6ff"/><rect x="52" y="24" width="24" height="6" rx="1" fill="#fff" opacity=".6"/><rect x="52" y="34" width="16" height="6" rx="1" fill="#fff" opacity=".6"/><rect x="20" y="48" width="56" height="12" rx="2" fill="#fff" opacity=".4"/><rect x="32" y="68" width="32" height="8" fill="#5c2d91"/><path fill="#773adc" d="M48 80l-8 8h16l-8-8z"/></svg>',
                            // Dynamics 365 Logo oficial
                            'Business Applications' => '<svg width="32" height="32" viewBox="0 0 96 96" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="dyn1" x1="48" y1="8" x2="48" y2="88" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#50e6ff"/><stop offset="1" stop-color="#0078d4"/></linearGradient></defs><path fill="url(#dyn1)" d="M48 8L8 28v40l40 20 40-20V28L48 8z"/><path fill="#0050c5" d="M48 48L8 28v40l40 20V48z"/><path fill="#00a4ef" d="M48 48l40-20v40L48 88V48z"/><circle cx="48" cy="48" r="12" fill="#fff"/></svg>',
                        ];
                        foreach ($solutions as $name => $icon):
                        ?>
                        <div class="solution-card <?= $formData['selectedSolutionArea'] === $name ? 'selected' : '' ?>" onclick="selectSolution('<?= $name ?>')">
                            <div class="solution-icon"><?= $icon ?></div>
                            <span class="solution-name"><?= $name ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($formData['selectedSolutionArea']): ?>
                    <div class="metrics-grid">
                        <div>
                            <label>Receita Média Mensal (USD)</label>
                            <div style="position:relative;">
                                <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--gray);font-weight:500;">$</span>
                                <input type="text" name="cspRevenue" value="<?= htmlspecialchars($formData['cspRevenue']) ?>" placeholder="0.00" style="padding-left:28px;">
                            </div>
                        </div>
                        <div>
                            <label>Clientes Ativos</label>
                            <input type="number" name="clientCount" value="<?= htmlspecialchars($formData['clientCount']) ?>" placeholder="0">
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="btn-row">
                        <button type="submit" name="action" value="prev" class="btn btn-ghost">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12l4.58-4.59z"/></svg>
                            Voltar
                        </button>
                        <button type="submit" name="action" value="next" class="btn btn-primary" <?= !$formData['selectedSolutionArea'] ? 'disabled' : '' ?>>
                            Continuar
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6-6-6z"/></svg>
                        </button>
                    </div>

                    <?php elseif ($step === 4): ?>
                    <!-- STEP 4: PCS & CERTIFICAÇÕES -->
                    <h3>Partner Capability Score (PCS)</h3>
                    <p class="subtitle">Simule sua pontuação oficial baseada nas métricas Microsoft Partner Center.</p>

                    <?php
                    $area = $formData['selectedSolutionArea'];
                    $maxScores = $pcsMaxScores[$area] ?? ['performance' => 30, 'skilling' => 40, 'customerSuccess' => 30];
                    
                    // Steps oficiais Microsoft por área (pontos por incremento)
                    $stepsByArea = [
                        'Modern Work' => [
                            'performance' => 2,    // 2-4 pts por cliente (usando menor)
                            'skilling' => 1,       // Varia por cert
                            'customerSuccess' => 5 // Usage(30) + Deployments(25)
                        ],
                        'Infrastructure' => [
                            'performance' => 10,   // 10 pts por cliente
                            'skilling' => 4,       // 4 pts por pessoa
                            'customerSuccess' => 2 // Usage(20) + Deployments(10)
                        ],
                        'Security' => [
                            'performance' => 2,    // 2-4 pts por cliente
                            'skilling' => 4,       // ~6.67 Enterprise, 4-8 SMB
                            'customerSuccess' => 4 // Usage(20) + Deployments(20) ~3.3 cada
                        ],
                        'Data & AI' => [
                            'performance' => 10,   // 10 pts por cliente
                            'skilling' => 4,       // 4 pts por pessoa
                            'customerSuccess' => 2 // Usage(20) + Deployments(10)
                        ],
                        'Digital & App Innovation' => [
                            'performance' => 10,   // 10 pts por cliente
                            'skilling' => 4,       // 4 pts por pessoa
                            'customerSuccess' => 2 // Usage(20) + Deployments(10)
                        ],
                        'Business Applications' => [
                            'performance' => 3,    // 3 pts por workload
                            'skilling' => 1,       // 1-7.5 pts (variável)
                            'customerSuccess' => 4 // Usage(30) + Deployments(20)
                        ]
                    ];
                    $steps = $stepsByArea[$area] ?? ['performance' => 1, 'skilling' => 1, 'customerSuccess' => 1];
                    
                    // Descrições das métricas por área
                    $metricDescriptions = [
                        'Modern Work' => [
                            'performance' => 'Net customer adds: 2-4 pts/cliente (max 5-10 clientes)',
                            'skilling' => 'Intermediate + Advanced: requer pessoas certificadas MS-900, MD-102, MS-102',
                            'customerSuccess' => 'Usage Growth (30 pts) + Deployments 40%+ adoption (25 pts)'
                        ],
                        'Infrastructure' => [
                            'performance' => 'Net customer adds: 10 pts/cliente (max 3 clientes com ACR $1K+)',
                            'skilling' => 'Intermediate: 4 pts/pessoa (AZ-104 + AZ-700/AZ-800). Advanced: 4 pts/pessoa (AZ-305 + Specialties)',
                            'customerSuccess' => 'Usage Growth: 1 pt/% ACR (max 20). Deployments: 2 pts cada (max 10)'
                        ],
                        'Security' => [
                            'performance' => 'Net customer adds: 2-4 pts/cliente (Azure + M365 Security)',
                            'skilling' => 'Steps obrigatórios: AZ-500 + SC-200, depois SC-100/SC-300/SC-400',
                            'customerSuccess' => 'Usage Growth (20 pts) + Deployments: ~3.3 pts cada (max 6)'
                        ],
                        'Data & AI' => [
                            'performance' => 'Net customer adds: 10 pts/cliente (max 3 clientes com ACR $500+)',
                            'skilling' => 'Pré-requisitos: AZ-104 + AZ-305. Depois: 4 pts/pessoa (DP-300, AI-102, etc.)',
                            'customerSuccess' => 'Usage Growth: 1 pt/% ACR (max 20). Deployments: 2 pts cada (max 10)'
                        ],
                        'Digital & App Innovation' => [
                            'performance' => 'Net customer adds: 10 pts/cliente (max 3 clientes com ACR)',
                            'skilling' => 'Intermediate: 4 pts/pessoa (AZ-104 + AZ-204). Advanced: 4 pts/pessoa (AZ-305 + AZ-400)',
                            'customerSuccess' => 'Usage Growth: 1 pt/% ACR (max 20). Deployments: 2 pts cada (max 10)'
                        ],
                        'Business Applications' => [
                            'performance' => 'Net workload adds: 3 pts/workload (max 5 workloads)',
                            'skilling' => 'Intermediate: 1-4 pts/pessoa (MB/PL series). Advanced: 2-7.5 pts/pessoa',
                            'customerSuccess' => 'Usage Growth MCV: 1 pt/% (max 30). Deployments: 4 pts cada (max 5)'
                        ]
                    ];
                    $areaDesc = $metricDescriptions[$area] ?? ['performance' => '', 'skilling' => '', 'customerSuccess' => ''];
                    ?>

                    <!-- Aviso de qualificação -->
                    <div style="background:linear-gradient(135deg,#fef3c7,#fef9c3);border:1px solid #fbbf24;border-radius:5px;padding:1rem;margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:12px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#d97706" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                        <div style="font-size:.8rem;color:#92400e;">
                            <strong>Critérios de Qualificação:</strong><br>
                            • Score total ≥ 70 pontos<br>
                            • Todos os 3 métricas devem ser > 0
                        </div>
                    </div>

                    <!-- Performance -->
                    <div class="score-section">
                        <div class="score-header">
                            <span class="score-label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="var(--blue-dark)" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6h-6z"/></svg>
                                Performance
                                <span class="info-tooltip" title="Acesse o Partner Center &gt; Insights &gt; Partner Capability Score para ver seu score de Performance atual. A métrica é calculada com base em Net Customer Adds (clientes novos com consumo ativo nos últimos 12 meses).">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="#d97706" viewBox="0 0 24 24" style="cursor:help;margin-left:4px;vertical-align:middle;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                </span>
                            </span>
                            <span class="score-max">Max: <?= $maxScores['performance'] ?> (step: <?= $steps['performance'] ?>)</span>
                        </div>
                        <p style="font-size:.75rem;color:var(--gray);margin:0 0 .75rem 0;"><?= htmlspecialchars($areaDesc['performance']) ?></p>
                        <input type="range" name="pcsPerformance" class="score-slider performance" min="0" max="<?= $maxScores['performance'] ?>" step="<?= $steps['performance'] ?>" value="<?= $formData['pcsPerformance'] ?>" oninput="updateScoreDisplay(this, 'performance')">
                        <div class="score-value performance" id="performance-value"><?= $formData['pcsPerformance'] ?> pts</div>
                    </div>

                    <!-- Skilling -->
                    <div class="score-section">
                        <div class="score-header">
                            <span class="score-label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="var(--ms-green)" viewBox="0 0 24 24"><path d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm-1.06 13.54L7.4 12l1.41-1.41 2.12 2.12 4.24-4.24 1.41 1.41-5.64 5.66z"/></svg>
                                Skilling
                            </span>
                            <span class="score-max">Max: <?= $maxScores['skilling'] ?> (step: <?= $steps['skilling'] ?>)</span>
                        </div>
                        <p style="font-size:.75rem;color:var(--gray);margin:0 0 .75rem 0;"><?= htmlspecialchars($areaDesc['skilling']) ?></p>
                        <input type="range" name="pcsSkilling" class="score-slider skilling" min="0" max="<?= $maxScores['skilling'] ?>" step="<?= $steps['skilling'] ?>" value="<?= $formData['pcsSkilling'] ?>" oninput="updateScoreDisplay(this, 'skilling')">
                        <div class="score-value skilling" id="skilling-value"><?= $formData['pcsSkilling'] ?> pts</div>
                    </div>

                    <!-- Certificações -->
                    <?php if ($area && isset($skillingRules[$area])): ?>
                    <div class="cert-section">
                        <div class="cert-header">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="var(--purple)" viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                            <div>
                                <h4>Certificações do Time</h4>
                                <p style="font-size:.75rem;color:var(--gray);">Informe quantos colaboradores possuem cada certificação</p>
                            </div>
                        </div>

                        <div class="cert-level intermediate">Intermediárias (Associate-level)</div>
                        <?php foreach ($skillingRules[$area]['intermediate'] as $cert):
                            $certKey = explode(':', $cert)[0];
                            $certValue = $formData['certifications'][$certKey] ?? 0;
                        ?>
                        <div class="cert-item">
                            <span class="cert-name"><?= htmlspecialchars($cert) ?></span>
                            <input type="number" name="certifications[<?= htmlspecialchars($certKey) ?>]" class="cert-input" min="0" max="99" value="<?= $certValue ?>">
                        </div>
                        <?php endforeach; ?>

                        <?php if (!empty($skillingRules[$area]['advanced'])): ?>
                        <div class="cert-level advanced" style="margin-top:1rem;">Avançadas (Expert-level)</div>
                        <?php foreach ($skillingRules[$area]['advanced'] as $cert):
                            $certKey = explode(':', $cert)[0];
                            $certValue = $formData['certifications'][$certKey] ?? 0;
                        ?>
                        <div class="cert-item">
                            <span class="cert-name"><?= htmlspecialchars($cert) ?></span>
                            <input type="number" name="certifications[<?= htmlspecialchars($certKey) ?>]" class="cert-input" min="0" max="99" value="<?= $certValue ?>">
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div style="margin-top:1rem;padding:.75rem;background:#f3f4f6;border-radius:5px;font-size:.75rem;color:var(--gray);text-align:center;">
                            <em>Esta área de solução não requer certificações avançadas.</em>
                        </div>
                        <?php endif; ?>

                        <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem;background:#fff;border-radius:5px;border:1px solid #bbf7d0;margin-top:1rem;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="var(--green)" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                                <span style="font-size:.8rem;font-weight:600;color:var(--charcoal);">Total de Certificações:</span>
                            </div>
                            <span style="font-size:1.25rem;font-weight:700;color:var(--green);" id="cert-total"><?= array_sum($formData['certifications'] ?? []) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Customer Success -->
                    <div class="score-section" style="margin-top:1.5rem;">
                        <div class="score-header">
                            <span class="score-label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="var(--sky)" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                Customer Success
                            </span>
                            <span class="score-max">Max: <?= $maxScores['customerSuccess'] ?> (step: <?= $steps['customerSuccess'] ?>)</span>
                        </div>
                        <p style="font-size:.75rem;color:var(--gray);margin:0 0 .75rem 0;"><?= htmlspecialchars($areaDesc['customerSuccess']) ?></p>
                        <input type="range" name="pcsCustomerSuccess" class="score-slider customer" min="0" max="<?= $maxScores['customerSuccess'] ?>" step="<?= $steps['customerSuccess'] ?>" value="<?= $formData['pcsCustomerSuccess'] ?>" oninput="updateScoreDisplay(this, 'customer')">
                        <div class="score-value customer" id="customer-value"><?= $formData['pcsCustomerSuccess'] ?> pts</div>
                    </div>

                    <!-- Score Preview com Status de Qualificação -->
                    <?php
                    $allMetricsPositive = $formData['pcsPerformance'] > 0 && $formData['pcsSkilling'] > 0 && $formData['pcsCustomerSuccess'] > 0;
                    $isQualified = $totalScore >= 70 && $allMetricsPositive;
                    $statusColor = $isQualified ? '#16a34a' : ($totalScore >= 70 ? '#d97706' : '#dc2626');
                    $statusBg = $isQualified ? 'linear-gradient(135deg,#dcfce7,#d1fae5)' : ($totalScore >= 70 ? 'linear-gradient(135deg,#fef3c7,#fef9c3)' : 'linear-gradient(135deg,#fee2e2,#fecaca)');
                    $statusBorder = $isQualified ? '#22c55e' : ($totalScore >= 70 ? '#fbbf24' : '#f87171');
                    $statusText = $isQualified ? 'Qualificado' : ($totalScore >= 70 ? 'Métricas incompletas' : 'Em progresso');
                    ?>
                    <div style="margin-top:1.5rem;padding:1rem;background:<?= $statusBg ?>;border-radius:5px;border:1px solid <?= $statusBorder ?>;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="<?= $statusColor ?>" viewBox="0 0 24 24"><path d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94.63 1.5 1.98 2.63 3.61 2.96V19H7v2h10v-2h-4v-3.1c1.63-.33 2.98-1.46 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z"/></svg>
                                <span style="font-weight:600;color:var(--charcoal);">Score Total:</span>
                            </div>
                            <span style="font-size:1.5rem;font-weight:700;color:<?= $statusColor ?>;" id="total-score"><?= $totalScore ?> / 100</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="font-size:.8rem;font-weight:600;color:<?= $statusColor ?>;"><?= $statusText ?></span>
                            <span style="font-size:.75rem;color:var(--gray);">Mínimo: 70 pts</span>
                        </div>
                    </div>

                    <div class="btn-row">
                        <button type="submit" name="action" value="prev" class="btn btn-ghost">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12l4.58-4.59z"/></svg>
                            Voltar
                        </button>
                        <button type="submit" name="action" value="next" class="btn btn-primary">
                            Calcular Resultado
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6-6-6z"/></svg>
                        </button>
                    </div>

                    <?php elseif ($step === 5): ?>
                    <!-- STEP 5: ROADMAP INTELIGENTE -->
                    <?php if ($roadmap): ?>
                    <?php $summary = $roadmap['summary']; ?>
                    
                    <!-- Hero Section com Status -->
                    <div style="background:<?= $summary['status'] === 'qualified' ? 'linear-gradient(135deg, #dcfce7, #bbf7d0)' : 'linear-gradient(135deg, #f8fafc, #e2e8f0)' ?>;border-radius:8px;padding:2rem;text-align:center;margin-bottom:1.5rem;border-left:4px solid <?= $summary['status'] === 'qualified' ? 'var(--green)' : 'var(--blue)' ?>;">
                        <h2 style="font-size:1.25rem;font-weight:700;color:var(--charcoal);margin-bottom:.5rem;">
                            <?= htmlspecialchars($summary['headline']) ?>
                        </h2>
                        <p style="font-size:.9rem;color:var(--gray);max-width:500px;margin:0 auto;">
                            <?= htmlspecialchars($summary['message']) ?>
                        </p>
                    </div>

                    <!-- Métricas Atuais -->
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
                        <?php 
                        $currentState = $roadmap['currentState'];
                        $metrics = [
                            ['key' => 'performance', 'label' => 'Performance', 'color' => '#0078D4'],
                            ['key' => 'skilling', 'label' => 'Skilling', 'color' => '#8B5CF6'],
                            ['key' => 'customerSuccess', 'label' => 'Customer Success', 'color' => '#16A34A'],
                            ['key' => 'total', 'label' => 'Total', 'color' => '#005758']
                        ];
                        foreach ($metrics as $m):
                            $data = $currentState['scores'][$m['key']];
                            $pct = $data['percentage'];
                            $barColor = $m['key'] === 'total' ? ($pct >= 70 ? '#16A34A' : '#EA580C') : $m['color'];
                        ?>
                        <div style="background:#fff;border-radius:5px;padding:1rem;border:1px solid #e5e7eb;border-top:3px solid <?= $barColor ?>;">
                            <div style="font-size:.65rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--gray);margin-bottom:.5rem;"><?= $m['label'] ?></div>
                            <div style="font-size:1.5rem;font-weight:700;color:<?= $barColor ?>;"><?= $data['current'] ?><span style="font-size:.75rem;color:var(--gray);">/ <?= $data['max'] ?></span></div>
                            <div style="height:4px;background:#e5e7eb;border-radius:2px;margin-top:.5rem;overflow:hidden;">
                                <div style="height:100%;width:<?= min(100, $pct) ?>%;background:<?= $barColor ?>;border-radius:2px;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($summary['status'] !== 'qualified'): ?>
                    <!-- Gaps e Tempo Estimado -->
                    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1.5rem;">
                        <!-- Gaps Identificados -->
                        <div style="background:#fff;border-radius:5px;border:1px solid #e5e7eb;padding:1.25rem;">
                            <h4 style="font-size:.85rem;font-weight:700;color:var(--charcoal);margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="var(--orange)" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                Gaps Identificados
                            </h4>
                            <?php if (isset($roadmap['gaps']['qualification'])): ?>
                            <div style="display:flex;align-items:center;gap:12px;padding:.75rem;background:#fef3c7;border-radius:5px;margin-bottom:.75rem;border-left:3px solid #f59e0b;">
                                <div>
                                    <div style="font-weight:600;color:#92400e;font-size:.85rem;">Gap para Qualificação</div>
                                    <div style="font-size:.75rem;color:#a16207;">Faltam <?= $roadmap['gaps']['qualification']['gap'] ?> pontos para atingir 70</div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php 
                            $gapConfig = [
                                'skilling' => ['label' => 'Skilling', 'color' => '#7c3aed', 'bg' => '#faf5ff'],
                                'performance' => ['label' => 'Performance', 'color' => '#0078D4', 'bg' => '#eff6ff'],
                                'customerSuccess' => ['label' => 'Customer Success', 'color' => '#16A34A', 'bg' => '#f0fdf4']
                            ];
                            foreach (['skilling', 'performance', 'customerSuccess'] as $gapKey):
                                if (isset($roadmap['gaps'][$gapKey])):
                                    $gap = $roadmap['gaps'][$gapKey];
                                    $cfg = $gapConfig[$gapKey];
                            ?>
                            <div style="display:flex;align-items:flex-start;gap:12px;padding:.75rem;background:<?= $cfg['bg'] ?>;border-radius:5px;margin-bottom:.5rem;border-left:3px solid <?= $cfg['color'] ?>;">
                                <div style="flex:1;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;">
                                        <span style="font-weight:600;color:<?= $cfg['color'] ?>;font-size:.8rem;"><?= $cfg['label'] ?></span>
                                        <span style="font-size:.7rem;padding:2px 8px;border-radius:3px;background:<?= $cfg['color'] ?>;color:#fff;"><?= ucfirst($gap['severity']) ?></span>
                                    </div>
                                    <div style="font-size:.75rem;color:var(--gray);margin-top:.25rem;">
                                        Atual: <?= $gap['current'] ?> → Meta: <?= round($gap['target'], 1) ?> (gap: <?= round($gap['gap'], 1) ?> pts)
                                    </div>
                                </div>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>

                        <!-- Tempo Estimado -->
                        <div style="background:#fff;border-radius:5px;border:1px solid #e5e7eb;padding:1.25rem;">
                            <h4 style="font-size:.85rem;font-weight:700;color:var(--charcoal);margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="var(--teal)" viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                                Tempo Estimado
                            </h4>
                            <?php $timeEstimate = $roadmap['estimatedTimeToQualify']; ?>
                            <div style="text-align:center;padding:1rem;background:linear-gradient(135deg,#f0fdfa,#ccfbf1);border-radius:5px;">
                                <div style="font-size:2rem;font-weight:700;color:var(--teal);"><?= $timeEstimate['months'] ?></div>
                                <div style="font-size:.75rem;color:var(--gray);font-weight:600;text-transform:uppercase;">meses</div>
                            </div>
                            <div style="margin-top:.75rem;">
                                <div style="display:flex;justify-content:space-between;font-size:.7rem;color:var(--gray);padding:4px 0;">
                                    <span>Otimista:</span>
                                    <span><?= ceil($timeEstimate['range']['optimistic'] / 4) ?> meses</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:.7rem;color:var(--charcoal);font-weight:600;padding:4px 0;">
                                    <span>Realista:</span>
                                    <span><?= ceil($timeEstimate['range']['realistic'] / 4) ?> meses</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:.7rem;color:var(--gray);padding:4px 0;">
                                    <span>Conservador:</span>
                                    <span><?= ceil($timeEstimate['range']['conservative'] / 4) ?> meses</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ações Priorizadas -->
                    <div style="background:#fff;border-radius:5px;border:1px solid #e5e7eb;padding:1.25rem;margin-bottom:1.5rem;">
                        <h4 style="font-size:.85rem;font-weight:700;color:var(--charcoal);margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="var(--blue)" viewBox="0 0 24 24"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
                            Plano de Ação Priorizado
                        </h4>
                        
                        <?php foreach (array_slice($roadmap['actions'], 0, 6) as $action): ?>
                        <?php
                        $categoryColors = [
                            'skilling' => ['bg' => '#faf5ff', 'border' => '#e9d5ff', 'text' => '#7c3aed'],
                            'performance' => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'text' => '#1d4ed8'],
                            'customerSuccess' => ['bg' => '#f0fdf4', 'border' => '#bbf7d0', 'text' => '#16a34a']
                        ];
                        $cc = $categoryColors[$action['category']] ?? $categoryColors['skilling'];
                        $priorityBadge = match($action['priority'] ?? 'medium') {
                            'high' => ['bg' => '#dc2626', 'text' => 'Alta'],
                            'medium' => ['bg' => '#f59e0b', 'text' => 'Média'],
                            'low' => ['bg' => '#6b7280', 'text' => 'Baixa'],
                            default => ['bg' => '#6b7280', 'text' => 'Normal']
                        };
                        ?>
                        <div style="display:flex;gap:12px;padding:1rem;background:<?= $cc['bg'] ?>;border-radius:5px;border-left:4px solid <?= $cc['border'] ?>;margin-bottom:.75rem;">
                            <div style="flex:1;">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:.25rem;">
                                    <span style="font-weight:600;color:var(--charcoal);font-size:.85rem;"><?= htmlspecialchars($action['title']) ?></span>
                                    <span style="font-size:.6rem;padding:2px 6px;border-radius:3px;background:<?= $priorityBadge['bg'] ?>;color:#fff;"><?= $priorityBadge['text'] ?></span>
                                </div>
                                <p style="font-size:.75rem;color:var(--gray);margin:0;line-height:1.4;">
                                    <?= htmlspecialchars($action['description'] ?? '') ?>
                                </p>
                                <?php if (!empty($action['tips'])): ?>
                                <div style="margin-top:.5rem;padding:.5rem;background:rgba(255,255,255,.7);border-radius:4px;">
                                    <p style="font-size:.65rem;font-weight:600;color:var(--gray);margin-bottom:.25rem;">Recomendações:</p>
                                    <ul style="font-size:.7rem;color:var(--gray);margin:0;padding-left:1rem;">
                                        <?php foreach (array_slice($action['tips'], 0, 2) as $tip): ?>
                                        <li><?= htmlspecialchars($tip) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align:right;flex-shrink:0;">
                                <div style="font-size:.65rem;color:var(--gray);text-transform:uppercase;">Duração</div>
                                <div style="font-size:.85rem;font-weight:600;color:<?= $cc['text'] ?>;"><?= $action['duration'] ?? 4 ?> sem</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Milestones -->
                    <div style="background:#fff;border-radius:5px;border:1px solid #e5e7eb;padding:1.25rem;margin-bottom:1.5rem;">
                        <h4 style="font-size:.85rem;font-weight:700;color:var(--charcoal);margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="var(--green)" viewBox="0 0 24 24"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6h-5.6z"/></svg>
                            Marcos do Roadmap
                        </h4>
                        <div style="display:flex;gap:0;position:relative;">
                            <!-- Linha conectora -->
                            <div style="position:absolute;top:20px;left:24px;right:24px;height:3px;background:linear-gradient(90deg,#10b981,#0078D4,#8B5CF6,#f59e0b);border-radius:2px;"></div>
                            
                            <?php foreach ($roadmap['milestones'] as $index => $milestone): ?>
                            <?php
                            $milestoneColors = [
                                'start' => '#10b981',
                                'checkpoint' => '#0078D4',
                                'goal' => '#f59e0b'
                            ];
                            $color = $milestoneColors[$milestone['type']] ?? '#6b7280';
                            ?>
                            <div style="flex:1;text-align:center;position:relative;z-index:1;">
                                <div style="width:40px;height:40px;background:<?= $color ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;box-shadow:0 2px 8px rgba(0,0,0,.15);">
                                    <span style="color:#fff;font-weight:700;font-size:.75rem;">
                                        <?= $index + 1 ?>
                                    </span>
                                </div>
                                <div style="margin-top:.5rem;">
                                    <div style="font-size:.7rem;font-weight:600;color:var(--charcoal);"><?= htmlspecialchars($milestone['title']) ?></div>
                                    <div style="font-size:.65rem;color:var(--gray);">Semana <?= $milestone['week'] ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Quick Wins -->
                    <?php if (!empty($roadmap['quickWins'])): ?>
                    <div style="background:#f8fafc;border-radius:5px;padding:1.25rem;margin-bottom:1.5rem;border:1px solid #e5e7eb;">
                        <h4 style="font-size:.85rem;font-weight:700;color:var(--charcoal);margin-bottom:.75rem;display:flex;align-items:center;gap:8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="var(--green)" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                            Ações de Impacto Rápido
                        </h4>
                        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
                            <?php foreach ($roadmap['quickWins'] as $qw): ?>
                            <span style="padding:6px 12px;background:#fff;color:var(--charcoal);font-size:.75rem;font-weight:500;border-radius:5px;border:1px solid #e5e7eb;">
                                <?= htmlspecialchars($qw['title']) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Próximos Passos -->
                    <div style="background:#f8fafc;border-radius:5px;padding:1.25rem;border:1px solid #e5e7eb;">
                        <h4 style="font-size:.85rem;font-weight:700;color:var(--charcoal);margin-bottom:.75rem;">Próximos Passos Imediatos</h4>
                        <ul style="list-style:none;padding:0;margin:0;">
                            <?php foreach ($summary['nextSteps'] ?? [] as $nextStep): ?>
                            <li style="display:flex;align-items:center;gap:8px;padding:.5rem 0;border-bottom:1px solid #e5e7eb;font-size:.85rem;color:var(--charcoal);">
                                <?= htmlspecialchars($nextStep) ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <?php else: ?>
                    <!-- Parceiro já qualificado -->
                    <div style="background:#f0fdf4;border-radius:5px;padding:1.5rem;margin-bottom:1.5rem;border-left:4px solid var(--green);">
                        <h3 style="font-size:1.25rem;font-weight:700;color:#166534;">Você já está qualificado</h3>
                        <p style="font-size:.9rem;color:#15803d;max-width:400px;margin:.5rem 0 0;">
                            Continue expandindo suas certificações e base de clientes para manter e melhorar sua designação.
                        </p>
                    </div>
                    
                    <!-- Próximos objetivos para qualificados -->
                    <div style="background:#fff;border-radius:5px;border:1px solid #e5e7eb;padding:1.25rem;">
                        <h4 style="font-size:.85rem;font-weight:700;color:var(--charcoal);margin-bottom:1rem;">Próximos Objetivos</h4>
                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;">
                            <div style="padding:1rem;background:#faf5ff;border-radius:5px;border-top:3px solid #7c3aed;">
                                <div style="font-size:.8rem;font-weight:600;color:#7c3aed;">Especializações</div>
                                <div style="font-size:.7rem;color:var(--gray);margin-top:.25rem;">Diferencie sua empresa</div>
                            </div>
                            <div style="padding:1rem;background:#eff6ff;border-radius:5px;border-top:3px solid #1d4ed8;">
                                <div style="font-size:.8rem;font-weight:600;color:#1d4ed8;">Aumentar Score</div>
                                <div style="font-size:.7rem;color:var(--gray);margin-top:.25rem;">Maior visibilidade</div>
                            </div>
                            <div style="padding:1rem;background:#f0fdf4;border-radius:5px;border-top:3px solid #16a34a;">
                                <div style="font-size:.8rem;font-weight:600;color:#16a34a;">Go-to-Market</div>
                                <div style="font-size:.7rem;color:var(--gray);margin-top:.25rem;">Ativar benefícios</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <!-- Fallback se não houver área selecionada -->
                    <div style="padding:3rem;background:#f8fafc;border-radius:5px;border:1px solid #e5e7eb;">
                        <h3 style="color:var(--charcoal);margin-bottom:.5rem;">Selecione uma Área de Solução</h3>
                        <p style="color:var(--gray);">Volte ao passo anterior e selecione sua área de foco para gerar o roadmap personalizado.</p>
                    </div>
                    <?php endif; ?>

                    <div class="btn-row">
                        <button type="submit" name="action" value="prev" class="btn btn-ghost">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12l4.58-4.59z"/></svg>
                            Voltar
                        </button>
                        <button type="submit" name="action" value="next" class="btn btn-primary" style="padding:14px 32px;">
                            Definir Estratégia GTM
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6-6-6z"/></svg>
                        </button>
                    </div>

                    <?php elseif ($step === 6): ?>
                    <!-- STEP 6: GTM PLANNER -->
                    <div class="gtm-section">
                        <div class="gtm-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="var(--blue-dark)" viewBox="0 0 24 24"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6h-5.6z"/></svg>
                        </div>
                        <h2 class="gtm-title">Estratégia de Go-to-Market</h2>
                        <p class="gtm-subtitle">Planeje suas ações de geração de demanda e capacitação.</p>
                    </div>

                    <div class="readiness-card">
                        <div style="display:grid;grid-template-columns:1fr 2fr;gap:2rem;align-items:start;">
                            <div>
                                <p style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--gray);letter-spacing:.5px;">Prontidão para Execução</p>
                                <div class="readiness-score" style="margin-top:.5rem;"><?= min(100, $totalScore + 15) ?> <span>/ 100</span></div>
                                <p style="font-size:.85rem;color:var(--gray);margin-top:.5rem;">
                                    <?php if ($isPassing): ?>
                                    Você está pronto para iniciar campanhas de geração de demanda!
                                    <?php else: ?>
                                    Complete os requisitos de skilling para maximizar seu potencial.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;">
                                    <?php if ($formData['isTdSynnexRegistered']): ?>
                                    <span style="padding:6px 12px;background:#dcfce7;color:#166534;font-size:.75rem;font-weight:600;border-radius:5px;">TD SYNNEX Ativo</span>
                                    <?php endif; ?>
                                    <?php if ($formData['isMicrosoftPartner']): ?>
                                    <span style="padding:6px 12px;background:#dbeafe;color:#1e40af;font-size:.75rem;font-weight:600;border-radius:5px;">Microsoft Partner</span>
                                    <?php endif; ?>
                                    <?php if ($formData['selectedSolutionArea']): ?>
                                    <span style="padding:6px 12px;background:#fef3c7;color:#92400e;font-size:.75rem;font-weight:600;border-radius:5px;"><?= htmlspecialchars($formData['selectedSolutionArea']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:5px;padding:1rem;">
                                    <p style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--gray);letter-spacing:.5px;margin-bottom:.75rem;">Checklist Executivo</p>
                                    <ul style="list-style:none;padding:0;margin:0;">
                                        <?php
                                        $checklist = [];
                                        if ($formData['pcsSkilling'] < 20) $checklist[] = 'Aumentar pontuação de Skilling';
                                        if (empty($formData['clientCount'])) $checklist[] = 'Registrar clientes ativos';
                                        if (empty($formData['cspRevenue'])) $checklist[] = 'Informar receita CSP';
                                        if (empty($checklist)) $checklist[] = 'Tudo pronto. Você pode concluir e publicar o plano.';
                                        foreach ($checklist as $item):
                                        ?>
                                        <li style="display:flex;align-items:center;gap:8px;padding:6px 0;font-size:.85rem;color:var(--charcoal);">
                                            <div style="width:20px;height:20px;border-radius:5px;border:1px solid #d1d5db;display:flex;align-items:center;justify-content:center;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="var(--gray)" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                                            </div>
                                            <?= htmlspecialchars($item) ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="btn-row" style="flex-wrap:wrap;gap:1rem;">
                        <button type="submit" name="action" value="prev" class="btn btn-ghost">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12l4.58-4.59z"/></svg>
                            Voltar
                        </button>
                        <button type="button" onclick="generatePDFReport()" class="btn btn-outline" style="padding:14px 24px;border:2px solid var(--teal);color:var(--teal);background:#fff;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM9 13h6v2H9v-2zm0 4h6v2H9v-2z"/></svg>
                            Gerar PDF com IA
                        </button>
                        <button type="submit" name="action" value="finish" class="btn btn-success" style="padding:14px 32px;">
                            Concluir Planejamento
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Sidebar -->
            <div>
                <div class="sidebar-card">
                    <h4>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="var(--blue-dark)" viewBox="0 0 24 24"><path d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm-1.06 13.54L7.4 12l1.41-1.41 2.12 2.12 4.24-4.24 1.41 1.41-5.64 5.66z"/></svg>
                        Status da Parceria
                    </h4>
                    <div style="display:flex;justify-content:space-between;font-size:.9rem;">
                        <span style="color:var(--gray);">Nível Atual</span>
                        <span style="font-weight:700;color:var(--blue-dark);"><?= $isPassing ? 'Solution Partner' : 'Register' ?></span>
                    </div>
                </div>

                <div class="sidebar-card">
                    <h4>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="var(--blue-dark)" viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg>
                        Recursos Úteis
                    </h4>
                    <ul style="list-style:none;padding:0;margin:0;">
                        <?php foreach (['Guia de Skilling (PDF)', 'Tabela de Rebates FY25', 'Suporte Partner Center'] as $item): ?>
                        <li style="padding:.6rem 0;border-bottom:1px solid #f3f4f6;">
                            <a href="#" style="display:flex;justify-content:space-between;align-items:center;color:var(--gray);text-decoration:none;font-size:.85rem;transition:color .15s;">
                                <?= $item ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24" style="opacity:0;transition:opacity .15s;"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle checkbox
        function toggleCheck(type) {
            const hidden = document.getElementById(type + '-hidden');
            const option = event.currentTarget;
            const isChecked = hidden.value === '1';
            hidden.value = isChecked ? '0' : '1';
            option.classList.toggle('checked', !isChecked);
            
            // Update continue button state
            updateContinueButton();
        }
        
        // Update continue button state based on checkbox selections
        function updateContinueButton() {
            const tdsynnex = document.getElementById('tdsynnex-hidden');
            const microsoft = document.getElementById('microsoft-hidden');
            const continueBtn = document.querySelector('button[name="action"][value="next"]');
            
            if (tdsynnex && microsoft && continueBtn) {
                const hasSelection = tdsynnex.value === '1' || microsoft.value === '1';
                continueBtn.disabled = !hasSelection;
            }
        }

        // Select partner type
        function selectPartnerType(type) {
            document.getElementById('partnerTypeInterest').value = type;
            document.querySelectorAll('.partner-type-btn').forEach(btn => {
                btn.classList.toggle('selected', btn.textContent.trim() === type);
            });
        }

        // Select solution
        function selectSolution(name) {
            document.getElementById('selectedSolutionArea').value = name;
            document.querySelectorAll('.solution-card').forEach(card => {
                card.classList.toggle('selected', card.querySelector('.solution-name').textContent === name);
            });
            // Submit form to show metrics
            document.querySelector('form').submit();
        }

        // Update score display
        function updateScoreDisplay(slider, type) {
            document.getElementById(type + '-value').textContent = slider.value + ' pts';
            updateTotalScore();
        }

        // Update total score
        function updateTotalScore() {
            const performance = parseInt(document.querySelector('input[name="pcsPerformance"]')?.value || 0);
            const skilling = parseInt(document.querySelector('input[name="pcsSkilling"]')?.value || 0);
            const customer = parseInt(document.querySelector('input[name="pcsCustomerSuccess"]')?.value || 0);
            const total = performance + skilling + customer;
            const totalEl = document.getElementById('total-score');
            if (totalEl) totalEl.textContent = total + ' pts';
        }

        // Update cert total
        document.querySelectorAll('.cert-input').forEach(input => {
            input.addEventListener('input', () => {
                let total = 0;
                document.querySelectorAll('.cert-input').forEach(i => {
                    total += parseInt(i.value) || 0;
                });
                const certTotal = document.getElementById('cert-total');
                if (certTotal) certTotal.textContent = total;
            });
        });
    </script>

    <!-- jsPDF Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <!-- PDF Generation Modal -->
    <div id="pdf-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:2rem;max-width:480px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div id="pdf-loading" style="display:block;">
                <div style="width:64px;height:64px;border:4px solid #e5e7eb;border-top-color:#005758;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 1.5rem;"></div>
                <h3 style="font-size:1.1rem;font-weight:700;color:#262626;margin-bottom:.5rem;">Gerando Relatorio com IA</h3>
                <p style="font-size:.85rem;color:#737373;">Analisando pontuacoes e gerando recomendacoes personalizadas...</p>
            </div>
            <div id="pdf-success" style="display:none;">
                <div style="width:64px;height:64px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#16a34a" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                </div>
                <h3 style="font-size:1.1rem;font-weight:700;color:#262626;margin-bottom:.5rem;">PDF Gerado com Sucesso</h3>
                <p style="font-size:.85rem;color:#737373;margin-bottom:1.5rem;">O download iniciara automaticamente.</p>
                <button onclick="closePdfModal()" style="padding:10px 24px;background:#005758;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Fechar</button>
            </div>
            <div id="pdf-error" style="display:none;">
                <div style="width:64px;height:64px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#dc2626" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                </div>
                <h3 style="font-size:1.1rem;font-weight:700;color:#262626;margin-bottom:.5rem;">Erro ao Gerar PDF</h3>
                <p id="pdf-error-msg" style="font-size:.85rem;color:#737373;margin-bottom:1.5rem;">Tente novamente mais tarde.</p>
                <button onclick="closePdfModal()" style="padding:10px 24px;background:#dc2626;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Fechar</button>
            </div>
        </div>
    </div>
    <style>@keyframes spin { to { transform: rotate(360deg); }}</style>

    <!-- PDF Generation Script -->
    <script>
        // Dados do parceiro para o PDF
        const pdfPartnerData = {
            companyName: <?= json_encode($formData['companyName'] ?? 'Parceiro') ?>,
            selectedSolutionArea: <?= json_encode($formData['selectedSolutionArea'] ?? '') ?>,
            pcsPerformance: <?= json_encode((float)($formData['pcsPerformance'] ?? 0)) ?>,
            pcsSkilling: <?= json_encode((float)($formData['pcsSkilling'] ?? 0)) ?>,
            pcsCustomerSuccess: <?= json_encode((float)($formData['pcsCustomerSuccess'] ?? 0)) ?>,
            teamCertCount: <?= json_encode(count($formData['certifications'] ?? [])) ?>,
            isTdSynnexRegistered: <?= json_encode($formData['isTdSynnexRegistered']) ?>,
            isMicrosoftPartner: <?= json_encode($formData['isMicrosoftPartner']) ?>,
            mpnId: <?= json_encode($formData['mpnId'] ?? '') ?>,
            cspRevenue: <?= json_encode($formData['cspRevenue'] ?? '') ?>,
            clientCount: <?= json_encode($formData['clientCount'] ?? '') ?>
        };

        function showPdfModal() {
            const modal = document.getElementById('pdf-modal');
            modal.style.display = 'flex';
            document.getElementById('pdf-loading').style.display = 'block';
            document.getElementById('pdf-success').style.display = 'none';
            document.getElementById('pdf-error').style.display = 'none';
        }

        function closePdfModal() {
            document.getElementById('pdf-modal').style.display = 'none';
        }

        function showPdfError(msg) {
            document.getElementById('pdf-loading').style.display = 'none';
            document.getElementById('pdf-error').style.display = 'block';
            document.getElementById('pdf-error-msg').textContent = msg || 'Tente novamente mais tarde.';
        }

        function showPdfSuccess() {
            document.getElementById('pdf-loading').style.display = 'none';
            document.getElementById('pdf-success').style.display = 'block';
        }

        async function generatePDFReport() {
            showPdfModal();

            try {
                // Chamar API de recomendacoes
                const response = await fetch('partner-recommendation-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ partnerData: pdfPartnerData })
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Erro ao gerar recomendacoes');
                }

                // ============================================================
                // GERAR PDF EXECUTIVO TD SYNNEX
                // ============================================================
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');
                const pageWidth = doc.internal.pageSize.getWidth();
                const pageHeight = doc.internal.pageSize.getHeight();
                const margin = 15;
                const contentWidth = pageWidth - (margin * 2);
                let y = 0;

                // Cores TD SYNNEX
                const colors = {
                    teal: [0, 87, 88],
                    tealDark: [0, 48, 49],
                    charcoal: [38, 38, 38],
                    gray: [115, 115, 115],
                    lightGray: [248, 250, 252],
                    border: [229, 231, 235],
                    blue: [0, 120, 212],
                    purple: [124, 58, 237],
                    green: [5, 150, 105],
                    orange: [217, 119, 6],
                    red: [220, 38, 38]
                };

                // Funcoes auxiliares
                function checkPage(needed = 25) {
                    if (y + needed > pageHeight - 20) {
                        doc.addPage();
                        y = 20;
                        return true;
                    }
                    return false;
                }

                function drawLine(x1, yPos, x2, color = colors.border) {
                    doc.setDrawColor(...color);
                    doc.setLineWidth(0.3);
                    doc.line(x1, yPos, x2, yPos);
                }

                function addSection(title, yPos) {
                    checkPage(30);
                    doc.setFontSize(11);
                    doc.setFont('helvetica', 'bold');
                    doc.setTextColor(...colors.charcoal);
                    doc.text(title.toUpperCase(), margin, yPos);
                    drawLine(margin, yPos + 2, margin + 45, colors.teal);
                    return yPos + 10;
                }

                // ============================================================
                // CAPA / HEADER
                // ============================================================
                
                // Barra superior Teal
                doc.setFillColor(...colors.teal);
                doc.rect(0, 0, pageWidth, 55, 'F');
                
                // Accent line
                doc.setFillColor(...colors.tealDark);
                doc.rect(0, 55, pageWidth, 3, 'F');

                // Logo area (texto estilizado)
                doc.setTextColor(255, 255, 255);
                doc.setFontSize(10);
                doc.setFont('helvetica', 'bold');
                doc.text('TD SYNNEX', margin, 15);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8);
                doc.text('Cloud Partner Hub', margin, 20);

                // Titulo principal
                doc.setFontSize(20);
                doc.setFont('helvetica', 'bold');
                doc.text('Relatorio de Qualificacao', margin, 35);
                doc.setFontSize(14);
                doc.setFont('helvetica', 'normal');
                doc.text('Solutions Partner Designation', margin, 43);

                // Data no canto direito
                doc.setFontSize(8);
                doc.text(new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' }), pageWidth - margin, 15, { align: 'right' });

                y = 68;

                // ============================================================
                // DADOS DO PARCEIRO
                // ============================================================
                doc.setFillColor(...colors.lightGray);
                doc.roundedRect(margin, y, contentWidth, 28, 2, 2, 'F');
                
                doc.setFontSize(14);
                doc.setFont('helvetica', 'bold');
                doc.setTextColor(...colors.charcoal);
                doc.text(pdfPartnerData.companyName || 'Parceiro', margin + 8, y + 10);

                doc.setFontSize(9);
                doc.setFont('helvetica', 'normal');
                doc.setTextColor(...colors.gray);
                
                const infoLine = [
                    pdfPartnerData.selectedSolutionArea || 'Area nao definida',
                    pdfPartnerData.mpnId ? 'MPN: ' + pdfPartnerData.mpnId : null,
                    pdfPartnerData.isMicrosoftPartner ? 'Microsoft Partner' : null
                ].filter(Boolean).join('  |  ');
                doc.text(infoLine, margin + 8, y + 18);

                // Status badge
                const totalScore = pdfPartnerData.pcsPerformance + pdfPartnerData.pcsSkilling + pdfPartnerData.pcsCustomerSuccess;
                const isQualified = totalScore >= 70 && pdfPartnerData.pcsPerformance > 0 && pdfPartnerData.pcsSkilling > 0 && pdfPartnerData.pcsCustomerSuccess > 0;
                
                const badgeX = pageWidth - margin - 45;
                doc.setFillColor(...(isQualified ? colors.green : colors.orange));
                doc.roundedRect(badgeX, y + 5, 40, 8, 2, 2, 'F');
                doc.setFontSize(7);
                doc.setFont('helvetica', 'bold');
                doc.setTextColor(255, 255, 255);
                doc.text(isQualified ? 'QUALIFICADO' : 'EM PROGRESSO', badgeX + 20, y + 10.5, { align: 'center' });

                y += 38;

                // ============================================================
                // METRICAS PCS - Grid Visual
                // ============================================================
                y = addSection('Partner Capability Score', y);

                const boxWidth = (contentWidth - 15) / 4;
                const boxHeight = 32;
                const gapToQualify = Math.max(0, 70 - totalScore);
                
                const metrics = [
                    { label: 'PERFORMANCE', value: pdfPartnerData.pcsPerformance, color: colors.blue, max: 30 },
                    { label: 'SKILLING', value: pdfPartnerData.pcsSkilling, color: colors.purple, max: 40 },
                    { label: 'CUSTOMER SUCCESS', value: pdfPartnerData.pcsCustomerSuccess, color: colors.green, max: 30 },
                    { label: 'TOTAL', value: totalScore, color: isQualified ? colors.green : colors.teal, max: 100, isTotal: true }
                ];

                metrics.forEach((m, i) => {
                    const x = margin + (i * (boxWidth + 5));
                    
                    // Box background
                    doc.setFillColor(...colors.lightGray);
                    doc.roundedRect(x, y, boxWidth, boxHeight, 2, 2, 'F');
                    
                    // Top accent bar
                    doc.setFillColor(...m.color);
                    doc.rect(x, y, boxWidth, 2.5, 'F');
                    
                    // Label
                    doc.setFontSize(6);
                    doc.setFont('helvetica', 'bold');
                    doc.setTextColor(...colors.gray);
                    doc.text(m.label, x + 5, y + 10);
                    
                    // Value
                    doc.setFontSize(18);
                    doc.setTextColor(...m.color);
                    doc.text(m.value.toString(), x + 5, y + 22);
                    
                    // Max indicator
                    doc.setFontSize(8);
                    doc.setTextColor(...colors.gray);
                    doc.text('/' + m.max, x + 5 + doc.getTextWidth(m.value.toString()) + 1, y + 22);
                    
                    // Progress bar
                    const barY = y + 26;
                    const barWidth = boxWidth - 10;
                    const fillWidth = Math.min(barWidth, (m.value / m.max) * barWidth);
                    
                    doc.setFillColor(...colors.border);
                    doc.roundedRect(x + 5, barY, barWidth, 3, 1, 1, 'F');
                    
                    if (fillWidth > 0) {
                        doc.setFillColor(...m.color);
                        doc.roundedRect(x + 5, barY, fillWidth, 3, 1, 1, 'F');
                    }
                });

                y += boxHeight + 10;

                // Gap indicator (se nao qualificado)
                if (!isQualified && gapToQualify > 0) {
                    doc.setFillColor(254, 243, 199);
                    doc.roundedRect(margin, y, contentWidth, 12, 2, 2, 'F');
                    doc.setFontSize(9);
                    doc.setFont('helvetica', 'bold');
                    doc.setTextColor(...colors.orange);
                    doc.text('Gap para qualificacao: ' + gapToQualify + ' pontos', margin + 8, y + 8);
                    
                    if (pdfPartnerData.pcsPerformance === 0 || pdfPartnerData.pcsSkilling === 0 || pdfPartnerData.pcsCustomerSuccess === 0) {
                        doc.setFont('helvetica', 'normal');
                        doc.setFontSize(8);
                        doc.text('(Todos os pilares devem ser > 0)', margin + 80, y + 8);
                    }
                    y += 18;
                }

                y += 5;

                // ============================================================
                // RECOMENDACOES POR PILAR
                // ============================================================
                const rec = data.recommendations || data;
                const pillarAnalysis = rec.pillarAnalysis || {};

                y = addSection('Recomendacoes por Pilar', y);

                // Funcao para renderizar pilar
                function renderPillar(pillar, title, accentColor, bgColor) {
                    if (!pillar) return;
                    
                    checkPage(45);
                    
                    // Header do pilar
                    doc.setFillColor(...bgColor);
                    doc.roundedRect(margin, y, contentWidth, 10, 2, 2, 'F');
                    doc.setFillColor(...accentColor);
                    doc.rect(margin, y, 3, 10, 'F');
                    
                    doc.setFontSize(10);
                    doc.setFont('helvetica', 'bold');
                    doc.setTextColor(...accentColor);
                    doc.text(title, margin + 8, y + 7);
                    
                    // Status badge
                    const status = pillar.status || 'warning';
                    const statusColors = {
                        critical: colors.red,
                        warning: colors.orange,
                        good: colors.blue,
                        excellent: colors.green
                    };
                    doc.setFontSize(7);
                    doc.setTextColor(...(statusColors[status] || colors.gray));
                    doc.text((status || '').toUpperCase(), pageWidth - margin - 5, y + 7, { align: 'right' });
                    
                    y += 14;
                    
                    // Recomendacoes
                    const recs = pillar.recommendations || [];
                    doc.setFontSize(8);
                    doc.setFont('helvetica', 'normal');
                    doc.setTextColor(...colors.charcoal);
                    
                    recs.slice(0, 3).forEach(r => {
                        checkPage(8);
                        const lines = doc.splitTextToSize(r, contentWidth - 15);
                        lines.forEach(line => {
                            doc.text('•  ' + line, margin + 5, y);
                            y += 5;
                        });
                    });
                    
                    // Gap de certificacoes (apenas para Skilling)
                    if (pillar.certificationGap) {
                        checkPage(15);
                        doc.setFillColor(254, 252, 232);
                        doc.roundedRect(margin + 5, y, contentWidth - 10, 10, 2, 2, 'F');
                        doc.setFontSize(7);
                        doc.setFont('helvetica', 'bold');
                        doc.setTextColor(...colors.orange);
                        doc.text('ALERTA: ' + pillar.certificationGap.substring(0, 90), margin + 8, y + 6);
                        y += 14;
                    }
                    
                    y += 5;
                }

                renderPillar(pillarAnalysis.performance, 'Performance', colors.blue, [239, 246, 255]);
                renderPillar(pillarAnalysis.skilling, 'Skilling', colors.purple, [250, 245, 255]);
                renderPillar(pillarAnalysis.customerSuccess, 'Customer Success', colors.green, [240, 253, 244]);

                // ============================================================
                // ANALISE DE CERTIFICACOES
                // ============================================================
                const certAnalysis = rec.certificationAnalysis;
                if (certAnalysis && certAnalysis.hasGap) {
                    checkPage(45);
                    y = addSection('Analise de Certificacoes', y);
                    
                    doc.setFillColor(254, 242, 242);
                    doc.roundedRect(margin, y, contentWidth, 35, 2, 2, 'F');
                    doc.setFillColor(...colors.red);
                    doc.rect(margin, y, 3, 35, 'F');
                    
                    y += 8;
                    doc.setFontSize(9);
                    doc.setFont('helvetica', 'bold');
                    doc.setTextColor(...colors.red);
                    doc.text('Gap Identificado', margin + 8, y);
                    
                    y += 6;
                    doc.setFontSize(8);
                    doc.setFont('helvetica', 'normal');
                    doc.setTextColor(...colors.charcoal);
                    const gapLines = doc.splitTextToSize(certAnalysis.gapDescription || '', contentWidth - 20);
                    gapLines.slice(0, 3).forEach(line => {
                        doc.text(line, margin + 8, y);
                        y += 4;
                    });
                    
                    if (certAnalysis.possibleCauses && certAnalysis.possibleCauses.length > 0) {
                        y += 3;
                        doc.setFont('helvetica', 'bold');
                        doc.setFontSize(7);
                        doc.setTextColor(...colors.gray);
                        doc.text('POSSIVEIS CAUSAS:', margin + 8, y);
                        y += 4;
                        doc.setFont('helvetica', 'normal');
                        certAnalysis.possibleCauses.slice(0, 2).forEach(cause => {
                            doc.text('• ' + cause, margin + 10, y);
                            y += 4;
                        });
                    }
                    
                    y += 10;
                }

                // ============================================================
                // ACOES DE IMPACTO RAPIDO
                // ============================================================
                const quickWins = rec.quickWins;
                if (quickWins && quickWins.length > 0) {
                    checkPage(40);
                    y = addSection('Acoes de Impacto Rapido', y);
                    
                    quickWins.slice(0, 4).forEach((qw, i) => {
                        checkPage(12);
                        
                        // Numero circular
                        doc.setFillColor(...colors.teal);
                        doc.circle(margin + 5, y + 3.5, 4, 'F');
                        doc.setFontSize(8);
                        doc.setFont('helvetica', 'bold');
                        doc.setTextColor(255, 255, 255);
                        doc.text((i + 1).toString(), margin + 3.5, y + 5);
                        
                        // Texto
                        doc.setTextColor(...colors.charcoal);
                        doc.setFontSize(9);
                        doc.text(qw.title || qw, margin + 14, y + 5);
                        
                        // Impact badge
                        if (qw.impact) {
                            doc.setFontSize(7);
                            doc.setTextColor(...colors.gray);
                            doc.text(qw.impact, pageWidth - margin, y + 5, { align: 'right' });
                        }
                        
                        y += 10;
                    });
                    
                    y += 5;
                }

                // ============================================================
                // ROADMAP
                // ============================================================
                const roadmap = rec.roadmap;
                if (roadmap && roadmap.phases && roadmap.phases.length > 0) {
                    checkPage(50);
                    y = addSection('Roadmap de Qualificacao', y);
                    
                    // Tempo estimado
                    if (roadmap.estimatedMonths) {
                        doc.setFillColor(...colors.lightGray);
                        doc.roundedRect(pageWidth - margin - 35, y - 8, 35, 8, 2, 2, 'F');
                        doc.setFontSize(7);
                        doc.setFont('helvetica', 'bold');
                        doc.setTextColor(...colors.teal);
                        doc.text(roadmap.estimatedMonths + ' meses', pageWidth - margin - 17.5, y - 3, { align: 'center' });
                    }
                    
                    // Timeline visual
                    const phaseWidth = contentWidth / Math.min(4, roadmap.phases.length);
                    
                    roadmap.phases.slice(0, 4).forEach((phase, i) => {
                        const x = margin + (i * phaseWidth);
                        
                        // Linha conectora
                        if (i < roadmap.phases.length - 1 && i < 3) {
                            doc.setDrawColor(...colors.border);
                            doc.setLineWidth(1);
                            doc.line(x + phaseWidth / 2 + 8, y + 8, x + phaseWidth - 8, y + 8);
                        }
                        
                        // Circulo da fase
                        doc.setFillColor(...colors.teal);
                        doc.circle(x + phaseWidth / 2, y + 8, 6, 'F');
                        doc.setTextColor(255, 255, 255);
                        doc.setFontSize(9);
                        doc.setFont('helvetica', 'bold');
                        doc.text(phase.phase.toString(), x + phaseWidth / 2 - 2, y + 10.5);
                        
                        // Titulo
                        doc.setTextColor(...colors.charcoal);
                        doc.setFontSize(8);
                        const titleLines = doc.splitTextToSize(phase.title || '', phaseWidth - 10);
                        titleLines.forEach((line, li) => {
                            doc.text(line, x + phaseWidth / 2, y + 20 + (li * 4), { align: 'center' });
                        });
                        
                        // Duracao
                        doc.setTextColor(...colors.gray);
                        doc.setFontSize(7);
                        doc.setFont('helvetica', 'normal');
                        doc.text(phase.duration || '', x + phaseWidth / 2, y + 28, { align: 'center' });
                    });
                    
                    y += 38;
                }

                // ============================================================
                // FOOTER EM TODAS AS PAGINAS
                // ============================================================
                const pageCount = doc.internal.getNumberOfPages();
                for (let i = 1; i <= pageCount; i++) {
                    doc.setPage(i);
                    
                    // Linha separadora
                    doc.setDrawColor(...colors.border);
                    doc.setLineWidth(0.3);
                    doc.line(margin, pageHeight - 12, pageWidth - margin, pageHeight - 12);
                    
                    // Texto do footer
                    doc.setFontSize(7);
                    doc.setTextColor(...colors.gray);
                    doc.setFont('helvetica', 'normal');
                    doc.text('TD SYNNEX Brasil  |  Cloud Partner Hub', margin, pageHeight - 7);
                    doc.text('Pagina ' + i + ' de ' + pageCount, pageWidth - margin, pageHeight - 7, { align: 'right' });
                    
                    // Marca d'agua discreta
                    doc.setFontSize(6);
                    doc.setTextColor(200, 200, 200);
                    doc.text('Documento gerado automaticamente com recomendacoes de IA', pageWidth / 2, pageHeight - 7, { align: 'center' });
                }

                // Download
                const fileName = 'TDSYNNEX_PCS_' + (pdfPartnerData.companyName || 'Parceiro').replace(/[^a-zA-Z0-9]/g, '_') + '_' + new Date().toISOString().slice(0, 10) + '.pdf';
                doc.save(fileName);

                showPdfSuccess();

            } catch (error) {
                console.error('Erro ao gerar PDF:', error);
                showPdfError(error.message || 'Erro ao processar recomendacoes');
            }
        }
    </script>

    <!-- AI Chat Styles -->
    <style>
        /* Chat FAB */
        .ai-chat-fab {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 1000;
        }

        .ai-chat-btn {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: var(--teal);
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 16px rgba(0,87,88,0.35);
            transition: all .2s;
        }

        .ai-chat-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,87,88,0.45);
        }

        .ai-chat-btn.active {
            background: var(--charcoal);
            border-radius: 50%;
        }

        .ai-chat-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            padding: 2px 6px;
            background: var(--teal);
            color: #fff;
            font-size: .6rem;
            font-weight: 700;
            border-radius: 4px;
            letter-spacing: .5px;
            border: 2px solid #fff;
        }

        /* Chat Panel */
        .ai-chat-panel {
            position: fixed;
            bottom: 88px;
            right: 24px;
            width: 420px;
            max-width: calc(100vw - 48px);
            height: 560px;
            max-height: calc(100vh - 120px);
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.18);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 999;
            border: 1px solid #e5e7eb;
            animation: chatSlideUp .2s ease-out;
        }

        .ai-chat-panel.open { display: flex; }

        @keyframes chatSlideUp {
            from { opacity: 0; transform: translateY(16px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Header */
        .ai-chat-header {
            padding: 14px 18px;
            background: var(--teal);
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ai-chat-avatar {
            width: 38px;
            height: 38px;
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ai-chat-title { font-weight: 700; font-size: .95rem; }
        .ai-chat-status {
            font-size: .7rem;
            opacity: .85;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .ai-chat-status::before {
            content: '';
            width: 6px;
            height: 6px;
            background: #4ade80;
            border-radius: 50%;
        }

        .ai-chat-header-actions {
            margin-left: auto;
            display: flex;
            gap: 6px;
        }

        .ai-chat-header-btn {
            background: rgba(255,255,255,0.15);
            border: none;
            color: #fff;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .15s;
        }
        .ai-chat-header-btn:hover { background: rgba(255,255,255,0.25); }

        /* Context Bar */
        .ai-chat-context {
            padding: 10px 16px;
            background: #f0fdf4;
            border-bottom: 1px solid #bbf7d0;
            font-size: .72rem;
            color: #166534;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ai-chat-context.no-context {
            background: #fef3c7;
            border-color: #fcd34d;
            color: #92400e;
        }
        .ai-chat-context svg { width: 14px; height: 14px; flex-shrink: 0; }

        /* Messages */
        .ai-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: #fafafa;
        }

        .ai-chat-msg {
            display: flex;
            gap: 10px;
            max-width: 92%;
        }
        .ai-chat-msg.user {
            margin-left: auto;
            flex-direction: row-reverse;
        }

        .ai-chat-msg-avatar {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .ai-chat-msg.assistant .ai-chat-msg-avatar {
            background: var(--teal);
            color: #fff;
        }
        .ai-chat-msg.user .ai-chat-msg-avatar {
            background: #e5e7eb;
            color: var(--charcoal);
        }

        .ai-chat-msg-wrapper {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .ai-chat-msg-content {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: .84rem;
            line-height: 1.55;
        }
        .ai-chat-msg.assistant .ai-chat-msg-content {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px 10px 10px 2px;
            color: var(--charcoal);
        }
        .ai-chat-msg.user .ai-chat-msg-content {
            background: var(--teal);
            color: #fff;
            border-radius: 10px 10px 2px 10px;
        }

        /* Message Actions */
        .ai-chat-msg-actions {
            display: flex;
            gap: 4px;
            opacity: 0;
            transition: opacity .15s;
        }
        .ai-chat-msg:hover .ai-chat-msg-actions { opacity: 1; }
        .ai-chat-msg-action {
            padding: 4px 8px;
            background: transparent;
            border: none;
            color: #9ca3af;
            font-size: .68rem;
            cursor: pointer;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .ai-chat-msg-action:hover { background: #f3f4f6; color: var(--charcoal); }
        .ai-chat-msg-action svg { width: 12px; height: 12px; }

        /* Message Content Styling */
        .ai-chat-msg-content h4 {
            font-size: .82rem;
            font-weight: 700;
            margin: .75rem 0 .5rem;
            color: var(--charcoal);
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: .25rem;
        }
        .ai-chat-msg-content h4:first-child { margin-top: 0; }
        .ai-chat-msg-content ul, .ai-chat-msg-content ol {
            margin: .5rem 0;
            padding-left: 1.1rem;
        }
        .ai-chat-msg-content li { margin: .3rem 0; }
        .ai-chat-msg-content strong { font-weight: 600; color: var(--charcoal); }
        .ai-chat-msg-content code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: .78rem;
            color: #0f766e;
        }
        .ai-chat-msg.user .ai-chat-msg-content code {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }
        .ai-chat-msg-content table {
            width: 100%;
            border-collapse: collapse;
            margin: .5rem 0;
            font-size: .78rem;
        }
        .ai-chat-msg-content th, .ai-chat-msg-content td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            text-align: left;
        }
        .ai-chat-msg-content th { background: #f8fafc; font-weight: 600; }

        /* Typing Indicator */
        .ai-typing {
            display: flex;
            gap: 5px;
            padding: 8px 0;
        }
        .ai-typing span {
            width: 7px;
            height: 7px;
            background: var(--teal);
            border-radius: 50%;
            animation: typingBounce .5s infinite alternate;
        }
        .ai-typing span:nth-child(2) { animation-delay: .15s; }
        .ai-typing span:nth-child(3) { animation-delay: .3s; }
        @keyframes typingBounce {
            from { transform: translateY(0); opacity: .4; }
            to { transform: translateY(-5px); opacity: 1; }
        }

        /* Quick Actions */
        .ai-chat-quick-actions {
            padding: 10px 14px;
            background: #fff;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 6px;
            overflow-x: auto;
        }
        .ai-quick-action {
            padding: 7px 12px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: .72rem;
            color: var(--charcoal);
            cursor: pointer;
            white-space: nowrap;
            transition: all .15s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .ai-quick-action:hover {
            background: #f0fdfa;
            border-color: var(--teal);
            color: var(--teal);
        }
        .ai-quick-action svg { width: 12px; height: 12px; }

        /* Input Area */
        .ai-chat-input {
            padding: 12px 14px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 8px;
            background: #fff;
        }
        .ai-chat-input input {
            flex: 1;
            padding: 11px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: .88rem;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            font-family: inherit;
        }
        .ai-chat-input input:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(0,87,88,0.08);
        }
        .ai-chat-input input::placeholder { color: #9ca3af; }

        .ai-chat-send {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            background: var(--teal);
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .15s;
        }
        .ai-chat-send:hover:not(:disabled) {
            background: var(--teal-dark);
        }
        .ai-chat-send:disabled { opacity: .5; cursor: not-allowed; }
        .ai-chat-send svg { width: 18px; height: 18px; }

        /* Footer */
        .ai-chat-footer {
            padding: 8px 14px;
            background: #fafafa;
            border-top: 1px solid #f0f0f0;
            font-size: .65rem;
            color: #9ca3af;
            text-align: center;
        }

        @media (max-width: 480px) {
            .ai-chat-panel {
                right: 10px;
                left: 10px;
                width: auto;
                bottom: 74px;
                height: calc(100vh - 100px);
                border-radius: 10px;
            }
            .ai-chat-fab { right: 14px; bottom: 14px; }
            .ai-chat-btn { width: 48px; height: 48px; }
        }
    </style>

    <!-- AI Chat HTML -->
    <div class="ai-chat-fab">
        <button class="ai-chat-btn" onclick="toggleChat()" title="Assistente IA Partner Center">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" id="chat-icon">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                <path d="M8 10h.01M12 10h.01M16 10h.01"/>
            </svg>
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" id="close-icon" style="display:none;">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
        <span class="ai-chat-badge">GPT</span>
    </div>

    <div class="ai-chat-panel" id="ai-chat-panel">
        <div class="ai-chat-header">
            <div class="ai-chat-avatar">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 16v-4M12 8h.01"/>
                </svg>
            </div>
            <div>
                <div class="ai-chat-title">Consultor Partner Center</div>
                <div class="ai-chat-status">Online</div>
            </div>
            <div class="ai-chat-header-actions">
                <button class="ai-chat-header-btn" onclick="clearChat()" title="Limpar conversa">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                </button>
                <button class="ai-chat-header-btn" onclick="toggleChat()" title="Fechar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="ai-chat-context" id="ai-chat-context">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <span id="context-text">Contexto do parceiro carregado</span>
        </div>

        <div class="ai-chat-messages" id="ai-chat-messages">
            <div class="ai-chat-msg assistant">
                <div class="ai-chat-msg-avatar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                    </svg>
                </div>
                <div class="ai-chat-msg-wrapper">
                    <div class="ai-chat-msg-content">
                        <strong>Bem-vindo ao Consultor Partner Center</strong><br><br>
                        Sou especialista em <strong>Microsoft AI Cloud Partner Program</strong> e posso ajudar com:
                        <ul>
                            <li>Requisitos para <strong>Solutions Partner</strong></li>
                            <li>Pontuacao e regras do <strong>PCS</strong></li>
                            <li>Certificacoes por area de solucao</li>
                            <li>Estrategias de qualificacao rapida</li>
                        </ul>
                        Selecione uma opcao abaixo ou digite sua pergunta.
                    </div>
                </div>
            </div>
        </div>

        <div class="ai-chat-quick-actions" id="ai-chat-quick-actions">
            <button class="ai-quick-action" onclick="sendSuggestion('Como funciona o Partner Capability Score?')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                O que e PCS?
            </button>
            <button class="ai-quick-action" onclick="sendSuggestion('Quais certificações preciso para minha área de solução?')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                Certificacoes
            </button>
            <button class="ai-quick-action" onclick="sendSuggestion('Quais ações de impacto rápido posso fazer para ganhar pontos?')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                Acoes rapidas
            </button>
            <button class="ai-quick-action" onclick="sendSuggestion('Analise meu score atual e sugira melhorias')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                Analisar meu PCS
            </button>
        </div>

        <div class="ai-chat-input">
            <input type="text" id="ai-chat-input" placeholder="Digite sua pergunta sobre Partner Center..." onkeypress="if(event.key==='Enter') sendMessage()">
            <button class="ai-chat-send" onclick="sendMessage()" id="ai-send-btn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
            </button>
        </div>

        <div class="ai-chat-footer">
            Powered by Azure OpenAI GPT-4o
        </div>
    </div>

    <!-- AI Chat JavaScript -->
    <script>
        // Chat state
        let chatHistory = [];
        let isLoading = false;

        // Partner context from PHP
        const partnerContext = {
            companyName: <?= json_encode($formData['companyName'] ?? '') ?>,
            selectedSolutionArea: <?= json_encode($formData['selectedSolutionArea'] ?? '') ?>,
            isTdSynnexRegistered: <?= json_encode($formData['isTdSynnexRegistered']) ?>,
            isMicrosoftPartner: <?= json_encode($formData['isMicrosoftPartner']) ?>,
            mpnId: <?= json_encode($formData['mpnId'] ?? '') ?>,
            pcsPerformance: <?= json_encode($formData['pcsPerformance'] ?? 0) ?>,
            pcsSkilling: <?= json_encode($formData['pcsSkilling'] ?? 0) ?>,
            pcsCustomerSuccess: <?= json_encode($formData['pcsCustomerSuccess'] ?? 0) ?>,
            cspRevenue: <?= json_encode($formData['cspRevenue'] ?? '') ?>,
            clientCount: <?= json_encode($formData['clientCount'] ?? '') ?>
        };

        // Update context indicator
        document.addEventListener('DOMContentLoaded', function() {
            const contextEl = document.getElementById('ai-chat-context');
            const contextText = document.getElementById('context-text');
            const hasContext = partnerContext.companyName || partnerContext.selectedSolutionArea;
            
            if (hasContext) {
                const area = partnerContext.selectedSolutionArea || 'Nao definida';
                const company = partnerContext.companyName || 'Parceiro';
                contextText.textContent = `${company} - ${area}`;
            } else {
                contextEl.classList.add('no-context');
                contextText.textContent = 'Preencha os dados do parceiro para analise personalizada';
            }
        });

        function toggleChat() {
            const panel = document.getElementById('ai-chat-panel');
            const chatBtn = document.querySelector('.ai-chat-btn');
            const chatIcon = document.getElementById('chat-icon');
            const closeIcon = document.getElementById('close-icon');
            
            panel.classList.toggle('open');
            chatBtn.classList.toggle('active');
            
            if (panel.classList.contains('open')) {
                chatIcon.style.display = 'none';
                closeIcon.style.display = 'block';
                document.getElementById('ai-chat-input').focus();
            } else {
                chatIcon.style.display = 'block';
                closeIcon.style.display = 'none';
            }
        }

        function clearChat() {
            if (!confirm('Limpar todo o historico de conversa?')) return;
            chatHistory = [];
            const messages = document.getElementById('ai-chat-messages');
            messages.innerHTML = `
                <div class="ai-chat-msg assistant">
                    <div class="ai-chat-msg-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/>
                        </svg>
                    </div>
                    <div class="ai-chat-msg-wrapper">
                        <div class="ai-chat-msg-content">
                            Conversa reiniciada. Como posso ajudar?
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('ai-chat-quick-actions').style.display = 'flex';
        }

        function sendSuggestion(text) {
            document.getElementById('ai-chat-input').value = text;
            sendMessage();
            document.getElementById('ai-chat-quick-actions').style.display = 'none';
        }

        function copyMessage(btn) {
            const content = btn.closest('.ai-chat-msg-wrapper').querySelector('.ai-chat-msg-content');
            const text = content.innerText;
            navigator.clipboard.writeText(text).then(() => {
                const originalText = btn.innerHTML;
                btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Copiado';
                setTimeout(() => { btn.innerHTML = originalText; }, 1500);
            });
        }

        function addMessage(content, role) {
            const messages = document.getElementById('ai-chat-messages');
            const msg = document.createElement('div');
            msg.className = `ai-chat-msg ${role}`;
            
            const avatarIcon = role === 'assistant' 
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
            
            const formattedContent = formatMessage(content);
            
            const actionsHtml = role === 'assistant' ? `
                <div class="ai-chat-msg-actions">
                    <button class="ai-chat-msg-action" onclick="copyMessage(this)">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        Copiar
                    </button>
                </div>` : '';
            
            msg.innerHTML = `
                <div class="ai-chat-msg-avatar">${avatarIcon}</div>
                <div class="ai-chat-msg-wrapper">
                    <div class="ai-chat-msg-content">${formattedContent}</div>
                    ${actionsHtml}
                </div>
            `;
            
            messages.appendChild(msg);
            messages.scrollTop = messages.scrollHeight;
        }

        function formatMessage(text) {
            // Enhanced markdown formatting
            let formatted = text
                // Headers
                .replace(/^### (.+)$/gm, '<h4>$1</h4>')
                .replace(/^## (.+)$/gm, '<h4>$1</h4>')
                // Bold and italic
                .replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.+?)\*/g, '<em>$1</em>')
                // Code
                .replace(/`(.+?)`/g, '<code>$1</code>')
                // Lists
                .replace(/^- (.+)$/gm, '<li>$1</li>')
                .replace(/^\* (.+)$/gm, '<li>$1</li>')
                .replace(/^\d+\. (.+)$/gm, '<li>$1</li>')
                // Line breaks
                .replace(/\n/g, '<br>');
            
            // Wrap consecutive <li> in <ul>
            formatted = formatted.replace(/(<li>.*?<\/li>)(<br>)?/g, '$1');
            formatted = formatted.replace(/(<li>.*?<\/li>)+/g, '<ul>$&</ul>');
            
            // Clean up excessive breaks
            formatted = formatted.replace(/<br><br><br>/g, '<br><br>');
            formatted = formatted.replace(/<br><h4>/g, '<h4>');
            formatted = formatted.replace(/<\/h4><br>/g, '</h4>');
            
            return formatted;
        }

        function showTyping() {
            const messages = document.getElementById('ai-chat-messages');
            const typing = document.createElement('div');
            typing.className = 'ai-chat-msg assistant';
            typing.id = 'typing-indicator';
            typing.innerHTML = `
                <div class="ai-chat-msg-avatar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                </div>
                <div class="ai-chat-msg-wrapper">
                    <div class="ai-chat-msg-content">
                        <div class="ai-typing"><span></span><span></span><span></span></div>
                    </div>
                </div>
            `;
            messages.appendChild(typing);
            messages.scrollTop = messages.scrollHeight;
        }

        function hideTyping() {
            const typing = document.getElementById('typing-indicator');
            if (typing) typing.remove();
        }

        async function sendMessage() {
            const input = document.getElementById('ai-chat-input');
            const sendBtn = document.getElementById('ai-send-btn');
            const message = input.value.trim();
            
            if (!message || isLoading) return;
            
            addMessage(message, 'user');
            chatHistory.push({ role: 'user', content: message });
            
            input.value = '';
            isLoading = true;
            sendBtn.disabled = true;
            showTyping();
            
            try {
                const response = await fetch('partner-chat-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        message: message,
                        history: chatHistory,
                        partnerContext: partnerContext
                    })
                });
                
                const data = await response.json();
                hideTyping();
                
                if (data.reply) {
                    addMessage(data.reply, 'assistant');
                    chatHistory.push({ role: 'assistant', content: data.reply });
                } else if (data.error) {
                    addMessage('Erro: ' + data.error, 'assistant');
                } else {
                    addMessage('Desculpe, ocorreu um erro. Tente novamente.', 'assistant');
                }
            } catch (error) {
                hideTyping();
                addMessage('Erro de conexao. Verifique sua internet e tente novamente.', 'assistant');
                console.error('Chat error:', error);
            } finally {
                isLoading = false;
                sendBtn.disabled = false;
                input.focus();
            }
        }
    </script>
</body>
</html>
