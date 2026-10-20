<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A commit is posted to a section when composing it, and is then only ever listed on that section's own page: the logs,
 * profiles, activity, feeds, and the public API all leave it out. A new section is a new case here, plus its icon in
 * `resources/js/lib/sections.ts`.
 */
enum Section: string
{
    case Hiring = 'hiring';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function title(): string
    {
        return match ($this) {
            self::Hiring => 'Hiring',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Hiring => 'Developers hiring developers, in plain text. Sign in with GitHub to reply.',
        };
    }
}
