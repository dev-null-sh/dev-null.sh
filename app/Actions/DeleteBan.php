<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Ban;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeleteBan
{
    /**
     * Lifts the ban on the GitHub account, and on its user if the account wasn't deleted.
     */
    public function handle(string $githubId): void
    {
        DB::transaction(function () use ($githubId): void {
            Ban::query()->where('github_id', $githubId)->delete();

            User::query()->where('github_id', $githubId)->update(['banned_at' => null]);
        });
    }
}
