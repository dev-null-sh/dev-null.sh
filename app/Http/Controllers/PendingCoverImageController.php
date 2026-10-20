<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListPendingCoverImagesRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PendingCoverImageController
{
    /**
     * Cover files are named with UUIDv7s, so ordering by path lists the oldest uploads first.
     */
    public function index(ListPendingCoverImagesRequest $request, #[CurrentUser] User $moderator): Response
    {
        return Inertia::render('moderation/covers', [
            'covers' => Inertia::scroll(fn (): CursorPaginator => User::query()
                ->whereNotNull('cover_image_path')
                ->whereNull('cover_image_approved_at')
                ->orderBy('cover_image_path')
                ->cursorPaginate(20)
                ->through(fn (User $user): array => [
                    'user' => $user,
                    'url' => $user->coverImageUrl($moderator),
                    'version' => $user->coverImageVersion(),
                ])),
        ]);
    }
}
