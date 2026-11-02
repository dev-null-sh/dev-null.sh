<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The emails the writer can draft from the changelog, by how far back they look.
 */
enum EmailDraft: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function days(): int
    {
        return match ($this) {
            self::Weekly => 7,
            self::Monthly => 30,
        };
    }
}
