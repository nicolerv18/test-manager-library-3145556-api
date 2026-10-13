<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Services\BootstrapAdminService;
use App\Application\Services\CoverService;
use App\Application\Services\CreateBookService;
use App\Application\Services\CreateLibrarianService;
use App\Application\Services\CreateMemberService;
use App\Application\Services\LoginService;
use App\Domain\DomainError;
use App\Domain\Uuid;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakePasswordHasher;
use Tests\Fakes\FakeTokenIssuer;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryBookRepository;
use Tests\Fakes\InMemoryCategoryRepository;
use Tests\Fakes\InMemoryCoverStorage;
use Tests\Fakes\InMemoryMemberRepository;
use Tests\Fakes\InMemoryStore;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Support\AssertsDomainErrors;

final class AuthAndCatalogTest extends TestCase
{
    use AssertsDomainErrors;

    private InMemoryStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
    }

    public function test_bootstrap_admin_is_idempotent_and_never_overwrites_password(): void
    {
        $users = new InMemoryUserRepository($this->store);
        $svc = new BootstrapAdminService($users, new FakePasswordHasher());
        $this->assertTrue($svc->execute(' Admin ', 'secreto1'));
        $this->assertFalse($svc->execute('admin', 'otra-clave'));
        $this->assertSame((new FakePasswordHasher())->hash('secreto1'), $users->findByUsername('admin')->passwordHash);
    }

    public function test_login_ok_and_same_error_for_wrong_user_or_password(): void
    {
        $users = new InMemoryUserRepository($this->store);
        $hasher = new FakePasswordHasher();
        (new BootstrapAdminService($users, $hasher))->execute('admin', 'secreto1');
        $login = new LoginService($users, $hasher, new FakeTokenIssuer(), new FixedClock());
        $this->assertSame(28800, $login->execute('ADMIN', 'secreto1')->expiresIn);
        $a = $this->assertDomainError(DomainError::INVALID_CREDENTIALS, fn () => $login->execute('admin', 'mala'));
        $b = $this->assertDomainError(DomainError::INVALID_CREDENTIALS, fn () => $login->execute('nadie', 'mala'));
        $this->assertSame($a->getMessage(), $b->getMessage());
    }

    public function test_create_librarian_rejects_duplicates_and_bad_role(): void
    {
        $users = new InMemoryUserRepository($this->store);
        $svc = new CreateLibrarianService($users, new FakePasswordHasher());
        $svc->execute('Maria', 'clave-segura', 'librarian');
        $this->assertDomainError(DomainError::USERNAME_TAKEN, fn () => $svc->execute('maria', 'otra-clave1', 'librarian'));
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => $svc->execute('x', 'clave-segura', 'root'));
    }

    public function test_isbn_unique_among_active_books_and_unknown_category(): void
    {
        $svc = new CreateBookService(new InMemoryBookRepository($this->store), new InMemoryCategoryRepository($this->store));
        $cat = $this->store->categories[0]->id;
        $svc->execute('A', 'B', '9780000000999', $cat, 1);
        $this->assertDomainError(DomainError::ISBN_TAKEN, fn () => $svc->execute('C', 'D', '9780000000999', $cat, 1));
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => $svc->execute('C', 'D', '9780000000998', Uuid::v4(), 1));
    }

    public function test_member_document_is_unique(): void
    {
        $svc = new CreateMemberService(new InMemoryMemberRepository($this->store));
        $svc->execute('Ana', '12345');
        $this->assertDomainError(DomainError::DOCUMENT_TAKEN, fn () => $svc->execute('Luis', '12345'));
    }

    public function test_cover_accepts_png_jpeg_rejects_other_types_and_big_files(): void
    {
        $books = new InMemoryBookRepository($this->store);
        $storage = new InMemoryCoverStorage();
        $book = (new CreateBookService($books, new InMemoryCategoryRepository($this->store)))->execute('A', 'B', '9780000000777', $this->store->categories[0]->id, 1);
        $svc = new CoverService($books, $storage);
        $png = "\x89PNG\r\n\x1a\n".str_repeat('x', 20);
        $this->assertNotNull($svc->set($book->id, $png)->coverKey());
        $this->assertSame('image/png', $svc->read($book->id)->mime);
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => $svc->set($book->id, 'GIF89a....'));
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => $svc->set($book->id, "\xFF\xD8\xFF".str_repeat('x', 2 * 1024 * 1024)));
        $this->assertNull($svc->remove($book->id)->coverKey());
        $this->assertDomainError(DomainError::NOT_FOUND, fn () => $svc->read($book->id));
    }
}
