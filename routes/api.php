<?php

declare(strict_types=1);

use App\Adapters\Inbound\Http\Controllers\AuthController;
use App\Adapters\Inbound\Http\Controllers\BookController;
use App\Adapters\Inbound\Http\Controllers\CategoryController;
use App\Adapters\Inbound\Http\Controllers\LoanController;
use App\Adapters\Inbound\Http\Controllers\MemberController;
use App\Adapters\Inbound\Http\Controllers\ReportController;
use App\Adapters\Inbound\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Prefix /api/v1 is applied in bootstrap/app.php. Literal routes are declared BEFORE parameterized ones (C-08).
Route::get('/health', [AuthController::class, 'health']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('jwt')->group(function (): void {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show'])->whereUuid('id');
    Route::get('/books/{id}/cover', [BookController::class, 'cover'])->whereUuid('id');

    Route::middleware('role:admin')->group(function (): void {
        Route::post('/users', [UserController::class, 'store']);
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update'])->whereUuid('id');
        Route::delete('/books/{id}', [BookController::class, 'destroy'])->whereUuid('id');
        Route::put('/books/{id}/cover', [BookController::class, 'setCover'])->whereUuid('id');
        Route::delete('/books/{id}/cover', [BookController::class, 'removeCover'])->whereUuid('id');
        Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed']);
    });

    Route::middleware('role:admin,librarian')->group(function (): void {
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/{id}', [MemberController::class, 'show'])->whereUuid('id');
        Route::post('/members', [MemberController::class, 'store']);

        Route::post('/loans', [LoanController::class, 'store']);
        Route::get('/loans', [LoanController::class, 'index']);
        Route::get('/loans/overdue', [LoanController::class, 'overdue']);
        Route::get('/loans/{id}', [LoanController::class, 'show'])->whereUuid('id');
        Route::post('/loans/{loanId}/items/{itemId}/return', [LoanController::class, 'returnItem'])->whereUuid(['loanId', 'itemId']);
    });
});
