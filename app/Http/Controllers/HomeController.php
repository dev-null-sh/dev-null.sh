<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Log;
use App\Models\User;
use App\Services\Terminal\TerminalRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final readonly class HomeController
{
    public function show(Request $request, TerminalRenderer $renderer): RedirectResponse|Response|HttpResponse
    {
        if (TerminalRenderer::wantsCli($request)) {
            $color = TerminalRenderer::isColorSupported($request);

            if (! Auth::check()) {
                return response($renderer->landing($color))->header('Content-Type', 'text/plain; charset=utf-8');
            }

            return redirect()->intended(route('log.show', Log::Following, absolute: false));
        }

        if (! app()->isLocal() || ! config('app.auto_login')) {
            return Inertia::render('welcome');
        }

        $user = User::query()->firstOrCreate(
            ['github_id' => 'local'],
            ['username' => 'local_dev', 'name' => 'Local Developer'],
        );

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('log.show', Log::Following, absolute: false));
    }
}
