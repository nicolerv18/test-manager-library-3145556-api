<?php

declare(strict_types=1);

namespace App\Domain;

/** Computed, never stored (constitution Article VII). */
enum LoanStatus: string
{
    case Open = 'open';
    case Overdue = 'overdue';
    case Closed = 'closed';
}
