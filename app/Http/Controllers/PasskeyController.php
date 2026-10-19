<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreatePasskeyRequest;
use App\Http\Requests\DeletePasskeyRequest;
use App\Models\Passkey;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class PasskeyController
{
    public function store(CreatePasskeyRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $user->passkeys()->create([
            'name' => $request->string('name')->value(),
            'credential_id' => $request->string('credential.credential_id')->value(),
            'credential' => $request->credential(),
        ]);

        return back();
    }

    public function destroy(DeletePasskeyRequest $request, Passkey $passkey): RedirectResponse
    {
        $passkey->delete();

        return back();
    }
}
