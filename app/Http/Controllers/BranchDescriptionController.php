<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateBranchDescription;
use App\Http\Requests\UpdateBranchDescriptionRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;

final readonly class BranchDescriptionController
{
    public function update(UpdateBranchDescriptionRequest $request, Branch $branch, UpdateBranchDescription $action): RedirectResponse
    {
        $action->handle($branch, $request->string('description')->toString() ?: null);

        return back();
    }
}
