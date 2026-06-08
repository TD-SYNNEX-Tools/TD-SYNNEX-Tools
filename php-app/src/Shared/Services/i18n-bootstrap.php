<?php
/**
 * i18n Bootstrap File
 * 
 * Include this file at the beginning of your PHP pages to enable internationalization.
 * 
 * Usage:
 *   require_once __DIR__ . '/../src/Shared/Services/i18n-bootstrap.php';
 *   
 *   // Then use translations:
 *   echo __('nav.home');
 *   _e('messages.success');
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load the I18n service
require_once __DIR__ . '/I18nService.php';

// Get the i18n instance
$i18n = \App\Shared\Services\I18nService::getInstance();

// Define global helper functions if not already defined
if (!function_exists('__')) {
    /**
     * Translate a key and return the string
     * 
     * @param string $key Dot notation key (e.g., 'nav.home')
     * @param array $params Parameters for string interpolation
     * @return string Translated string
     */
    function __(string $key, array $params = []): string
    {
        global $i18n;
        return $i18n->translate($key, $params);
    }
}

if (!function_exists('_e')) {
    /**
     * Translate a key and echo the result
     * 
     * @param string $key Dot notation key
     * @param array $params Parameters for string interpolation
     */
    function _e(string $key, array $params = []): void
    {
        echo __(key: $key, params: $params);
    }
}

if (!function_exists('getLang')) {
    /**
     * Get the current language code
     * 
     * @return string Current language code (e.g., 'pt-BR', 'en', 'es')
     */
    function getLang(): string
    {
        global $i18n;
        return $i18n->getLanguage();
    }
}

if (!function_exists('getHtmlLang')) {
    /**
     * Get the HTML lang attribute value
     * 
     * @return string Language code for HTML lang attribute
     */
    function getHtmlLang(): string
    {
        global $i18n;
        return $i18n->getHtmlLang();
    }
}

if (!function_exists('getI18n')) {
    /**
     * Get the I18n service instance
     * 
     * @return \App\Shared\Services\I18nService
     */
    function getI18n(): \App\Shared\Services\I18nService
    {
        global $i18n;
        return $i18n;
    }
}
