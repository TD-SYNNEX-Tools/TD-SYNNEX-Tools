<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use App\Shared\Services\ServerLogger;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

$logger = ServerLogger::getInstance();
$action = $_GET['action'] ?? 'get';

if ($action === 'clear') {
    $logger->clear();
    echo json_encode(['ok' => true, 'count' => 0]);
    exit;
}

// Polling incremental: retorna logs a partir do índice informado
$since = max(0, (int)($_GET['since'] ?? 0));
$logs  = $logger->getLogs($since);
$total = $logger->getCount();

echo json_encode([
    'ok'    => true,
    'since' => $since,
    'total' => $total,
    'logs'  => $logs,
], JSON_UNESCAPED_UNICODE);
