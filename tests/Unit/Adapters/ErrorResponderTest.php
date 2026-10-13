<?php

declare(strict_types=1);

namespace Tests\Unit\Adapters;

use App\Adapters\Inbound\Http\ErrorResponder;
use App\Adapters\Inbound\Http\StatusMap;
use App\Domain\DomainError;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Contract test of the error renderer (api-contract.md §2, RNF-4): every business code and every framework failure
 * is rendered as the single body `{code, message, details}` with the status of the contract, and never leaks internals.
 */
final class ErrorResponderTest extends TestCase
{
    public function test_every_contract_code_is_rendered_with_its_status_and_the_single_body(): void
    {
        /** @var array<string, DomainError> $errors */
        $errors = [
            DomainError::INVALID_CREDENTIALS => DomainError::invalidCredentials(),
            DomainError::UNAUTHENTICATED => DomainError::unauthenticated(),
            DomainError::FORBIDDEN_ROLE => DomainError::forbiddenRole(),
            DomainError::NOT_FOUND => DomainError::notFound(),
            DomainError::USERNAME_TAKEN => DomainError::usernameTaken(),
            DomainError::ISBN_TAKEN => DomainError::isbnTaken(),
            DomainError::DOCUMENT_TAKEN => DomainError::documentTaken(),
            DomainError::BOOK_HAS_OPEN_LOANS => DomainError::bookHasOpenLoans('Cien años de soledad'),
            DomainError::BOOK_NOT_AVAILABLE => DomainError::bookNotAvailable('Cien años de soledad'),
            DomainError::MEMBER_HAS_OVERDUE => DomainError::memberHasOverdue(),
            DomainError::ITEM_ALREADY_RETURNED => DomainError::itemAlreadyReturned(),
            DomainError::MEMBER_LIMIT_EXCEEDED => DomainError::memberLimitExceeded(2, 1, 3),
            DomainError::DUPLICATE_BOOK_IN_LOAN => DomainError::duplicateBookInLoan(0),
            DomainError::VALIDATION_ERROR => DomainError::validation([['field' => 'title', 'issue' => 'requerido']]),
            DomainError::INVALID_DATE_RANGE => DomainError::invalidDateRange(),
        ];

        foreach ($errors as $code => $error) {
            $response = ErrorResponder::render($error);
            $payload = $response->getData(true);

            $this->assertSame(StatusMap::for($code), $response->getStatusCode(), $code);
            $this->assertSame(['code', 'message', 'details'], array_keys($payload), $code);
            $this->assertSame($code, $payload['code'], $code);
            $this->assertNotSame('', $payload['message'], $code);
        }
    }

    public function test_framework_failures_use_the_same_body(): void
    {
        $notFound = ErrorResponder::render(new NotFoundHttpException())->getData(true);
        $this->assertSame(DomainError::NOT_FOUND, $notFound['code']);
        $this->assertSame(['code', 'message', 'details'], array_keys($notFound));

        $notAllowed = ErrorResponder::render(new MethodNotAllowedHttpException(['GET']));
        $this->assertSame(405, $notAllowed->getStatusCode());
        $this->assertSame('METHOD_NOT_ALLOWED', $notAllowed->getData(true)['code']);
    }

    public function test_an_unexpected_failure_is_500_and_never_leaks_internals(): void
    {
        $response = ErrorResponder::render(new RuntimeException('boom at /srv/app/secret.php line 42'));
        $payload = $response->getData(true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('INTERNAL_ERROR', $payload['code']);
        $this->assertSame(['code', 'message', 'details'], array_keys($payload));

        $encoded = strtolower((string) json_encode($payload));
        $this->assertStringNotContainsString('secret.php', $encoded);
        $this->assertStringNotContainsString('line 42', $encoded);
        $this->assertStringNotContainsString('boom', $encoded);
    }
}
