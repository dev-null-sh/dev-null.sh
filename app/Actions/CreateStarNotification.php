<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use App\Queries\ProfileRecipientsQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateStarNotification
{
    public function __construct(private ProfileRecipientsQuery $recipients)
    {
        //
    }

    /**
     * A star notifies the commit's author, every member of it for an organization's, once while it holds:
     * unstarring removes the notification.
     */
    public function handle(User $stargazer, Commit $commit): void
    {
        /** @var Collection<int, object{user_id: string, organization_id: string|null}> $recipients */
        $recipients = DB::query()->fromSub($this->recipients->builder(), 'recipients')
            ->where('profile_id', $commit->author_id)
            ->where('user_id', '<>', $stargazer->id)
            ->get(['user_id', 'organization_id']);

        Notification::query()->insertOrIgnore($recipients->map(fn (object $recipient): array => [
            'id' => Str::uuid7()->toString(),
            'recipient_id' => $recipient->user_id,
            'actor_id' => $stargazer->id,
            'organization_id' => $recipient->organization_id,
            'type' => NotificationType::Starred->value,
            'commit_id' => $commit->id,
            'subject_id' => $commit->id,
            'key' => 'starred:'.$commit->id.':'.$stargazer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }
}
