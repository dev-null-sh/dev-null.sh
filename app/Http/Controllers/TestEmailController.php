<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateTestEmail;
use App\Http\Requests\CreateTestEmailRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class TestEmailController
{
    public function store(CreateTestEmailRequest $request, #[CurrentUser] User $user, CreateTestEmail $action): RedirectResponse
    {
        $address = $request->string('address')->value();

        $action->handle($user, $address, $request->string('subject')->value(), $request->string('body')->value());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Sent a test email to {$address}.",
        ]);

        return back();
    }
}
