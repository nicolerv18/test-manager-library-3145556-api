<?php

declare(strict_types=1);

namespace Tests\Unit\Adapters;

use App\Adapters\Inbound\Http\StatusMap;
use App\Adapters\Outbound\Security\Hs256TokenIssuer;
use App\Adapters\Outbound\Time\DemoHeaderClock;
use App\Domain\DomainError;
use App\Domain\Role;
use App\Domain\User;
use PHPUnit\Framework\TestCase;
use Tests\Support\AssertsDomainErrors;

final class StatusMapTest extends TestCase
{
    use AssertsDomainErrors;

    public function test_every_business_code_has_the_contract_status(): void
    {
        $expected = [
            'INVALID_CREDENTIALS' => 401, 'UNAUTHENTICATED' => 401, 'FORBIDDEN_ROLE' => 403, 'NOT_FOUND' => 404,
            'USERNAME_TAKEN' => 409, 'ISBN_TAKEN' => 409, 'DOCUMENT_TAKEN' => 409, 'BOOK_HAS_OPEN_LOANS' => 409,
            'BOOK_NOT_AVAILABLE' => 409, 'MEMBER_HAS_OVERDUE' => 409, 'ITEM_ALREADY_RETURNED' => 409,
            'MEMBER_LIMIT_EXCEEDED' => 422, 'DUPLICATE_BOOK_IN_LOAN' => 422, 'VALIDATION_ERROR' => 422, 'INVALID_DATE_RANGE' => 422,
        ];
        foreach ($expected as $code => $status) {
            $this->assertSame($status, StatusMap::for($code), $code);
        }
        $this->assertSame(count($expected), count(StatusMap::codes()));
    }

    public function test_token_roundtrip_expiry_tampering_and_alg_none(): void
    {
        $issuer = new Hs256TokenIssuer('a-test-secret-of-sufficient-length');
        $user = User::create('11111111-1111-4111-8111-111111111111', 'ana', 'h', Role::Librarian);
        $now = new \DateTimeImmutable('2026-10-01T10:00:00Z');
        $t = $issuer->issue($user, $now);
        $this->assertSame(28800, $t->expiresIn);
        $claims = $issuer->verify($t->accessToken, $now->modify('+1 hour'));
        $this->assertSame($user->id, $claims->userId);
        $this->assertSame(Role::Librarian, $claims->role);

        $this->assertDomainError(DomainError::UNAUTHENTICATED, fn () => $issuer->verify($t->accessToken, $now->modify('+9 hours')));
        [$h, $p, $s] = explode('.', $t->accessToken);
        $admin = rtrim(strtr(base64_encode(json_encode(['sub' => $user->id, 'role' => 'admin', 'iat' => 0, 'exp' => 4102444800])), '+/', '-_'), '=');
        $this->assertDomainError(DomainError::UNAUTHENTICATED, fn () => $issuer->verify("{$h}.{$admin}.{$s}", $now));
        $none = rtrim(strtr(base64_encode('{"alg":"none"}'), '+/', '-_'), '=');
        $this->assertDomainError(DomainError::UNAUTHENTICATED, fn () => $issuer->verify("{$none}.{$p}.", $now));
        $this->assertDomainError(DomainError::UNAUTHENTICATED, fn () => $issuer->verify('garbage', $now));
        $other = new Hs256TokenIssuer('another-secret-of-sufficient-length');
        $this->assertDomainError(DomainError::UNAUTHENTICATED, fn () => $other->verify($t->accessToken, $now));
    }

    public function test_demo_header_is_ignored_unless_demo_mode(): void
    {
        $header = static fn (): string => '2020-01-01T00:00:00Z';
        $this->assertTrue((new DemoHeaderClock(false, $header))->now()->format('Y') >= '2026');
        $this->assertSame('2020-01-01T00:00:00+00:00', (new DemoHeaderClock(true, $header))->now()->format('c'));
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => (new DemoHeaderClock(true, static fn (): string => 'mañana'))->now());
        $this->assertTrue((new DemoHeaderClock(false, static fn (): string => 'mañana'))->now()->format('Y') >= '2026');
    }
}
