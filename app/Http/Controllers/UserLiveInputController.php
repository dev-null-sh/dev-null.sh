<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserLiveInput;
use App\Actions\UpdateUserLive;
use App\Enums\LivePlatform;
use App\Http\Requests\CreateUserLiveInputRequest;
use App\Http\Requests\UpdateUserLiveInputRequest;
use App\Models\User;
use App\Services\CloudflareStream;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserLiveInputController
{
    public function __construct(private CloudflareStream $stream)
    {
        //
    }

    /**
     * The stream key is only ever flashed to its owner, never kept in a page they could share.
     */
    public function store(CreateUserLiveInputRequest $request, #[CurrentUser] User $user, CreateUserLiveInput $action): RedirectResponse
    {
        Inertia::flash('liveInput', $action->handle($user)->toArray());

        return back();
    }

    public function update(UpdateUserLiveInputRequest $request, #[CurrentUser] User $user, UpdateUserLive $action): RedirectResponse
    {
        $action->handle($user, LivePlatform::Cloudflare, $this->stream->playerUrl((string) $user->live_input_id), $request->integer('hours'));

        return back();
    }
}
