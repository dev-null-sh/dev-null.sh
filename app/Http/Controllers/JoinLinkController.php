<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateJoinLink;
use App\Http\Requests\CreateJoinLinkRequest;
use Illuminate\Http\RedirectResponse;

final readonly class JoinLinkController
{
    /**
     * A guest on a phone, not signed in to GitHub there, gets a link to join from their computer. The answer is the same
     * whether the email went out or not, so the form doesn't tell which addresses already got one.
     */
    public function store(CreateJoinLinkRequest $request, CreateJoinLink $action): RedirectResponse
    {
        if (! $request->filled('homepage')) {
            $action->handle($request->string('email')->toString(), $request->string('path')->toString());
        }

        return back();
    }
}
