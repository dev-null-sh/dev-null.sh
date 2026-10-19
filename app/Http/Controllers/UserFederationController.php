<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserFederation;
use App\Http\Requests\UpdateUserFederationRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class UserFederationController
{
    public function update(UpdateUserFederationRequest $request, #[CurrentUser] User $user, UpdateUserFederation $action): RedirectResponse
    {
        $action->handle($user, $request->boolean('enabled'));

        return back();
    }
}
