<?php
declare(strict_types=1);

namespace App\Shared\Services;

/**
 * Cliente do Portal de Governança T2 (lado php-app -> API Node).
 *
 * - Só age quando há um token interno válido na sessão (usuário entrou via
 *   portal). Sem token, todos os métodos são no-op e retornam null/false,
 *   para que o php-app continue funcionando de forma autônoma em DEV.
 * - Comunicação best-effort: falhas de rede NÃO interrompem a análise;
 *   apenas registram log e seguem.
 */
final class GovernanceClient
{
    private ?string $token;
    private string $apiBaseUrl;
    private ?ServerLogger $logger;

    public function __construct(?string $token, string $apiBaseUrl, ?ServerLogger $logger = null)
    {
        $this->token = $token !== '' ? $token : null;
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        $this->logger = $logger;
    }

    /** Cria a partir do contexto preenchido pelo governance-guard. */
    public static function fromGlobals(?ServerLogger $logger = null): self
    {
        $govUser = $GLOBALS['gov_user'] ?? null;
        $token = is_array($govUser) ? (string) ($govUser['token'] ?? '') : '';
        $apiBase = (string) ($GLOBALS['gov_api_base_url'] ?? '');
        return new self($token, $apiBase, $logger);
    }

    public function isEnabled(): bool
    {
        return $this->token !== null && $this->apiBaseUrl !== '';
    }

    /**
     * Registra um evento de uso (métrica de adoção). Best-effort.
     */
    public function recordUsage(string $analysisType): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $this->request('POST', '/gov/usage', ['analysisType' => $analysisType]);
    }

    /**
     * Cria uma proposta com metadados + KPIs. Retorna o ID ou null em falha.
     *
     * @param array<string,mixed> $data
     */
    public function createProposal(array $data): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }
        $resp = $this->request('POST', '/gov/proposals', $data);
        if ($resp === null) {
            return null;
        }
        $decoded = json_decode($resp, true);
        if (is_array($decoded) && isset($decoded['id'])) {
            return (string) $decoded['id'];
        }
        return null;
    }

    /**
     * Faz upload do arquivo pesado (XLSX/PDF) para a proposta. Best-effort.
     */
    public function uploadProposalFile(
        string $proposalId,
        string $filename,
        string $contentType,
        string $bytes
    ): bool {
        if (!$this->isEnabled()) {
            return false;
        }

        $boundary = '----govboundary' . bin2hex(random_bytes(8));
        $eol = "\r\n";
        $body = '--' . $boundary . $eol
            . 'Content-Disposition: form-data; name="file"; filename="'
            . str_replace('"', '', $filename) . '"' . $eol
            . 'Content-Type: ' . $contentType . $eol . $eol
            . $bytes . $eol
            . '--' . $boundary . '--' . $eol;

        $resp = $this->rawRequest(
            'POST',
            '/gov/proposals/' . rawurlencode($proposalId) . '/file',
            $body,
            'multipart/form-data; boundary=' . $boundary
        );
        return $resp !== null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function request(string $method, string $path, array $payload): ?string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return null;
        }
        return $this->rawRequest($method, $path, $json, 'application/json');
    }

    private function rawRequest(
        string $method,
        string $path,
        string $body,
        string $contentType
    ): ?string {
        $url = $this->apiBaseUrl . $path;
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: ' . $contentType,
            'Content-Length: ' . strlen($body),
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($resp === false || $code >= 400) {
                $this->warn("HTTP {$method} {$path} falhou (code={$code}): {$err}");
                return null;
            }
            return is_string($resp) ? $resp : '';
        }

        // Fallback sem cURL (stream context).
        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]);
        $resp = @file_get_contents($url, false, $context);
        if ($resp === false) {
            $this->warn("HTTP {$method} {$path} falhou (stream).");
            return null;
        }
        return $resp;
    }

    private function warn(string $message): void
    {
        if ($this->logger !== null) {
            $this->logger->warn('Governance', $message);
        } else {
            error_log('[Governance] ' . $message);
        }
    }
}
