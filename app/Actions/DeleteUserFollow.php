<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeleteUserFollow
{
    public function handle(User $follower, User $followed): void
    {
        DB::transaction(function () use ($follower, $followed): void {
            if ($follower->following()->detach($followed->id) === 0) {
                return;
            }

            ConversationParticipant::query()
                ->where('user_id', $follower->id)
                ->whereIn('conversation_id', Conversation::query()
                    ->select('id')
                    ->where('pair', Conversation::pairOf($follower, $followed)))
                ->update(['status' => RemoteStatus::Dropped]);
        });
    }
}
