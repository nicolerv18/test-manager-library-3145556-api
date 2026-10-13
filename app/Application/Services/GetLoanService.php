<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\LoanRepository;
use App\Domain\DomainError;
use App\Domain\Loan;

/** HU-08 · loan detail (CA-08.1). */
final class GetLoanService
{
    public function __construct(private readonly LoanRepository $loans)
    {
    }

    public function execute(string $id): Loan
    {
        return $this->loans->find($id) ?? throw DomainError::notFound('El préstamo');
    }
}
