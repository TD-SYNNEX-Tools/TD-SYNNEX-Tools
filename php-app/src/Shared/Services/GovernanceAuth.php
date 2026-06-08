<?php
declare(strict_types=1);

namespace App\Shared\Services;

/**
 * Verificador self-contained de JWT HS256 emitido pela API Node.
 *
 * Não depende de pacotes externos: faz apenas HMAC-SHA256 + base64url,
 * comparação em tempo constante e checagem de exp/nbf/iss/aud.
 */
final class GovernanceAuth
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function fromDefaultConfig(): self
    {
        /** @var array<string,mixed> $config */
        $config = require __DIR__ . '/../Config/governance.php';
        return new self($config);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function apiBaseUrl(): string
    {
        return (string) ($this->config['api_base_url'] ?? '');
    }

    public function portalLoginUrl(): string
    {
        return (string) ($this->config['portal_login'] ?? '/');
    }

    /**
     * Valida o token e retorna o payload (claims) ou null se inválido.
     *
     * @return array<string,mixed>|null
     */
    public function verify(string $token): ?array
    {
        $secret = (string) ($this->config['token_secret'] ?? '');
        if ($secret === '') {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$h64, $p64, $s64] = $parts;

        $header = $this->decodeJson($h64);
        $payload = $this->decodeJson($p64);
        if ($header === null || $payload === null) {
            return null;
        }

        if (($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? 'JWT') !== 'JWT') {
            return null;
        }

        $expected = $this->base64UrlEncode(
            hash_hmac('sha256', $h64 . '.' . $p64, $secret, true)
        );
        $provided = $s64;
        if (!hash_equals($expected, $provided)) {
            return null;
        }

        $now = time();
        $leeway = (int) ($this->config['leeway'] ?? 0);

        if (isset($payload['exp']) && $now > ((int) $payload['exp'] + $leeway)) {
            return null;
        }
        if (isset($payload['nbf']) && $now < ((int) $payload['nbf'] - $leeway)) {
            return null;
        }
        if (isset($this->config['issuer']) && isset($payload['iss'])
            && $payload['iss'] !== $this->config['issuer']) {
            return null;
        }
        if (isset($this->config['audience']) && isset($payload['aud'])
            && $payload['aud'] !== $this->config['audience']) {
            return null;
        }

        return $payload;
    }

    /** @return array<string,mixed>|null */
    private function decodeJson(string $segment): ?array
    {
        $json = $this->base64UrlDecode($segment);
        if ($json === null) {
            return null;
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    private function base64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $b64): ?string
    {
        $remainder = strlen($b64) % 4;
        if ($remainder) {
            $b64 .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($b64, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
