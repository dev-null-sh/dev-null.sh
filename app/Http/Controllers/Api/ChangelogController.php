<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Requests\ListApiChangelogRequest;
use App\Queries\ChangelogQuery;
use Illuminate\Http\JsonResponse;

final readonly class ChangelogController
{
    /**
     * What shipped to production, three days at a time, as the web's changelog lists it.
     */
    public function index(ListApiChangelogRequest $request, ChangelogQuery $changelog): JsonResponse
    {
        $days = $changelog->get($request->timeZone(), $request->before());

        if ($days === null) {
            throw ApiException::changelogUnavailable();
        }

        return response()->json([
            'data' => $days['commits'],
            'older_before' => $days['olderBefore'],
            'commits_url' => 'https://github.com/'.config()->string('services.github.repository').'/commits/'.ChangelogQuery::Branch,
        ]);
    }
}
