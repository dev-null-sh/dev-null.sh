<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;

/**
 * What a user can choose to have pushed to their phones, each a switch of its own.
 */
enum PushPreference: string
{
    case Follows = 'follows';
    case Replies = 'replies';
    case Mentions = 'mentions';
    case DirectRemotes = 'direct_remotes';
    case OrgRemotes = 'org_remotes';

    /**
     * The switch for a notification, or null when it is one that isn't pushed.
     */
    public static function forNotification(NotificationType $type): ?self
    {
        return match ($type) {
            NotificationType::Followed => self::Follows,
            NotificationType::Replied => self::Replies,
            NotificationType::Mentioned => self::Mentions,
            default => null,
        };
    }

    /**
     * The switch for messages and pings in a conversation of this kind.
     */
    public static function forConversation(ConversationKind $kind): self
    {
        return match ($kind) {
            ConversationKind::Direct => self::DirectRemotes,
            ConversationKind::Org => self::OrgRemotes,
        };
    }

    /**
     * Every switch and whether the user has it on.
     *
     * @return array<value-of<self>, bool>
     */
    public static function allFor(User $user): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $preference): array => [$preference->value => $preference->isOnFor($user)])->all();
    }

    public function column(): string
    {
        return "push_{$this->value}";
    }

    public function isOnFor(User $user): bool
    {
        return $user->getAttribute($this->column()) === true;
    }
}
