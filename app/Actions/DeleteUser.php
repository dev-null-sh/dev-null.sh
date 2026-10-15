<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteUser
{
    /**
     * The database cascades away the user's commits, every reply under them, and their images, but the image files are erased here.
     */
    public function handle(User $user): void
    {
        $imagePaths = $this->imagePaths($user);

        $user->delete();

        Storage::disk()->delete($imagePaths);
    }

    /**
     * @return array<int, string>
     */
    private function imagePaths(User $user): array
    {
        $commitIds = [];
        $level = $user->commits()->withTrashed()->pluck('id')->all();

        while ($level !== []) {
            $commitIds = [...$commitIds, ...$level];
            $level = Commit::query()->withTrashed()->whereIn('parent_id', $level)->pluck('id')->all();
        }

        return CommitImage::query()
            ->whereIn('commit_id', $commitIds)
            ->get(['path'])
            ->map(fn (CommitImage $image): string => $image->path)
            ->all();
    }
}
