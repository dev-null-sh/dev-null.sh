<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateLocalSessionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final readonly class LocalSessionController
{
    public function store(CreateLocalSessionRequest $request, User $user): RedirectResponse
    {
        Auth::login($user);

        $request->session()->regenerate();

        return to_route('log.show', $user->log);
    }
}
