<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Input;
use App\Adapters\Inbound\Http\Presenter;
use App\Application\Services\CoverService;
use App\Application\Services\CreateBookService;
use App\Application\Services\DeleteBookService;
use App\Application\Services\GetBookService;
use App\Application\Services\SearchBooksService;
use App\Application\Services\UpdateBookService;
use App\Domain\Book;
use App\Domain\DomainError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class BookController
{
    public function index(Request $request, SearchBooksService $search): JsonResponse
    {
        $page = $search->execute(
            Input::queryString($request, 'q'),
            Input::queryUuid($request, 'category_id'),
            Input::queryInt($request, 'page'),
            Input::queryInt($request, 'size'),
        );

        return new JsonResponse(Presenter::page($page, fn (Book $b): array => Presenter::book($b)));
    }

    public function show(string $id, GetBookService $get): JsonResponse
    {
        return new JsonResponse(Presenter::book($get->execute($id)));
    }

    public function store(Request $request, CreateBookService $create): JsonResponse
    {
        $b = Input::body($request);
        $book = $create->execute(
            Input::string($b, 'title'),
            Input::string($b, 'author'),
            Input::string($b, 'isbn'),
            Input::uuid($b, 'category_id'),
            Input::int($b, 'total_copies'),
        );

        return new JsonResponse(Presenter::book($book), 201);
    }

    public function update(string $id, Request $request, UpdateBookService $update): JsonResponse
    {
        $b = Input::body($request); // an `isbn` field, if sent, is ignored (C-05)
        $book = $update->execute($id, Input::string($b, 'title'), Input::string($b, 'author'), Input::uuid($b, 'category_id'), Input::int($b, 'total_copies'));

        return new JsonResponse(Presenter::book($book));
    }

    public function destroy(string $id, DeleteBookService $delete): Response
    {
        $delete->execute($id);

        return new Response('', 204);
    }

    public function cover(string $id, CoverService $covers): Response
    {
        $cover = $covers->read($id);

        return new Response($cover->bytes, 200, ['Content-Type' => $cover->mime, 'Cache-Control' => 'private, max-age=300']);
    }

    public function setCover(string $id, Request $request, CoverService $covers): JsonResponse
    {
        $file = $request->file('file');
        if ($file === null || !$file->isValid()) {
            throw DomainError::invalid('file', 'es obligatorio y debe ser un archivo válido de hasta 2 MB');
        }
        $bytes = file_get_contents($file->getPathname());

        return new JsonResponse(Presenter::book($covers->set($id, $bytes === false ? '' : $bytes)));
    }

    public function removeCover(string $id, CoverService $covers): JsonResponse
    {
        return new JsonResponse(Presenter::book($covers->remove($id)));
    }
}
