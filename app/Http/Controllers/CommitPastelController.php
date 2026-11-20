<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommitPastel;
use App\Enums\PastelVariant;
use App\Http\Requests\CreateCommitPastelRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitPastelController
{
    public function store(CreateCommitPastelRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitPastel $action): JsonResponse
    {
        $awarded = $action->handle($user, $commit, $request->variant());

        return response()->json([
            'pasteis_count' => $commit->refresh()->pasteis_count,
            'pasteis_diamond_count' => $commit->pasteis_diamond_count,
            'pasteis_frosted_count' => $commit->pasteis_frosted_count,
            'remaining' => $user->refresh()->pasteisRemaining(),
            'diamond_remaining' => $user->diamondPasteisRemaining(),
            'diamond_resets_at' => now()->startOfWeek(1)->addWeek()->toIso8601ZuluString(),
            'day' => today()->toDateString(),
            'recipient' => [
                'id' => $commit->user_id,
                'pasteis_received' => $commit->user?->pasteis_received,
                'pasteis_diamond_received' => $commit->user?->pasteis_diamond_received,
                'pasteis_frosted_received' => $commit->user?->pasteis_frosted_received,
            ],
            ...$awarded ? [] : ['errors' => ['pastel' => [$request->variant() === PastelVariant::Diamond ? 'You already gave your Diamond this week. Your next one arrives on Monday at 00:00 UTC.' : 'you ran out of pastéis. wait until tomorrow.']]],
        ], $awarded ? 200 : 422);
    }
}
