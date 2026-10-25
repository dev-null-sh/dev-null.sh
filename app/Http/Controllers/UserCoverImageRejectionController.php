<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteUserCoverImage;
use App\Http\Requests\CreateUserCoverImageRejectionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final readonly class UserCoverImageRejectionController
{
    public function store(CreateUserCoverImageRejectionRequest $request, User $user, DeleteUserCoverImage $action): RedirectResponse
    {
        $action->handle($user, $user->cover_image_path);

        return back();
    }
}
