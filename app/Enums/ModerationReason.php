<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Why a moderator put someone in timeout or banned them, as the user is told. The first ones mirror the commit rules
 * shown before a user's first commit.
 */
enum ModerationReason: string
{
    use HasOptions;

    case EngagementBait = 'engagement_bait';
    case LowEffort = 'low_effort';
    case AiGenerated = 'ai_generated';
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Unsafe = 'unsafe';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::EngagementBait => 'Engagement bait',
            self::LowEffort => 'Low-effort posts',
            self::AiGenerated => 'AI-generated posts',
            self::Spam => 'Spam or self-promotion',
            self::Harassment => 'Harassment or hate',
            self::Unsafe => 'Harmful or private information',
            self::Other => 'Breaking the terms',
        };
    }
}
