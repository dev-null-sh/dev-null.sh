<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiGuildsRequest;
use App\Http\Resources\GuildResource;
use App\Models\User;
use App\Queries\GuildsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class GuildController
{
    public function index(ListApiGuildsRequest $request, #[CurrentUser] User $user, GuildsQuery $query): AnonymousResourceCollection
    {
        return GuildResource::collection($query->paginate($request->term(), $request->integer('page', 1), $user)->withQueryString());
    }
}
