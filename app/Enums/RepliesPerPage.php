<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Http\Request;

enum RepliesPerPage: int
{
    case One = 1;
    case Three = 3;
    case Five = 5;
    case Ten = 10;
    case Fifteen = 15;
    case Twenty = 20;
    case TwentyFive = 25;

    /**
     * Kept in a cookie, like the theme, so guests can pick it too. API clients page on from the newest reply alone.
     */
    public static function of(Request $request): self
    {
        if ($request->is('api/*')) {
            return self::One;
        }

        return array_find(self::cases(), fn (self $count): bool => (string) $count->value === $request->cookie('replies_per_page')) ?? self::Three;
    }
}
