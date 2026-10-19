<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BroadcastRole;
use App\Enums\RemoteStatus;
use App\Models\BroadcastInvitation;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateBroadcastSubscription
{
    /**
     * Someone subscribes to a broadcast, on their own when it is public or by accepting an invitation. Their history
     * starts at the latest message, and only the first time: a rejoin keeps the cutoff their first one set, so they
     * catch up on everything pushed while they were away but never reach further back. The invitation, if any, goes
     * with the acceptance.
     */
    public function handle(User $subscriber, Conversation $broadcast): void
    {
        DB::transaction(function () use ($subscriber, $broadcast): void {
            $latest = $broadcast->fresh()->last_message_id ?? 0;

            $participant = ConversationParticipant::query()->firstOrCreate(
                ['conversation_id' => $broadcast->id, 'user_id' => $subscriber->id],
                [
                    'role' => BroadcastRole::Subscriber,
                    'status' => RemoteStatus::Open,
                    'visible_from_id' => $latest,
                    'read_through_id' => $latest,
                    'last_message_at' => now(),
                ],
            );

            if (! $participant->wasRecentlyCreated) {
                ConversationParticipant::query()->whereKey($participant->id)
                    ->where('status', '!=', RemoteStatus::Banned)
                    ->update(['status' => RemoteStatus::Open, 'archived_at' => null, 'last_message_at' => now()]);
            }

            BroadcastInvitation::query()->where('conversation_id', $broadcast->id)->where('user_id', $subscriber->id)->delete();
        });
    }
}
