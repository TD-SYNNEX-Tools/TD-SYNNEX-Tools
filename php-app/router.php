<?php
/**
 * Router para o servidor embutido do PHP (php -S)
 * Mapeia URLs amigáveis para os arquivos correspondentes em public/
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Rotas amigáveis → arquivos reais
$routes = [
    '/'                    => '/home.php',
    '/home'                => '/home.php',
    '/sql-advisor'         => '/sql-advisor.php',
    '/analise-financeira'  => '/analise-financeira.php',
    '/csp-comparison'      => '/csp-comparison.php',
    '/sku-management'      => '/sku-management.php',
    '/chat-api'            => '/chat-api.php',
    '/reset-session'       => '/reset-session.php',
    '/server-logs'         => '/server-logs.php',
    '/migracao-m365'       => '/migracao-m365.php',
    '/guia-exportacao-custos' => '/guia-exportacao-custos.php',
    '/upload-chunk'        => '/upload-chunk.php',
];

// Se a URI corresponde a uma rota amigável
if (isset($routes[$uri])) {
    $_SERVER['SCRIPT_NAME'] = $routes[$uri];
    require __DIR__ . '/public' . $routes[$uri];
    return true;
}

// Para arquivos estáticos existentes (css, js, imagens, etc.)
$filePath = __DIR__ . '/public' . $uri;
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false; // Deixa o servidor embutido servir o arquivo
}

// Fallback: tenta servir como arquivo PHP em public/
if (file_exists(__DIR__ . '/public' . $uri . '.php')) {
    require __DIR__ . '/public' . $uri . '.php';
    return true;
}

// 404
http_response_code(404);
echo '<h1>404 - Página não encontrada</h1>';
return true;
