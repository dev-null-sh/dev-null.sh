<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\CommitResource;
use App\Models\Headline;
use App\Models\User;
use App\Queries\HeadlinePageQuery;
use App\Queries\HotTopicsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class HeadlineController
{
    public function index(#[CurrentUser] User $user, HotTopicsQuery $topics): JsonResponse
    {
        return response()->json(['data' => $topics->get($user)]);
    }

    public function show(#[CurrentUser] User $user, Headline $headline, HeadlinePageQuery $query): AnonymousResourceCollection
    {
        $page = $query->get($headline, $user);

        return CommitResource::collection($page['commits']())->additional([
            'name' => $page['name'],
            'summary' => $page['summary'](),
        ]);
    }
}
