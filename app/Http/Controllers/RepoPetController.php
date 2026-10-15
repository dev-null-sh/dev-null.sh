<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateRepoPet;
use App\Http\Requests\DeleteRepoPetRequest;
use App\Http\Requests\UpdateRepoPetRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class RepoPetController
{
    public function update(UpdateRepoPetRequest $request, #[CurrentUser] User $user, UpdateRepoPet $action): RedirectResponse
    {
        $action->handle($user, $request->configuration());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Repo pet adopted.')]);

        return back();
    }

    public function destroy(DeleteRepoPetRequest $request, #[CurrentUser] User $user, UpdateRepoPet $action): RedirectResponse
    {
        $action->handle($user, null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Repo pet removed.')]);

        return back();
    }
}
