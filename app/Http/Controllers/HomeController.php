<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final readonly class HomeController
{
    public function show(Request $request): RedirectResponse|Response
    {
        if (! app()->isLocal() || ! config('app.auto_login')) {
            return Inertia::render('welcome');
        }

        $user = User::query()->firstOrCreate(
            ['github_id' => 'local'],
            ['username' => 'local_dev', 'name' => 'Local Developer'],
        );

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('log.show', $user->log, absolute: false));
    }
}
