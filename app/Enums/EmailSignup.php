<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who an email reaches by when they signed up.
 */
enum EmailSignup: string
{
    case Anytime = 'anytime';
    case Week = '7d';
    case Month = '30d';
    case Quarter = '90d';

    public function days(): ?int
    {
        return match ($this) {
            self::Anytime => null,
            self::Week => 7,
            self::Month => 30,
            self::Quarter => 90,
        };
    }
}
