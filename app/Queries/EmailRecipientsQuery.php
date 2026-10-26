<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\EmailActivity;
use App\Enums\EmailSignup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class EmailRecipientsQuery
{
    /**
     * The people an email reaches: everyone with an email address who has not unsubscribed, is not banned and is not a
     * bot, narrowed down by when they were last seen and when they signed up.
     *
     * @return Builder<User>
     */
    public function builder(EmailActivity $activity, EmailSignup $signup): Builder
    {
        $seenSince = $activity->days() === null ? null : today()->subDays($activity->days());

        return User::query()
            ->whereNotNull('email')
            ->whereNull('emails_unsubscribed_at')
            ->whereNull('banned_at')
            ->whereNotIn('github_id', User::BOT_GITHUB_IDS)
            ->when($seenSince !== null && ! $activity->isInactive(), fn (Builder $query) => $query->where('last_seen_at', '>=', $seenSince))
            ->when($seenSince !== null && $activity->isInactive(), fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $seenSince),
            ))
            ->when($signup->days() !== null, fn (Builder $query) => $query->where('created_at', '>=', now()->subDays((int) $signup->days())));
    }
}
