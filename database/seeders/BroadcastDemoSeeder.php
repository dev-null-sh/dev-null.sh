<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\CreateBroadcast;
use App\Actions\CreateBroadcastInvitation;
use App\Actions\CreateBroadcastPublisher;
use App\Actions\CreateBroadcastSubscription;
use App\Actions\CreateMessage;
use App\Actions\DeleteBroadcastSubscription;
use App\Enums\BroadcastVisibility;
use App\Enums\ConversationKind;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

final class BroadcastDemoSeeder extends Seeder
{
    public function run(
        CreateBroadcast $createBroadcast,
        CreateBroadcastInvitation $invite,
        CreateBroadcastPublisher $publish,
        CreateBroadcastSubscription $subscribe,
        CreateMessage $message,
        DeleteBroadcastSubscription $leave,
    ): void {
        throw_unless(app()->isLocal(), RuntimeException::class, 'The broadcast demo is only available locally.');

        $users = collect(['owner', 'publisher', 'reader', 'new', 'invitee', 'returning'])
            ->mapWithKeys(function (string $role): array {
                $username = "broadcast-{$role}";
                $user = User::query()->firstOrCreate(
                    ['username' => $username],
                    ['github_id' => "local:{$username}", 'name' => "Broadcast {$role}", 'onboarded_at' => now()],
                );
                $user->update(['remotes_enabled' => true]);

                return [$role => $user];
            })->all();

        if (Conversation::query()->where('kind', ConversationKind::Broadcast)->where('name', 'Broadcast demo: open')->exists()) {
            return;
        }

        $owner = $users['owner'];
        $open = $createBroadcast->handle($owner, 'Broadcast demo: open', BroadcastVisibility::Public);
        $message->handle($owner, $open, 'Before anyone joined: only the owner can see this message.', Str::uuid()->toString());

        foreach (['publisher', 'reader', 'returning'] as $role) {
            $subscribe->handle($users[$role], $open);
        }

        $publish->handle($open, $users['publisher']);
        $message->handle($users['publisher'], $open, 'Welcome! Publishers can post; subscribers can read and react.', Str::uuid()->toString());
        $leave->handle($users['returning'], $open);
        $message->handle($owner, $open, 'Published while broadcast-returning was away. Rejoining restores this message, not pre-first-join history.', Str::uuid()->toString());

        $private = $createBroadcast->handle($owner, 'Broadcast demo: invite-only', BroadcastVisibility::Invite);
        $message->handle($owner, $private, 'This predates the invitation being accepted and stays hidden from the invitee.', Str::uuid()->toString());
        $invite->handle($private, $users['invitee']);
    }
}
