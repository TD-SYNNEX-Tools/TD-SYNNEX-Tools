<?php
/**
 * Reset de Sessão — limpa preços salvos para forçar novos defaults
 */
session_start();

require_once __DIR__ . '/../vendor/autoload.php';
use App\Shared\Services\ResultsCache;
ResultsCache::forget('financialResults');

session_destroy();
header('Location: home.php');
exit;
