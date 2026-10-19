<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ClaimTag;
use App\Http\Requests\ClaimTagRequest;
use App\Models\User;
use App\Services\Branches;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class TagClaimController
{
    public function store(ClaimTagRequest $request, string $tag, #[CurrentUser] User $user, Branches $hashtags, ClaimTag $action): RedirectResponse
    {
        $normalized = mb_strtolower($tag);
        abort_unless($hashtags->isValid($normalized), 404);

        $action->handle($user, $normalized, $request->string('domain')->toString());

        return to_route('tag.settings', $normalized);
    }
}
