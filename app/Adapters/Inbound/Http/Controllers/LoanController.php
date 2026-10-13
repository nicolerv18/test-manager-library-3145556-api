<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Input;
use App\Adapters\Inbound\Http\Presenter;
use App\Application\Ports\Clock;
use App\Application\Services\GetLoanService;
use App\Application\Services\LendBooksService;
use App\Application\Services\ListLoansService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use App\Domain\DomainError;
use App\Domain\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoanController
{
    public function __construct(private readonly Clock $clock)
    {
    }

    public function store(Request $request, LendBooksService $lend): JsonResponse
    {
        $b = Input::body($request);
        $memberId = Input::uuid($b, 'member_id');
        $items = $b['items'] ?? null;
        if (!is_array($items) || !array_is_list($items) || count($items) < 1 || count($items) > Loan::MAX_ITEMS) {
            throw DomainError::invalid('items', 'debe traer entre 1 y '.Loan::MAX_ITEMS.' libros');
        }
        $bookIds = [];
        foreach ($items as $i => $item) {
            $v = is_array($item) ? ($item['book_id'] ?? null) : null;
            if (!is_string($v) || !\App\Domain\Uuid::isValid($v)) {
                throw DomainError::invalid("items[{$i}].book_id", 'debe ser un UUID válido');
            }
            $bookIds[] = strtolower($v);
        }

        $loan = $lend->execute($memberId, $bookIds, (string) $request->attributes->get('auth_user_id'));

        return new JsonResponse(Presenter::loan($loan, $this->clock->now()), 201);
    }

    public function index(Request $request, ListLoansService $list): JsonResponse
    {
        $now = $this->clock->now();
        $page = $list->execute(
            Input::queryString($request, 'from'),
            Input::queryString($request, 'to'),
            Input::queryString($request, 'status'),
            Input::queryUuid($request, 'member_id'),
            Input::queryInt($request, 'page'),
            Input::queryInt($request, 'size'),
        );

        return new JsonResponse(Presenter::page($page, fn (Loan $l): array => Presenter::loan($l, $now)));
    }

    public function overdue(OverdueService $overdue): JsonResponse
    {
        return new JsonResponse(['items' => array_map([Presenter::class, 'overdue'], $overdue->execute())]);
    }

    public function show(string $id, GetLoanService $get): JsonResponse
    {
        return new JsonResponse(Presenter::loan($get->execute($id), $this->clock->now()));
    }

    public function returnItem(string $loanId, string $itemId, ReturnItemService $return): JsonResponse
    {
        return new JsonResponse(Presenter::item($return->execute($loanId, $itemId)));
    }
}
