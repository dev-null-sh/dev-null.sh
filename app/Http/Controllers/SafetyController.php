<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SafetyController
{
    public function edit(#[CurrentUser] User $user): Response
    {
        return Inertia::render('safety/edit', [
            'muted' => fn (): Collection => $user->muting()->latest('mutes.created_at')->latest('users.id')->get(),
            'blocked' => fn (): Collection => $user->blocking()->latest('blocks.created_at')->latest('users.id')->get(),
        ]);
    }
}
