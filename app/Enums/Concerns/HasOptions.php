<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

use BackedEnum;

/**
 * @phpstan-require-implements BackedEnum
 */
trait HasOptions
{
    abstract public function label(): string;

    /**
     * Every case's label by its value, in the order the cases are declared, for the web to offer as choices.
     *
     * @return array<int|string, string>
     */
    public static function options(): array
    {
        return array_column(array_map(fn (self $case): array => [$case->value, $case->label()], self::cases()), 1, 0);
    }
}
