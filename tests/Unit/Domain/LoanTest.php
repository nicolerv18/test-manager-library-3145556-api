<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Book;
use App\Domain\Category;
use App\Domain\DomainError;
use App\Domain\Isbn;
use App\Domain\Loan;
use App\Domain\LoanStatus;
use App\Domain\Quantity;
use App\Domain\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\AssertsDomainErrors;

final class LoanTest extends TestCase
{
    use AssertsDomainErrors;

    private function book(string $isbn, int $copies = 2, string $title = 'Libro'): Book
    {
        return Book::create(Uuid::v4(), $title, 'Autor', Isbn::of($isbn), new Category(Uuid::v4(), 'Novela'), Quantity::of($copies));
    }

    private function loan(string $now = '2026-10-01T10:00:00Z'): Loan
    {
        return Loan::register(Uuid::v4(), Uuid::v4(), Uuid::v4(), new DateTimeImmutable($now));
    }

    public function test_due_date_is_fourteen_days_after_loan(): void
    {
        $loan = $this->loan();
        $item = $loan->addItem($this->book('9780000000001'), Uuid::v4());
        $this->assertSame('2026-10-15T10:00:00+00:00', $item->dueAt->format('c'));
    }

    public function test_item_freezes_title_isbn_and_category(): void
    {
        $book = $this->book('9780000000002', 2, 'Original');
        $item = $this->loan()->addItem($book, Uuid::v4());
        $book->revise('Cambiado', 'Otro', new Category(Uuid::v4(), 'Ciencia'), Quantity::of(5));
        $this->assertSame('Original', $item->bookTitle);
        $this->assertSame('Novela', $item->categoryName);
        $this->assertSame('9780000000002', $item->isbn);
    }

    public function test_adding_a_line_withdraws_one_copy(): void
    {
        $book = $this->book('9780000000003', 2);
        $this->loan()->addItem($book, Uuid::v4());
        $this->assertSame(1, $book->availableCopies());
    }

    public function test_a_loan_admits_at_most_three_lines(): void
    {
        $loan = $this->loan();
        foreach (['9780000000011', '9780000000012', '9780000000013'] as $i) {
            $loan->addItem($this->book($i), Uuid::v4());
        }
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => $loan->addItem($this->book('9780000000014'), Uuid::v4()));
    }

    public function test_same_book_twice_is_rejected(): void
    {
        $loan = $this->loan();
        $book = $this->book('9780000000021', 5);
        $loan->addItem($book, Uuid::v4());
        $this->assertDomainError(DomainError::DUPLICATE_BOOK_IN_LOAN, fn () => $loan->addItem($book, Uuid::v4()));
    }

    public function test_book_without_copies_is_not_available_and_no_line_is_added(): void
    {
        $loan = $this->loan();
        $book = $this->book('9780000000031', 1);
        $loan->addItem($book, Uuid::v4());
        $other = $this->loan();
        $this->assertDomainError(DomainError::BOOK_NOT_AVAILABLE, fn () => $other->addItem($book, Uuid::v4()));
        $this->assertCount(0, $other->items());
    }

    public function test_status_open_overdue_closed(): void
    {
        $loan = $this->loan('2026-10-01T10:00:00Z');
        $item = $loan->addItem($this->book('9780000000041'), Uuid::v4());
        $this->assertSame(LoanStatus::Open, $loan->status(new DateTimeImmutable('2026-10-15T09:59:59Z')));
        $this->assertSame(LoanStatus::Overdue, $loan->status(new DateTimeImmutable('2026-10-15T10:00:01Z')));
        $loan->returnItem($item->id, new DateTimeImmutable('2026-10-20T10:00:00Z'));
        $this->assertSame(LoanStatus::Closed, $loan->status(new DateTimeImmutable('2026-10-21T10:00:00Z')));
    }

    public function test_exactly_at_due_instant_is_not_overdue(): void
    {
        $loan = $this->loan('2026-10-01T10:00:00Z');
        $loan->addItem($this->book('9780000000042'), Uuid::v4());
        $this->assertSame(LoanStatus::Open, $loan->status(new DateTimeImmutable('2026-10-15T10:00:00Z')));
    }

    public function test_days_late_is_rounded_down(): void
    {
        $loan = $this->loan('2026-10-01T10:00:00Z');
        $item = $loan->addItem($this->book('9780000000043'), Uuid::v4());
        $this->assertSame(0, $item->daysLate(new DateTimeImmutable('2026-10-15T09:00:00Z')));
        $this->assertSame(0, $item->daysLate(new DateTimeImmutable('2026-10-16T09:59:59Z')));
        $this->assertSame(1, $item->daysLate(new DateTimeImmutable('2026-10-16T10:00:00Z')));
        $this->assertSame(3, $item->daysLate(new DateTimeImmutable('2026-10-18T23:00:00Z')));
    }

    public function test_returning_twice_is_a_conflict(): void
    {
        $loan = $this->loan();
        $item = $loan->addItem($this->book('9780000000051'), Uuid::v4());
        $loan->returnItem($item->id, new DateTimeImmutable('2026-10-02T10:00:00Z'));
        $this->assertDomainError(DomainError::ITEM_ALREADY_RETURNED, fn () => $loan->returnItem($item->id, new DateTimeImmutable('2026-10-03T10:00:00Z')));
    }

    public function test_unknown_line_is_not_found(): void
    {
        $this->assertDomainError(DomainError::NOT_FOUND, fn () => $this->loan()->returnItem(Uuid::v4(), new DateTimeImmutable()));
    }
}
