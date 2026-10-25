<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteUserCoverImage
{
    /**
     * Removing a cover and rejecting one both erase the file: a rejected cover is never kept. A moderator passes the
     * path of the cover they rejected, so a cover the owner uploads in the meantime is left alone.
     */
    public function handle(User $user, ?string $onlyPath = null): void
    {
        $path = DB::transaction(function () use ($user, $onlyPath): ?string {
            $path = User::query()->lockForUpdate()->findOrFail($user->id)->cover_image_path;

            if ($onlyPath !== null && $path !== $onlyPath) {
                return null;
            }

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
