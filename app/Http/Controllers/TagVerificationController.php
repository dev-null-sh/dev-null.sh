<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RequestTagVerification;
use App\Actions\UpdateTagDomain;
use App\Http\Requests\UpdateTagDomainRequest;
use App\Http\Requests\VerifyTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;

final readonly class TagVerificationController
{
    public function store(VerifyTagRequest $request, Tag $tag, RequestTagVerification $action): RedirectResponse
    {
        $action->handle($tag);

        return back();
    }

    public function update(UpdateTagDomainRequest $request, Tag $tag, UpdateTagDomain $action): RedirectResponse
    {
        $action->handle($tag, $request->string('domain')->toString());

        return back();
    }
}
