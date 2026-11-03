<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What changed for a person, broadcast on their private channel so only they hear it. It carries no content: each
 * page reads what it shows again, through the same authorized endpoints it loaded it with.
 */
enum UserUpdate: string
{
    case Notifications = 'notifications';
    case Messages = 'remotes.messages';
    case Conversation = 'remotes.conversation';
    case Inbox = 'remotes.inbox';
    case Calls = 'calls';
    case CallSignals = 'calls.signals';
    case Onboarding = 'onboarding';
    case ScheduledCommits = 'scheduled-commits';
    case Account = 'account';
    case Relationships = 'relationships';
    case Commits = 'commits';
    case Moderation = 'moderation';
    case Live = 'live';
}
