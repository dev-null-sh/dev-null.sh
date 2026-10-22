<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BroadcastRole;
use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeleteBroadcastMember
{
    public function handle(Conversation $broadcast, User $member): void
    {
        DB::transaction(function () use ($broadcast, $member): void {
            $broadcast->participants()->where('user_id', $member->id)->where('status', RemoteStatus::Open)->update([
                'status' => RemoteStatus::Dropped,
                'role' => BroadcastRole::Subscriber,
                'archived_at' => null,
            ]);
            $broadcast->invitations()->where('user_id', $member->id)->delete();
        });
    }
}
