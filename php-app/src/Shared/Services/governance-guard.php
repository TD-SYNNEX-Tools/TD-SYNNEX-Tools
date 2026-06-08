<?php
declare(strict_types=1);

/**
 * Guard de governança T2 — incluir no TOPO das páginas protegidas.
 *
 *   require_once __DIR__ . '/../src/Shared/Services/governance-guard.php';
 *
 * Fluxo:
 *  - Em DEV (GOV_ENABLED != '1') o guard é "no-op": apenas popula
 *    $GLOBALS['gov_user'] = null e segue, sem bloquear nada.
 *  - Em produção: lê o token interno (HS256) vindo do portal via ?gov_token=,
 *    valida, guarda os claims na sessão e remove o token da URL (redirect).
 *  - Sem token válido → redireciona para o login do portal.
 *
 * Após rodar, expõe:
 *   $GLOBALS['gov_user'] = [
 *     'company_id' => string, 'user_id' => string,
 *     'role' => string, 'email' => string, 'token' => string|null
 *   ] | null
 */

require_once __DIR__ . '/GovernanceAuth.php';

use App\Shared\Services\GovernanceAuth;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$govAuth = GovernanceAuth::fromDefaultConfig();

// Helper exposto para o resto da página (URL base da API Node).
$GLOBALS['gov_api_base_url'] = $govAuth->apiBaseUrl();

if (!$govAuth->isEnabled()) {
    // Modo desenvolvimento: sem gate. Permite testar localmente.
    $GLOBALS['gov_user'] = $_SESSION['gov_user'] ?? null;
    return;
}

// 1) Sessão já autenticada e ainda válida?
$session = $_SESSION['gov_user'] ?? null;
if (is_array($session) && isset($session['exp']) && time() < (int) $session['exp']) {
    $GLOBALS['gov_user'] = $session;
    return;
}

// 2) Token recém-chegado na URL?
$rawToken = isset($_GET['gov_token']) ? (string) $_GET['gov_token'] : '';
if ($rawToken !== '') {
    $claims = $govAuth->verify($rawToken);
    if ($claims !== null) {
        $_SESSION['gov_user'] = [
            'company_id' => (string) ($claims['company_id'] ?? ''),
            'user_id'    => (string) ($claims['user_id'] ?? ''),
            'role'       => (string) ($claims['role'] ?? 'partner'),
            'email'      => (string) ($claims['email'] ?? ''),
            'token'      => $rawToken,
            'exp'        => (int) ($claims['exp'] ?? (time() + 3600)),
        ];

        // Remove o token da URL (evita reuso/compartilhamento por engano).
        $url = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $query = $_GET;
        unset($query['gov_token']);
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        header('Location: ' . $url, true, 302);
        exit;
    }
}

// 3) Sem credencial válida → manda para o login do portal.
header('Location: ' . $govAuth->portalLoginUrl(), true, 302);
exit;
