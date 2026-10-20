<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteUserCoverImage
{
    /**
     * Removing a cover and rejecting one both erase the file: a rejected cover is never kept.
     */
    public function handle(User $user): void
    {
        $path = DB::transaction(function () use ($user): ?string {
            $path = User::query()->lockForUpdate()->findOrFail($user->id)->cover_image_path;

            User::withoutTimestamps(fn (): int => User::query()->whereKey($user->id)->update([
                'cover_image_path' => null,
                'cover_image_approved_at' => null,
            ]));

            return $path;
        });

        if ($path !== null) {
            Storage::disk()->delete($path);
        }
    }
}
