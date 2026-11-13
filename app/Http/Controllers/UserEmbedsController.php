<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserEmbeds;
use App\Http\Requests\UpdateUserEmbedsRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserEmbedsController
{
    public function update(UpdateUserEmbedsRequest $request, #[CurrentUser] User $user, UpdateUserEmbeds $action): RedirectResponse
    {
        $action->handle($user, $request->boolean('enabled'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Embeds preference saved.',
        ]);

        return back();
    }
}
