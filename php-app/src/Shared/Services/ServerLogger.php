<?php
declare(strict_types=1);

namespace App\Shared\Services;

/**
 * Captura eventos do servidor (chamadas API, parse de arquivos, etc.)
 * e armazena na sessão para exibição em tempo real no frontend.
 */
class ServerLogger
{
    private const SESSION_KEY = '_serverLogs';
    private const MAX_ENTRIES = 500;

    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    /**
     * Registra um evento de log.
     *
     * @param string $level   info|warn|error|api|success|debug
     * @param string $source  Ex: MicrosoftPricingApi, FinancialAnalyzer
     * @param string $message Descrição do evento
     * @param array  $meta    Dados extras (url, status, timing, etc.)
     */
    public function log(string $level, string $source, string $message, array $meta = []): void
    {
        $entry = [
            'ts'      => microtime(true),
            'time'    => date('H:i:s') . '.' . substr((string)microtime(true), -3),
            'level'   => $level,
            'source'  => $source,
            'message' => $message,
            'meta'    => $meta,
        ];

        $_SESSION[self::SESSION_KEY][] = $entry;

        // Limitar tamanho
        if (count($_SESSION[self::SESSION_KEY]) > self::MAX_ENTRIES) {
            $_SESSION[self::SESSION_KEY] = array_slice($_SESSION[self::SESSION_KEY], -self::MAX_ENTRIES);
        }
    }

    public function info(string $source, string $message, array $meta = []): void
    {
        $this->log('info', $source, $message, $meta);
    }

    public function api(string $source, string $message, array $meta = []): void
    {
        $this->log('api', $source, $message, $meta);
    }

    public function success(string $source, string $message, array $meta = []): void
    {
        $this->log('success', $source, $message, $meta);
    }

    public function warn(string $source, string $message, array $meta = []): void
    {
        $this->log('warn', $source, $message, $meta);
    }

    public function error(string $source, string $message, array $meta = []): void
    {
        $this->log('error', $source, $message, $meta);
    }

    public function debug(string $source, string $message, array $meta = []): void
    {
        $this->log('debug', $source, $message, $meta);
    }

    /**
     * Retorna logs a partir de um índice (para polling incremental).
     */
    public function getLogs(int $sinceIndex = 0): array
    {
        $all = $_SESSION[self::SESSION_KEY] ?? [];
        return array_slice($all, $sinceIndex);
    }

    /**
     * Retorna total de logs no buffer.
     */
    public function getCount(): int
    {
        return count($_SESSION[self::SESSION_KEY] ?? []);
    }

    /**
     * Limpa todos os logs.
     */
    public function clear(): void
    {
        $_SESSION[self::SESSION_KEY] = [];
    }
}
