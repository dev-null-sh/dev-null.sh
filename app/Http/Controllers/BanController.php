<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteBan;
use App\Http\Requests\DeleteBanRequest;
use App\Models\Ban;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class BanController
{
    /**
     * Lifts a ban from the list, including one whose user deleted their account and so has no profile to lift it from.
     */
    public function destroy(DeleteBanRequest $request, Ban $ban, DeleteBan $action): RedirectResponse
    {
        $action->handle($ban->github_id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $ban->user === null ? 'Lifted the ban. They can sign up again.' : "Unbanned {$ban->user->mention()}.",
        ]);

        return back();
    }
}
