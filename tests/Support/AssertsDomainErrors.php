<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\DomainError;

trait AssertsDomainErrors
{
    /** Runs `$fn` and asserts it throws a DomainError with the given machine code. Returns the error for further checks. */
    protected function assertDomainError(string $code, callable $fn): DomainError
    {
        try {
            $fn();
        } catch (DomainError $e) {
            $this->assertSame($code, $e->errorCode, "Expected {$code} but got {$e->errorCode}: {$e->getMessage()}");

            return $e;
        }
        $this->fail("Expected DomainError {$code} but nothing was thrown.");
    }
}
