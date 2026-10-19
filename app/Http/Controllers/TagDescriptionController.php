<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateTagDescription;
use App\Http\Requests\UpdateTagDescriptionRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;

final readonly class TagDescriptionController
{
    public function update(UpdateTagDescriptionRequest $request, Tag $tag, UpdateTagDescription $action): RedirectResponse
    {
        $action->handle($tag, $request->string('description')->toString() ?: null);

        return back();
    }
}
