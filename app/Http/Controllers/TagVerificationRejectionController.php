<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RejectTagVerification;
use App\Http\Requests\RejectTagVerificationRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;

final readonly class TagVerificationRejectionController
{
    public function store(RejectTagVerificationRequest $request, Tag $tag, RejectTagVerification $action): RedirectResponse
    {
        $action->handle($tag);

        return back();
    }
}
