<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ApproveTagVerification;
use App\Http\Requests\ApproveTagVerificationRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;

final readonly class TagVerificationApprovalController
{
    public function store(ApproveTagVerificationRequest $request, Tag $tag, ApproveTagVerification $action): RedirectResponse
    {
        $action->handle($tag);

        return back();
    }
}
