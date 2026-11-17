<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListLiveStreamRequest;
use App\Http\Resources\LiveStreamResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

final readonly class LiveStreamController
{
    /**
     * The streams the people a page shows are on right now, as their profiles show them, so a page that may have
     * missed someone going live or ending a stream shows it as it is. Anyone not live, unknown or across a block with
     * the viewer answers the same: not live.
     */
    public function index(ListLiveStreamRequest $request, #[CurrentUser] ?User $viewer): JsonResponse
    {
        $ids = $request->ids();
        $streams = User::query()->whereKey($ids)
            ->when($viewer, fn (Builder $query, User $viewer): Builder => $query->whereNotIn('users.id', $viewer->idsAcrossBlocks()))
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => LiveStreamResource::of($user)]);

        return response()->json(['streams' => collect($ids)->mapWithKeys(fn (string $id): array => [$id => $streams->get($id)])])
            ->header('Cache-Control', 'no-store, private');
    }
}
