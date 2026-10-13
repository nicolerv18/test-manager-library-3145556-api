<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\MemberRepository;
use App\Domain\DocumentNumber;
use App\Domain\Member;
use App\Domain\Uuid;

/** HU-05 · create (CA-05.1). The document is unique: the engine answers DOCUMENT_TAKEN. */
final class CreateMemberService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    public function execute(string $fullName, string $documentNumber): Member
    {
        $member = Member::create(Uuid::v4(), $fullName, DocumentNumber::of($documentNumber));
        $this->members->insert($member);

        return $member;
    }
}
