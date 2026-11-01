<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Queries\ProfileRecipientsQuery;
use App\Queries\SilencedActorQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class CreateReplyNotifications
{
    public function __construct(private ProfileRecipientsQuery $recipients)
    {
        //
    }

    /**
     * Notifies the author of each reply's parent, every member of it for an organization's, but only once the reply
     * is approved, so nobody hears about a reply they cannot see yet. Nobody hears about their own reply, from people
     * they mute or from across a block either. Safe to call more than once.
     */
    public function handle(Commit ...$commits): void
    {
        $replyIds = array_map(
            fn (Commit $commit): string => $commit->id,
            array_filter($commits, fn (Commit $commit): bool => $commit->parent_id !== null),
        );

        if ($replyIds === []) {
            return;
        }

        $silenced = new SilencedActorQuery('recipients.user_id', 'commits.user_id');

        /** @var Collection<int, object{id: string, user_id: string, parent_id: string, recipient_id: string, organization_id: string|null}> $replies */
        $replies = Commit::query()
            ->join('commits as parents', 'parents.id', '=', 'commits.parent_id')
            ->joinSub($this->recipients->builder(), 'recipients', 'recipients.profile_id', '=', 'parents.author_id')
            ->whereKey($replyIds)
            ->whereNull('parents.deleted_at')
            ->where('commits.status', CommitStatus::Approved)
            ->whereColumn('recipients.user_id', '<>', 'commits.user_id')
            ->whereNotExists($silenced->mutes(...))
            ->whereNotExists($silenced->blocking(...))
            ->whereNotExists($silenced->blockers(...))
            ->toBase()
            ->get(['commits.id', 'commits.user_id', 'commits.parent_id', 'recipients.user_id as recipient_id', 'recipients.organization_id']);

        Notification::query()->insertOrIgnore($replies->map(fn (object $reply): array => [
            'id' => Str::uuid7()->toString(),
            'recipient_id' => $reply->recipient_id,
            'actor_id' => $reply->user_id,
            'organization_id' => $reply->organization_id,
            'type' => NotificationType::Replied->value,
            'commit_id' => $reply->id,
            'subject_id' => $reply->parent_id,
            'key' => 'replied:'.$reply->id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }
}
