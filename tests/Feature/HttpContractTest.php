<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\DomainError;
use Tests\TestCase;

/**
 * Contract test of the HTTP surface (api-contract.md §1 and §2, RNF-4): health is public, a missing token is a 401,
 * and every failure — including the framework's own 404 and 405 — is rendered as the single error body
 * `{code, message, details}` with no trace, SQL or password.
 *
 * These paths never touch the database: the token is rejected before persistence, and unknown routes / wrong methods
 * are resolved by the router. The full end-to-end walkthrough (with data) lives in the integration suite.
 */
final class HttpContractTest extends TestCase
{
    public function test_health_is_public_and_reports_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_missing_token_is_401_with_the_single_error_body(): void
    {
        $response = $this->getJson('/api/v1/books');

        $response->assertStatus(401);
        $this->assertContractError($response->json(), DomainError::UNAUTHENTICATED);
    }

    public function test_unknown_route_is_404_with_the_single_error_body(): void
    {
        $response = $this->getJson('/api/v1/route-that-does-not-exist');

        $response->assertStatus(404);
        $this->assertContractError($response->json(), DomainError::NOT_FOUND);
    }

    public function test_wrong_method_is_405_with_the_single_error_body(): void
    {
        $response = $this->deleteJson('/api/v1/health');

        $response->assertStatus(405);
        $this->assertContractError($response->json(), 'METHOD_NOT_ALLOWED');
    }

    public function test_the_error_body_keeps_the_same_shape_and_leaks_nothing(): void
    {
        $json = $this->getJson('/api/v1/books')->json();
        $encoded = strtolower((string) json_encode($json));

        $this->assertSame(['code', 'message', 'details'], array_keys($json));
        $this->assertStringNotContainsString('sql', $encoded);
        $this->assertStringNotContainsString('password', $encoded);
        $this->assertStringNotContainsString('stack', $encoded);
        $this->assertStringNotContainsString('exception', $encoded);
    }

    /**
     * @param array<string, mixed> $json
     */
    private function assertContractError(array $json, string $expectedCode): void
    {
        $this->assertSame(
            ['code', 'message', 'details'],
            array_keys($json),
            'Every error must be the single body {code, message, details}.',
        );
        $this->assertSame($expectedCode, $json['code']);
        $this->assertIsString($json['message']);
        $this->assertNotSame('', $json['message']);
        $this->assertIsArray($json['details']);
    }
}
