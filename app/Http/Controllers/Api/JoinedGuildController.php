<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiJoinedGuildsRequest;
use App\Models\User;
use App\Queries\JoinedGuildsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class JoinedGuildController
{
    /**
     * The guilds the user joined and did not mute, as the web's left navigation lists them: the most missed commits
     * first. read_from is when the count starts, the last visit to the board or the join, so a board can mark where
     * the commits that are new since then end.
     */
    public function index(ListApiJoinedGuildsRequest $request, #[CurrentUser] User $user, JoinedGuildsQuery $guilds): JsonResponse
    {
        return response()->json(['data' => array_map(fn (array $guild): array => [
            ...$guild,
            'html_url' => route('guild.show', $guild['handle']),
        ], $guilds->get($user, apiIcons: true))]);
    }
}
