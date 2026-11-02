<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ModerationReason;
use App\Models\Ban;
use App\Models\ScheduledCommit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserBan
{
    public function __construct(private DeleteScheduledCommit $deleteScheduledCommit)
    {
        //
    }

    /**
     * Banning the user again only changes the reason. Their scheduled commits are canceled, so lifting the ban later
     * doesn't send them all out at once.
     */
    public function handle(User $user, ModerationReason $reason): Ban
    {
        $ban = DB::transaction(function () use ($user, $reason): Ban {
            $ban = Ban::query()->updateOrCreate(['github_id' => $user->github_id], ['reason' => $reason]);

            $user->update(['banned_at' => $ban->created_at]);

            return $ban;
        });

        ScheduledCommit::query()->where('user_id', $user->id)->whereNull('submitted_at')->whereNull('canceled_at')
            ->each(fn (ScheduledCommit $scheduled): bool => $this->deleteScheduledCommit->handle($user, $scheduled));

        return $ban;
    }
}
