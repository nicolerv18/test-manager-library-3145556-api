<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Dto\Page;
use App\Application\Dto\Paging;
use App\Domain\Member;
use DateTimeImmutable;

interface MemberRepository
{
    public function find(string $id): ?Member;

    /** @throws \App\Domain\DomainError DOCUMENT_TAKEN */
    public function insert(Member $member): void;

    /** @return Page<Member> partial match on name or document, ordered by name */
    public function search(?string $q, Paging $paging): Page;

    /** Lines of this member that are not returned yet (access pattern Q7). */
    public function unreturnedCount(string $memberId): int;

    /** True when any unreturned line of this member has `due_at` earlier than `$now`. */
    public function hasOverdue(string $memberId, DateTimeImmutable $now): bool;
}
