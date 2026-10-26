<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateSuggestionDismissal;
use App\Http\Requests\CreateSuggestionDismissalRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class SuggestionDismissalController
{
    public function store(CreateSuggestionDismissalRequest $request, #[CurrentUser] User $currentUser, User $user, CreateSuggestionDismissal $action): RedirectResponse
    {
        $action->handle($currentUser, $user);

        return back();
    }
}
