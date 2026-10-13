<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\Page;
use App\Application\Dto\Paging;
use App\Application\Ports\MemberRepository;
use App\Domain\Member;

/** HU-05 · list and search by name or document (CA-05.2). */
final class SearchMembersService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    /** @return Page<Member> */
    public function execute(?string $q, ?int $page, ?int $size): Page
    {
        $q = $q === null ? null : trim($q);

        return $this->members->search($q === '' ? null : $q, Paging::of($page, $size));
    }
}
