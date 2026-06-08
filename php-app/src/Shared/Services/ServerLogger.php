<?php
declare(strict_types=1);

namespace App\Shared\Services;

/**
 * Captura eventos do servidor (chamadas API, parse de arquivos, etc.)
 * e os grava em arquivo *fora* do $_SESSION, para que o polling
 * (server-logs.php) consiga ler em tempo real mesmo enquanto outra
 * requisicao POST mantem o session-lock.
 *
 * Cada linha do arquivo eh um JSON (NDJSON), o que torna append e leitura
 * incrementais O(N) sem precisar reescrever o arquivo inteiro.
 */
class ServerLogger
{
    private const MAX_ENTRIES   = 1000;
    private const TRIM_INTERVAL = 200; // a cada N entries, trim do arquivo

    private static ?self $instance = null;
    private string $logFile;
    private int $writeCounter = 0;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $dir = dirname(__DIR__, 3) . '/uploads/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        // Logs sao por-token-de-sessao (cada usuario ve so os proprios).
        $token = $this->sessionToken();
        $this->logFile = $dir . '/logs_' . $token . '.ndjson';
    }

    private function sessionToken(): string
    {
        // SEMPRE usar o PHPSESSID do cookie (disponivel antes mesmo de session_start).
        // Isso garante que POST (analise) e GET (polling de logs) usem o mesmo arquivo
        // - independente de quem chamou session_start primeiro.
        $sessionName = session_name() ?: 'PHPSESSID';
        $sid = $_COOKIE[$sessionName] ?? '';
        if ($sid === '' && session_status() === PHP_SESSION_ACTIVE) {
            $sid = session_id();
        }
        $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$sid);
        return $clean !== '' ? $clean : 'default';
    }

    /**
     * Registra um evento de log.
     */
    public function log(string $level, string $source, string $message, array $meta = []): void
    {
        $entry = [
            'ts'      => microtime(true),
            'time'    => date('H:i:s') . '.' . substr(sprintf('%.3f', microtime(true)), -3),
            'level'   => $level,
            'source'  => $source,
            'message' => $message,
            'meta'    => $meta,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        // Append atomico (LOCK_EX evita corrompimento entre requisicoes concorrentes)
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);

        $this->writeCounter++;
        if ($this->writeCounter % self::TRIM_INTERVAL === 0) {
            $this->trim();
        }
    }

    public function info(string $source, string $message, array $meta = []): void     { $this->log('info',    $source, $message, $meta); }
    public function api(string $source, string $message, array $meta = []): void      { $this->log('api',     $source, $message, $meta); }
    public function success(string $source, string $message, array $meta = []): void  { $this->log('success', $source, $message, $meta); }
    public function warn(string $source, string $message, array $meta = []): void     { $this->log('warn',    $source, $message, $meta); }
    public function error(string $source, string $message, array $meta = []): void    { $this->log('error',   $source, $message, $meta); }
    public function debug(string $source, string $message, array $meta = []): void    { $this->log('debug',   $source, $message, $meta); }

    /**
     * Retorna logs a partir de um indice (para polling incremental).
     */
    public function getLogs(int $sinceIndex = 0): array
    {
        if (!is_file($this->logFile)) return [];
        $lines = @file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $slice = array_slice($lines, $sinceIndex);
        $out = [];
        foreach ($slice as $l) {
            $decoded = json_decode($l, true);
            if (is_array($decoded)) $out[] = $decoded;
        }
        return $out;
    }

    public function getCount(): int
    {
        if (!is_file($this->logFile)) return 0;
        $lines = @file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        return count($lines);
    }

    public function clear(): void
    {
        if (is_file($this->logFile)) {
            @unlink($this->logFile);
        }
    }

    /**
     * Mantem somente as ultimas MAX_ENTRIES linhas no arquivo.
     */
    private function trim(): void
    {
        if (!is_file($this->logFile)) return;
        $lines = @file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        if (count($lines) <= self::MAX_ENTRIES) return;
        $kept = array_slice($lines, -self::MAX_ENTRIES);
        @file_put_contents($this->logFile, implode("\n", $kept) . "\n", LOCK_EX);
    }
}
