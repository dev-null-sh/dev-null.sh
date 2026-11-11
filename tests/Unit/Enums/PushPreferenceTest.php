<?php

declare(strict_types=1);

use App\Enums\ConversationKind;
use App\Enums\NotificationType;
use App\Enums\PushPreference;
use App\Models\User;

it('finds the switch for the notifications that can be pushed', function (): void {
    expect(PushPreference::forNotification(NotificationType::Followed))->toBe(PushPreference::Follows)
        ->and(PushPreference::forNotification(NotificationType::Replied))->toBe(PushPreference::Replies)
        ->and(PushPreference::forNotification(NotificationType::Mentioned))->toBe(PushPreference::Mentions)
        ->and(PushPreference::forNotification(NotificationType::Starred))->toBeNull();
});

it('finds the switch for messages in each kind of remote', function (): void {
    expect(PushPreference::forConversation(ConversationKind::Direct))->toBe(PushPreference::DirectRemotes)
        ->and(PushPreference::forConversation(ConversationKind::Org))->toBe(PushPreference::OrgRemotes);
});

it('says whether the user has a switch on', function (): void {
    $user = User::factory()->make(['push_mentions' => false]);

    expect(PushPreference::Mentions->isOnFor($user))->toBeFalse()
        ->and(PushPreference::Replies->isOnFor($user))->toBeTrue()
        ->and(PushPreference::allFor($user))->toBe([
            'follows' => true,
            'replies' => true,
            'mentions' => false,
            'direct_remotes' => true,
            'org_remotes' => true,
        ]);
});
