<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Input;
use App\Adapters\Inbound\Http\Presenter;
use App\Application\Services\CreateMemberService;
use App\Application\Services\GetMemberService;
use App\Application\Services\SearchMembersService;
use App\Domain\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MemberController
{
    public function index(Request $request, SearchMembersService $search): JsonResponse
    {
        $page = $search->execute(Input::queryString($request, 'q'), Input::queryInt($request, 'page'), Input::queryInt($request, 'size'));

        return new JsonResponse(Presenter::page($page, fn (Member $m): array => Presenter::member($m)));
    }

    public function show(string $id, GetMemberService $get): JsonResponse
    {
        $r = $get->execute($id);

        return new JsonResponse(Presenter::member($r['member']) + ['active_loans' => $r['active_loans']]);
    }

    public function store(Request $request, CreateMemberService $create): JsonResponse
    {
        $b = Input::body($request);
        $member = $create->execute(Input::string($b, 'full_name'), Input::string($b, 'document_number'));

        return new JsonResponse(Presenter::member($member), 201);
    }
}
