<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Http\Request;

enum WhoToFollowCount: int
{
    case One = 1;
    case Three = 3;
    case Five = 5;

    /**
     * Kept in a cookie, like whether to show the suggestions at all, so each browser keeps its own.
     */
    public static function of(Request $request): self
    {
        return array_find(self::cases(), fn (self $count): bool => (string) $count->value === $request->cookie('who_to_follow_count')) ?? self::Three;
    }
}
