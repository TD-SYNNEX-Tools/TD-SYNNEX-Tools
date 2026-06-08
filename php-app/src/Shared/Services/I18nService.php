<?php
/**
 * Internationalization (i18n) Service
 * 
 * Provides multi-language support for the application.
 * Supports: Portuguese (pt-BR), English (en), Spanish (es)
 */

namespace App\Shared\Services;

class I18nService
{
    private static ?I18nService $instance = null;
    private string $currentLanguage;
    private array $translations = [];
    private array $availableLanguages = ['pt-BR', 'en', 'es'];
    private string $defaultLanguage = 'pt-BR';
    private string $langDir;

    private function __construct()
    {
        $this->langDir = __DIR__ . '/../../../lang';
        $this->currentLanguage = $this->detectLanguage();
        $this->loadTranslations();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): I18nService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Detect current language from session, cookie, or browser
     */
    private function detectLanguage(): string
    {
        // 1. Check session
        if (isset($_SESSION['language']) && in_array($_SESSION['language'], $this->availableLanguages)) {
            return $_SESSION['language'];
        }

        // 2. Check cookie
        if (isset($_COOKIE['language']) && in_array($_COOKIE['language'], $this->availableLanguages)) {
            return $_COOKIE['language'];
        }

        // 3. Check browser language
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            $langMap = [
                'pt' => 'pt-BR',
                'en' => 'en',
                'es' => 'es'
            ];
            if (isset($langMap[$browserLang])) {
                return $langMap[$browserLang];
            }
        }

        return $this->defaultLanguage;
    }

    /**
     * Load translation file for current language
     */
    private function loadTranslations(): void
    {
        $langFile = $this->langDir . '/' . $this->currentLanguage . '.php';
        
        if (file_exists($langFile)) {
            $this->translations = require $langFile;
        } else {
            // Fallback to default language
            $defaultFile = $this->langDir . '/' . $this->defaultLanguage . '.php';
            if (file_exists($defaultFile)) {
                $this->translations = require $defaultFile;
            }
        }
    }

    /**
     * Set the current language
     */
    public function setLanguage(string $language): bool
    {
        if (!in_array($language, $this->availableLanguages)) {
            return false;
        }

        $this->currentLanguage = $language;
        
        // Store in session
        $_SESSION['language'] = $language;
        
        // Store in cookie (30 days)
        setcookie('language', $language, time() + (30 * 24 * 60 * 60), '/');
        
        // Reload translations
        $this->loadTranslations();
        
        return true;
    }

    /**
     * Get current language
     */
    public function getLanguage(): string
    {
        return $this->currentLanguage;
    }

    /**
     * Get available languages
     */
    public function getAvailableLanguages(): array
    {
        return $this->availableLanguages;
    }

    /**
     * Get language names for display
     */
    public function getLanguageNames(): array
    {
        return [
            'pt-BR' => 'Português',
            'en' => 'English',
            'es' => 'Español'
        ];
    }

    /**
     * Get language flags/icons
     */
    public function getLanguageFlags(): array
    {
        return [
            'pt-BR' => '🇧🇷',
            'en' => '🇺🇸',
            'es' => '🇪🇸'
        ];
    }

    /**
     * Translate a key
     * 
     * @param string $key Dot notation key (e.g., 'nav.home', 'buttons.save')
     * @param array $params Parameters for string interpolation
     * @return string Translated string or key if not found
     */
    public function translate(string $key, array $params = []): string
    {
        $keys = explode('.', $key);
        $value = $this->translations;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                // Key not found, return the key itself
                return $key;
            }
        }

        if (!is_string($value)) {
            return $key;
        }

        // Replace parameters
        foreach ($params as $param => $replacement) {
            $value = str_replace(':' . $param, $replacement, $value);
        }

        return $value;
    }

    /**
     * Shorthand for translate
     */
    public function t(string $key, array $params = []): string
    {
        return $this->translate($key, $params);
    }

    /**
     * Get all translations for a section
     */
    public function getSection(string $section): array
    {
        return $this->translations[$section] ?? [];
    }

    /**
     * Check if a translation key exists
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->translations;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Get language code for HTML lang attribute
     */
    public function getHtmlLang(): string
    {
        $langMap = [
            'pt-BR' => 'pt-BR',
            'en' => 'en',
            'es' => 'es'
        ];
        return $langMap[$this->currentLanguage] ?? 'pt-BR';
    }
}

/**
 * Global helper function for translations
 */
function __($key, $params = []): string
{
    return I18nService::getInstance()->translate($key, $params);
}

/**
 * Echo translation directly
 */
function _e($key, $params = []): void
{
    echo I18nService::getInstance()->translate($key, $params);
}
