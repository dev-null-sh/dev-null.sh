<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateTagWatch;
use App\Actions\DeleteTagWatch;
use App\Actions\UpsertTags;
use App\Http\Requests\CreateTagWatchRequest;
use App\Http\Requests\DeleteTagWatchRequest;
use App\Models\Tag;
use App\Models\User;
use App\Services\Branches;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class TagWatchController
{
    public function store(
        CreateTagWatchRequest $request,
        string $tag,
        #[CurrentUser] User $user,
        Branches $hashtags,
        UpsertTags $upsertTags,
        CreateTagWatch $action,
    ): RedirectResponse {
        $normalized = mb_strtolower($tag);
        abort_unless($hashtags->isValid($normalized), 404);

        $upsertTags->handle([$normalized]);
        $action->handle($user, Tag::query()->where('name', $normalized)->firstOrFail());

        return back();
    }

    public function destroy(
        DeleteTagWatchRequest $request,
        string $tag,
        #[CurrentUser] User $user,
        Branches $hashtags,
        UpsertTags $upsertTags,
        DeleteTagWatch $action,
    ): RedirectResponse {
        $normalized = mb_strtolower($tag);
        abort_unless($hashtags->isValid($normalized), 404);

        $upsertTags->handle([$normalized]);
        $action->handle($user, Tag::query()->where('name', $normalized)->firstOrFail());

        return back();
    }
}
