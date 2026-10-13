<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http;

use App\Application\Dto\OverdueLine;
use App\Application\Dto\Page;
use App\Application\Dto\ReportRow;
use App\Domain\Book;
use App\Domain\Category;
use App\Domain\Loan;
use App\Domain\LoanItem;
use App\Domain\Member;
use App\Domain\User;
use DateTimeImmutable;
use DateTimeZone;

/** Domain objects -> JSON shapes of api-contract §3. Never exposes password hashes. */
final class Presenter
{
    public static function date(?DateTimeImmutable $d): ?string
    {
        return $d?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /** @param callable(mixed): array<string, mixed> $map */
    public static function page(Page $page, callable $map): array
    {
        return ['items' => array_map($map, $page->items), 'page' => $page->page, 'size' => $page->size, 'total' => $page->total];
    }

    public static function category(Category $c): array
    {
        return ['id' => $c->id, 'name' => $c->name];
    }

    public static function book(Book $b): array
    {
        return [
            'id' => $b->id,
            'title' => $b->title(),
            'author' => $b->author(),
            'isbn' => $b->isbn->value,
            'category' => self::category($b->category()),
            'total_copies' => $b->totalCopies(),
            'available_copies' => $b->availableCopies(),
            'cover_key' => $b->coverKey(),
        ];
    }

    public static function member(Member $m): array
    {
        return ['id' => $m->id, 'full_name' => $m->fullName, 'document_number' => $m->document->value];
    }

    public static function user(User $u): array
    {
        return ['id' => $u->id, 'username' => $u->username, 'role' => $u->role->value];
    }

    public static function item(LoanItem $i): array
    {
        return [
            'id' => $i->id,
            'book_id' => $i->bookId,
            'book_title' => $i->bookTitle,
            'isbn' => $i->isbn,
            'category_name' => $i->categoryName,
            'due_at' => self::date($i->dueAt),
            'returned_at' => self::date($i->returnedAt()),
        ];
    }

    public static function loan(Loan $l, DateTimeImmutable $now): array
    {
        return [
            'id' => $l->id,
            'member_id' => $l->memberId,
            'loaned_at' => self::date($l->loanedAt),
            'status' => $l->status($now)->value,
            'items' => array_map([self::class, 'item'], $l->items()),
        ];
    }

    public static function overdue(OverdueLine $o): array
    {
        return [
            'loan_id' => $o->loanId,
            'item_id' => $o->itemId,
            'book_title' => $o->bookTitle,
            'member_name' => $o->memberName,
            'due_at' => self::date($o->dueAt),
            'days_late' => $o->daysLate,
        ];
    }

    public static function reportRow(ReportRow $r): array
    {
        return ['book_id' => $r->bookId, 'book_title' => $r->bookTitle, 'category_name' => $r->categoryName, 'times_borrowed' => $r->timesBorrowed];
    }
}
