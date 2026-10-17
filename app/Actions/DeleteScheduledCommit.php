<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ScheduledCommit;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteScheduledCommit
{
    /**
     * The images of an item that was already submitted belong to its commit, so they are only erased when this cancels it.
     */
    public function handle(User $user, ScheduledCommit $scheduled): bool
    {
        $imagePaths = array_column($scheduled->images ?? [], 'path');

        $canceled = ScheduledCommit::query()->whereKey($scheduled->id)->where('user_id', $user->id)
            ->whereNull('submitted_at')->whereNull('canceled_at')
            ->update(['canceled_at' => now(), 'body' => null, 'poll' => null, 'images' => null, 'link_preview_url' => null]) === 1;

        if ($canceled) {
            Storage::disk()->delete($imagePaths);
        }

        return $canceled;
    }
}
