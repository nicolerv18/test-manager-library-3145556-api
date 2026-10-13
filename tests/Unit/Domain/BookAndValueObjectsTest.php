<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Book;
use App\Domain\Category;
use App\Domain\DateRange;
use App\Domain\DomainError;
use App\Domain\Isbn;
use App\Domain\Quantity;
use App\Domain\Uuid;
use PHPUnit\Framework\TestCase;
use Tests\Support\AssertsDomainErrors;

final class BookAndValueObjectsTest extends TestCase
{
    use AssertsDomainErrors;

    private function book(int $copies = 3): Book
    {
        return Book::create(Uuid::v4(), ' Título ', ' Autor ', Isbn::of('9780000000100'), new Category(Uuid::v4(), 'Novela'), Quantity::of($copies));
    }

    public function test_isbn_requires_exactly_thirteen_digits(): void
    {
        $this->assertSame('9780000000100', Isbn::of(' 9780000000100 ')->value);
        foreach (['', '123', '97800000001000', '978000000010X'] as $bad) {
            $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => Isbn::of($bad));
        }
    }

    public function test_quantity_must_be_at_least_one(): void
    {
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => Quantity::of(0));
        $this->assertSame(1, Quantity::of(1)->value);
    }

    public function test_new_book_starts_with_all_copies_available_and_trimmed_text(): void
    {
        $b = $this->book(4);
        $this->assertSame(4, $b->availableCopies());
        $this->assertSame('Título', $b->title());
        $this->assertNull($b->coverKey());
    }

    public function test_changing_total_moves_available_by_the_same_difference(): void
    {
        $b = $this->book(3);
        $b->withdraw();
        $b->revise('T', 'A', $b->category(), Quantity::of(5));
        $this->assertSame(5, $b->totalCopies());
        $this->assertSame(4, $b->availableCopies());
    }

    public function test_total_cannot_drop_below_lent_copies(): void
    {
        $b = $this->book(3);
        $b->withdraw();
        $b->withdraw();
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => $b->revise('T', 'A', $b->category(), Quantity::of(1)));
        $this->assertSame(1, $b->availableCopies());
    }

    public function test_restock_never_exceeds_total(): void
    {
        $b = $this->book(1);
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => $b->restock());
    }

    public function test_empty_title_is_rejected(): void
    {
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => Book::create(Uuid::v4(), '  ', 'A', Isbn::of('9780000000100'), new Category(Uuid::v4(), 'X'), Quantity::of(1)));
    }

    public function test_date_range_date_only_end_is_inclusive_whole_day(): void
    {
        $r = DateRange::parse('2026-10-01', '2026-10-03');
        $this->assertSame('2026-10-01T00:00:00+00:00', $r->start->format('c'));
        $this->assertSame('2026-10-04T00:00:00+00:00', $r->endExclusive->format('c'));
    }

    public function test_date_range_start_after_end_is_invalid(): void
    {
        $this->assertDomainError(DomainError::INVALID_DATE_RANGE, fn () => DateRange::parse('2026-10-05', '2026-10-01'));
    }

    public function test_date_range_garbage_is_validation_error(): void
    {
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => DateRange::parse('ayer', '2026-10-01'));
    }
}
