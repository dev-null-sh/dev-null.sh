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
    /**
     * The hot topics the web's aside shows the user: each headline with its recap while it's fresh and safe to show.
     *
     * @response array{data: list<array{id: string, name: string, summary: string|null}>}
     */
    public function index(#[CurrentUser] User $user, HotTopicsQuery $topics): JsonResponse
    {
        return response()->json(['data' => $topics->get($user)]);
    }

    /**
     * A headline's page as the web shows it: its name, its recap while it's fresh and safe to show, and the commits
     * about it from the past week that the user may see, newest first.
     */
    public function show(#[CurrentUser] User $user, Headline $headline, HeadlinePageQuery $query): AnonymousResourceCollection
    {
        $page = $query->get($headline, $user);

        return CommitResource::collection($page['commits']())->additional([
            'name' => $page['name'],
            'summary' => $page['summary'](),
        ]);
    }
}
