<?php
/**
 * Language switch endpoint
 * 
 * Changes the application language and redirects back to the referring page.
 */
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use App\Shared\Services\I18nService;

// Get the requested language
$lang = $_GET['lang'] ?? $_POST['lang'] ?? null;
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? 'home.php';

if ($lang) {
    $i18n = I18nService::getInstance();
    
    if ($i18n->setLanguage($lang)) {
        // Language changed successfully
        $success = true;
    } else {
        // Invalid language
        $success = false;
    }
}

// If AJAX request, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success ?? false,
        'language' => $lang,
        'message' => $success ? 'Language changed successfully' : 'Invalid language'
    ]);
    exit;
}

// Otherwise redirect
header('Location: ' . $redirect);
exit;
