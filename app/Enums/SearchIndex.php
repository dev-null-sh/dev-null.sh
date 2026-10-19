<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * An Algolia index kept in sync with the database. It only helps find things: who may see what is still decided by
 * the database, after the search.
 */
enum SearchIndex: string
{
    case Users = 'users';

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return match ($this) {
            self::Users => ['searchableAttributes' => ['username', 'name']],
        };
    }
}
