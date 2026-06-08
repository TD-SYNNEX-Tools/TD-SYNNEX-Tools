<?php
/**
 * Cloud Partner Hub - Página de Onboarding / Landing Page
 * Formulário de cadastro inicial de parceiros
 */

declare(strict_types=1);
session_start();

// Initialize i18n
require_once __DIR__ . '/../src/Shared/Services/i18n-bootstrap.php';

// Processar formulário
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $razaoSocial = trim($_POST['razaoSocial'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($razaoSocial)) {
        $error = 'Por favor, informe a Razão Social da empresa.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, informe um email corporativo válido.';
    } else {
        // Salvar na sessão e redirecionar para o wizard
        $_SESSION['onboarding_company'] = $razaoSocial;
        $_SESSION['onboarding_email'] = $email;
        $_SESSION['onboarding_started'] = true;
        
        header('Location: cloud-partner-wizard.php?step=2&company=' . urlencode($razaoSocial));
        exit;
    }
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --teal: #005758;
            --teal-dark: #003031;
            --teal-light: #e6f4f4;
            --blue: #0078D4;
            --charcoal: #262626;
            --gray: #737373;
            --gray-light: #a3a3a3;
            --light-bg: #f8fafc;
            --white: #ffffff;
            --border: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--light-bg);
            color: var(--charcoal);
            min-height: 100vh;
        }

        /* Main Container */
        .main-container {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 3rem 4rem;
            gap: 6rem;
            min-height: calc(100vh - 56px);
        }

        /* Left Side - Hero */
        .hero-section {
            max-width: 520px;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 900;
            color: var(--teal);
            line-height: 1.05;
            letter-spacing: -2px;
            margin-bottom: 1.25rem;
        }

        .hero-subtitle {
            font-size: 1.35rem;
            font-weight: 400;
            color: var(--charcoal);
            line-height: 1.4;
            margin-bottom: 1.5rem;
        }

        .hero-subtitle strong {
            font-weight: 600;
        }

        .hero-description {
            font-size: .95rem;
            color: var(--gray);
            line-height: 1.7;
            margin-bottom: 2.5rem;
        }

        .hero-description strong {
            color: var(--teal);
            font-weight: 600;
        }

        /* Features */
        .features {
            display: flex;
            gap: 2rem;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feature-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--teal);
        }

        .feature-icon svg {
            width: 20px;
            height: 20px;
        }

        .feature-text {
            font-size: .88rem;
            font-weight: 500;
            color: var(--charcoal);
        }

        /* Right Side - Form Card */
        .form-card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04);
            padding: 2.5rem;
            width: 400px;
        }

        .form-header {
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--charcoal);
            margin-bottom: .35rem;
        }

        .form-subtitle {
            font-size: .88rem;
            color: var(--gray);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: .72rem;
            font-weight: 700;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: .6rem;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: .95rem;
            font-family: inherit;
            color: var(--charcoal);
            background: var(--white);
            transition: all .2s ease;
        }

        .form-input::placeholder {
            color: var(--gray-light);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--teal);
            box-shadow: 0 0 0 4px rgba(0, 87, 88, 0.1);
        }

        .form-input:hover:not(:focus) {
            border-color: #cbd5e1;
        }

        .submit-btn {
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, var(--teal) 0%, var(--teal-dark) 100%);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: .95rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all .2s ease;
            margin-top: 1.5rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 87, 88, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn svg {
            width: 18px;
            height: 18px;
        }

        .form-footer {
            margin-top: 1.5rem;
            text-align: center;
        }

        .form-footer-text {
            font-size: .75rem;
            color: var(--gray-light);
            line-height: 1.6;
        }

        .form-footer-text a {
            color: var(--teal);
            text-decoration: none;
            font-weight: 500;
        }

        .form-footer-text a:hover {
            text-decoration: underline;
        }

        /* Error message */
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: .88rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-container {
                flex-direction: column;
                gap: 3rem;
                padding: 2rem;
            }

            .hero-section {
                text-align: center;
                max-width: 600px;
            }

            .hero-title {
                font-size: 2.5rem;
            }

            .features {
                justify-content: center;
            }

            .form-card {
                width: 100%;
                max-width: 420px;
            }
        }

        @media (max-width: 640px) {
            .hero-title {
                font-size: 2rem;
            }

            .hero-subtitle {
                font-size: 1.1rem;
            }

            .features {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }

            .form-card {
                padding: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../templates/topbar.php'; ?>

    <!-- Main Content -->
    <main class="main-container">
        <!-- Hero Section -->
        <section class="hero-section">
            <h1 class="hero-title">CloudPartner<br>HUB</h1>
            <p class="hero-subtitle">O ecossistema definitivo para <strong>aceleração de negócios.</strong></p>
            <p class="hero-description">
                Centralize sua jornada com a Microsoft e TD SYNNEX. Do onboarding aos incentivos avançados, tenha visibilidade completa da sua evolução <strong>no programa CSP.</strong>
            </p>
        </section>

        <!-- Form Card -->
        <div class="form-card">
            <div class="form-header">
                <h2 class="form-title">Application de Parceria</h2>
                <p class="form-subtitle">Inicie seu cadastro no CloudPartner HUB.</p>
            </div>

            <?php if ($error): ?>
            <div class="error-message">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="razaoSocial">Razão Social</label>
                    <input 
                        type="text" 
                        id="razaoSocial" 
                        name="razaoSocial" 
                        class="form-input" 
                        placeholder="Nome legal da empresa"
                        value="<?= htmlspecialchars($_POST['razaoSocial'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Corporativo</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="nome@empresa.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                    >
                </div>

                <button type="submit" class="submit-btn">
                    Iniciar Onboarding
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </form>

            <div class="form-footer">
                <p class="form-footer-text">
                    Ao prosseguir, você concorda com a <a href="#">Política de Privacidade</a> e os <a href="#">Termos de Uso</a> do HUB.
                </p>
            </div>
        </div>
    </main>
</body>
</html>
