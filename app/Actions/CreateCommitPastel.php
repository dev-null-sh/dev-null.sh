<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\CommitUpdate;
use App\Enums\UserUpdate;
use App\Events\CommitsUpdated;
use App\Events\UsersUpdated;
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

            // The count is everyone's to see, and the giver's other devices learn what they have left to give.
            if ($recipientCommit->status === CommitStatus::Approved) {
                CommitsUpdated::dispatch([$recipientCommit], CommitUpdate::Engagement);
            }
            UsersUpdated::dispatch([$sender->id], UserUpdate::Account);

            return true;
        }, 3);
    }
}
