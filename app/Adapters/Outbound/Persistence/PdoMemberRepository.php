<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Dto\Page;
use App\Application\Dto\Paging;
use App\Application\Ports\MemberRepository;
use App\Domain\DocumentNumber;
use App\Domain\DomainError;
use App\Domain\Member;
use DateTimeImmutable;

final class PdoMemberRepository implements MemberRepository
{
    use PdoSupport;

    public function find(string $id): ?Member
    {
        if (preg_match('/^[0-9a-f-]{36}$/i', $id) !== 1) {
            return null;
        }
        $r = $this->run('SELECT id, full_name, document_number FROM library.member WHERE id = :id', ['id' => $id])->fetch(\PDO::FETCH_ASSOC);

        return $r ? $this->map($r) : null;
    }

    public function insert(Member $member): void
    {
        try {
            $this->run(
                'INSERT INTO library.member (id, full_name, document_number) VALUES (:id, :n, :d)',
                ['id' => $member->id, 'n' => $member->fullName, 'd' => $member->document->value],
            );
        } catch (\PDOException $e) {
            throw self::isUnique($e) ? DomainError::documentTaken() : $e;
        }
    }

    public function search(?string $q, Paging $paging): Page
    {
        $where = '';
        $p = [];
        if ($q !== null && trim($q) !== '') {
            $where = ' WHERE full_name ILIKE :q OR document_number ILIKE :q';
            $p['q'] = self::like($q);
        }
        $total = (int) $this->run('SELECT count(*) FROM library.member'.$where, $p)->fetchColumn();
        $rows = $this->run('SELECT id, full_name, document_number FROM library.member'.$where.' ORDER BY full_name, id'.self::limit($paging), $p)->fetchAll(\PDO::FETCH_ASSOC);

        return new Page(array_map(fn (array $r): Member => $this->map($r), $rows), $paging->page, $paging->size, $total);
    }

    public function unreturnedCount(string $memberId): int
    {
        return (int) $this->run(
            'SELECT count(*) FROM library.loan_item i JOIN library.loan l ON l.id = i.loan_id WHERE l.member_id = :m AND i.returned_at IS NULL',
            ['m' => $memberId],
        )->fetchColumn();
    }

    public function hasOverdue(string $memberId, DateTimeImmutable $now): bool
    {
        return (bool) $this->run(
            'SELECT 1 FROM library.loan_item i JOIN library.loan l ON l.id = i.loan_id WHERE l.member_id = :m AND i.returned_at IS NULL AND i.due_at < :now LIMIT 1',
            ['m' => $memberId, 'now' => self::ts($now)],
        )->fetchColumn();
    }

    /** @param array<string, string> $r */
    private function map(array $r): Member
    {
        return new Member($r['id'], $r['full_name'], DocumentNumber::of($r['document_number']));
    }
}
