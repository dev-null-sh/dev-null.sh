<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListChangelogRequest;
use App\Queries\ChangelogQuery;
use Illuminate\Http\JsonResponse;

final readonly class ChangelogController
{
    public function index(ListChangelogRequest $request, ChangelogQuery $changelog): JsonResponse
    {
        $days = $changelog->get($request->timeZone(), $request->before());

        return response()->json([
            'pullRequests' => $days['pullRequests'] ?? null,
            'olderBefore' => $days['olderBefore'] ?? null,
            'commitsUrl' => 'https://github.com/'.ChangelogQuery::Repository.'/commits/'.ChangelogQuery::Branch,
        ]);
    }
}
