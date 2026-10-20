<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteUserCoverImage;
use App\Actions\UpdateUserCoverImage;
use App\Http\Requests\ShowUserCoverImageRequest;
use App\Http\Requests\UpdateUserCoverImageRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class UserCoverImageController
{
    public function show(ShowUserCoverImageRequest $request, User $user): StreamedResponse
    {
        return Storage::disk()->response((string) $user->cover_image_path, $user->username.'-cover.webp', headers: [
            'Content-Type' => 'image/webp',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function update(UpdateUserCoverImageRequest $request, #[CurrentUser] User $user, UpdateUserCoverImage $action): RedirectResponse
    {
        $action->handle($user, $request->coverImage(), $request->float('offset_x'), $request->float('offset_y'));

        return back();
    }

    public function destroy(#[CurrentUser] User $user, DeleteUserCoverImage $action): RedirectResponse
    {
        $action->handle($user);

        return back();
    }
}
