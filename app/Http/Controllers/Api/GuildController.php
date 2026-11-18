<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiGuildsRequest;
use App\Http\Resources\GuildResource;
use App\Models\User;
use App\Queries\GuildsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class GuildController
{
    public function index(ListApiGuildsRequest $request, #[CurrentUser] User $user, GuildsQuery $query): AnonymousResourceCollection
    {
        $guilds = $query->paginate($request->term(), $request->integer('page', 1))->withQueryString();

        $guilds->getCollection()->loadExists([
            'members as is_joined' => function (Builder|Relation $query) use ($user): void {
                $query->whereKey($user->id);
            },
            'muters as is_muted' => function (Builder|Relation $query) use ($user): void {
                $query->whereKey($user->id);
            },
        ]);

        return GuildResource::collection($guilds);
    }
}
