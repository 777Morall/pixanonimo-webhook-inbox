<?php
declare(strict_types=1);

namespace PixAnonimo\Webhooks;

// Exemplo didático: https://pixanonimo.net/docs
final class Signature
{
    public function __construct(private readonly string $secret, private readonly int $toleranceSeconds = 300)
    {
        if (strlen($secret) < 32 || $toleranceSeconds < 1 || $toleranceSeconds > 3600) {
            throw new \InvalidArgumentException('Use segredo de pelo menos 32 bytes e tolerância entre 1 e 3600 segundos.');
        }
    }

    public function verify(string $rawBody, string $timestamp, string $signature, int $now): bool
    {
        if (!preg_match('/\A[0-9]{1,10}\z/D', $timestamp)
            || !preg_match('/\A[0-9a-f]{64}\z/D', $signature)
            || abs($now - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $this->secret);
        return hash_equals($expected, $signature);
    }
}
