<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Security;

use App\Application\Dto\IssuedToken;
use App\Application\Dto\TokenClaims;
use App\Application\Ports\TokenIssuer;
use App\Domain\DomainError;
use App\Domain\Role;
use App\Domain\User;
use DateTimeImmutable;

/**
 * JWT HS256 signed with the secret from the environment (plan D-11). Only `sub`, `role`, `iat` and `exp` travel inside; no personal data.
 * Implemented with hash_hmac (RFC 7519/7515) so the adapter has no dependency beyond PHP itself.
 */
final class Hs256TokenIssuer implements TokenIssuer
{
    public const TTL_SECONDS = 8 * 3600;

    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) < 16) {
            throw new \RuntimeException('JWT_SECRET must be set and at least 16 characters long.');
        }
    }

    public function issue(User $user, DateTimeImmutable $now): IssuedToken
    {
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = self::b64(json_encode([
            'sub' => $user->id,
            'role' => $user->role->value,
            'iat' => $now->getTimestamp(),
            'exp' => $now->getTimestamp() + self::TTL_SECONDS,
        ], JSON_THROW_ON_ERROR));
        $signature = self::b64(hash_hmac('sha256', "{$header}.{$payload}", $this->secret, true));

        return new IssuedToken("{$header}.{$payload}.{$signature}", self::TTL_SECONDS, $user->role);
    }

    public function verify(string $token, DateTimeImmutable $now): TokenClaims
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw DomainError::unauthenticated();
        }
        [$h, $p, $s] = $parts;
        $header = json_decode((string) self::unb64($h), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') { // never trust "none" nor other algorithms
            throw DomainError::unauthenticated();
        }
        $expected = self::b64(hash_hmac('sha256', "{$h}.{$p}", $this->secret, true));
        if (!hash_equals($expected, $s)) {
            throw DomainError::unauthenticated();
        }
        $claims = json_decode((string) self::unb64($p), true);
        if (!is_array($claims) || !is_int($claims['exp'] ?? null) || $claims['exp'] <= $now->getTimestamp()) {
            throw DomainError::unauthenticated();
        }
        $role = Role::tryFrom((string) ($claims['role'] ?? ''));
        if ($role === null || !is_string($claims['sub'] ?? null)) {
            throw DomainError::unauthenticated();
        }

        return new TokenClaims($claims['sub'], $role);
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $text): string|false
    {
        return base64_decode(strtr($text, '-_', '+/'), true);
    }
}
