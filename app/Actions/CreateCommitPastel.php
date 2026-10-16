<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateCommitPastel
{
    public function handle(User $user, Commit $commit): bool
    {
        return DB::transaction(function () use ($user, $commit): bool {
            $users = User::query()->whereKey([$user->id, $commit->user_id])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $sender = $users->get($user->id);
            assert($sender instanceof User);

            $remaining = $sender->pasteisRemaining();

            if ($remaining === 0) {
                return false;
            }

            $recipientCommit = Commit::query()->lockForUpdate()->findOrFail($commit->id);

            $sender->update([
                'pasteis_given_on' => today()->toDateString(),
                'pasteis_given_today' => User::DAILY_PASTEIS - $remaining + 1,
            ]);

            $recipientCommit->increment('pasteis_count');
            User::query()->whereKey($commit->user_id)->increment('pasteis_received');

            return true;
        }, 3);
    }
}
