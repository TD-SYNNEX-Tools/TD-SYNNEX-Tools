<?php
declare(strict_types=1);

namespace App\Shared\Services;

/**
 * ResultsCache — armazena estruturas grandes (resultado da analise financeira)
 * em arquivo no disco em vez de dentro do $_SESSION.
 *
 * Isso evita que toda requisicao tenha que (de)serializar centenas de MB
 * de dados, eliminando estouros de memoria com CSVs de 100+ MB.
 *
 * Uso:
 *   ResultsCache::put('financialResults', $results);
 *   $results = ResultsCache::get('financialResults');
 *   ResultsCache::forget('financialResults');
 */
final class ResultsCache
{
    private const TTL_SECONDS = 7200; // 2h

    private static function dir(): string
    {
        $dir = dirname(__DIR__, 3) . '/uploads/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['_resultsCacheToken'])) {
            $_SESSION['_resultsCacheToken'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['_resultsCacheToken'];
    }

    private static function path(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
        return self::dir() . '/' . self::token() . '_' . $safeKey . '.bin';
    }

    public static function put(string $key, $value): bool
    {
        self::gc();
        $path = self::path($key);
        $payload = serialize($value);
        // Escrita atomica para evitar leituras parciais
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $payload, LOCK_EX) === false) {
            return false;
        }
        return @rename($tmp, $path);
    }

    public static function get(string $key)
    {
        $path = self::path($key);
        if (!is_file($path)) {
            return null;
        }
        // Atualiza mtime (mantem vivo enquanto for usado)
        @touch($path);
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = @unserialize($raw, ['allowed_classes' => false]);
        return $data === false ? null : $data;
    }

    public static function has(string $key): bool
    {
        return is_file(self::path($key));
    }

    public static function forget(string $key): void
    {
        $path = self::path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Limpa caches antigos (TTL ultrapassado) — best-effort.
     */
    public static function gc(): void
    {
        $dir = self::dir();
        $cutoff = time() - self::TTL_SECONDS;
        foreach (glob($dir . '/*.bin') ?: [] as $file) {
            if (@filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
        foreach (glob($dir . '/*.tmp') ?: [] as $file) {
            if (@filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
