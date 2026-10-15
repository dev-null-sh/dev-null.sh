<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\User;
use App\Queries\TagLogQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TagController
{
    public function show(#[CurrentUser] User $user, Tag $tag, TagLogQuery $query): Response
    {
        return Inertia::render('tag/show', [
            'tag' => $tag,
            'commitsCount' => fn (): int => $query->builder($user, $tag)->count(),
            'followersCount' => fn (): int => $tag->followers()->newPivotQuery()->count(),
            'isFollowing' => fn (): bool => $user->isFollowingTag($tag),
            'commits' => Inertia::scroll(fn () => $query->builder($user, $tag)->cursorPaginate(20)),
        ]);
    }
}
