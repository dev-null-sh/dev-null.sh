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

final readonly class CreateQuoteNotifications
{
    public function __construct(private ProfileRecipientsQuery $recipients)
    {
        //
    }

    /**
     * Notifies the author of each quoted commit, every member of it for an organization's, but only once the quote
     * is approved, so nobody hears about a quote they cannot see yet. It comes from its author: an organization's
     * quote comes from the organization, whoever posted it. Nobody hears about their own quote, nor the members of an
     * organization about its quote to itself, from authors they mute or from across a block either. Safe to call
     * more than once.
     */
    public function handle(Commit ...$commits): void
    {
        $quoteIds = array_map(
            fn (Commit $commit): string => $commit->id,
            array_filter($commits, fn (Commit $commit): bool => $commit->quoted_commit_id !== null),
        );

        if ($quoteIds === []) {
            return;
        }

        $silenced = new SilencedActorQuery('recipients.user_id', 'commits.author_id');

        /** @var Collection<int, object{id: string, author_id: string, recipient_id: string, organization_id: string|null}> $quotes */
        $quotes = Commit::query()
            ->join('commits as quoted', 'quoted.id', '=', 'commits.quoted_commit_id')
            ->joinSub($this->recipients->builder(), 'recipients', 'recipients.profile_id', '=', 'quoted.author_id')
            ->whereKey($quoteIds)
            ->whereNull('quoted.deleted_at')
            ->where('commits.status', CommitStatus::Approved)
            ->whereRaw('recipients.user_id is distinct from commits.user_id')
            ->whereColumn('recipients.profile_id', '<>', 'commits.author_id')
            ->whereNotExists($silenced->mutes(...))
            ->whereNotExists($silenced->blocking(...))
            ->whereNotExists($silenced->blockers(...))
            ->toBase()
            ->get(['commits.id', 'commits.author_id', 'recipients.user_id as recipient_id', 'recipients.organization_id']);

        Notification::query()->insertOrIgnore($quotes->map(fn (object $quote): array => [
            'id' => Str::uuid7()->toString(),
            'recipient_id' => $quote->recipient_id,
            'actor_id' => $quote->author_id,
            'organization_id' => $quote->organization_id,
            'type' => NotificationType::Quoted->value,
            'commit_id' => $quote->id,
            'key' => 'quoted:'.$quote->id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }
}
