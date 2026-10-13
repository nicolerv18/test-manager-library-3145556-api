<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\MemberRepository;
use App\Domain\DomainError;
use App\Domain\Member;

/** Member detail with `active_loans` = count of unreturned lines (contract §3.5). */
final class GetMemberService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    /** @return array{member: Member, active_loans: int} */
    public function execute(string $id): array
    {
        $member = $this->members->find($id) ?? throw DomainError::notFound('El socio');

        return ['member' => $member, 'active_loans' => $this->members->unreturnedCount($id)];
    }
}
