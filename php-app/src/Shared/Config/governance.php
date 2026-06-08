<?php
declare(strict_types=1);

/**
 * Configuração do Portal de Governança T2 (lado php-app).
 *
 * Lê variáveis de ambiente; em desenvolvimento, aceita um arquivo opcional
 * `governance.local.php` (NÃO versionado) que retorna um array de overrides.
 *
 * Variáveis:
 *   GOV_ENABLED                 '1' para exigir token (default '0' em dev)
 *   GOV_INTERNAL_TOKEN_SECRET   segredo HS256 compartilhado com a API Node
 *   GOV_API_BASE_URL            ex.: http://localhost:3001/api
 *   GOV_PORTAL_LOGIN_URL        para onde redirecionar sem token válido
 */

$env = static function (string $key, ?string $default = null): ?string {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
};

$config = [
    'enabled'       => $env('GOV_ENABLED', '0') === '1',
    'token_secret'  => $env('GOV_INTERNAL_TOKEN_SECRET', ''),
    'api_base_url'  => rtrim($env('GOV_API_BASE_URL', 'http://localhost:3001/api') ?? '', '/'),
    'portal_login'  => $env('GOV_PORTAL_LOGIN_URL', '/'),
    // tolerância de relógio (segundos) na validação de exp/nbf
    'leeway'        => (int) ($env('GOV_TOKEN_LEEWAY', '30')),
    // nome do issuer/audience esperados (devem casar com a API Node)
    'issuer'        => 'cloudpartner-hub',
    'audience'      => 'php-app',
];

$localOverride = __DIR__ . '/governance.local.php';
if (is_file($localOverride)) {
    /** @var array $overrides */
    $overrides = require $localOverride;
    if (is_array($overrides)) {
        $config = array_merge($config, $overrides);
    }
}

return $config;
