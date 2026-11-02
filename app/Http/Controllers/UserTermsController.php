<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserTermsAcceptance;
use App\Http\Requests\UpdateUserTermsRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class UserTermsController
{
    public function update(UpdateUserTermsRequest $request, #[CurrentUser] User $user, UpdateUserTermsAcceptance $action): RedirectResponse
    {
        $action->handle($user);

        return back();
    }
}
