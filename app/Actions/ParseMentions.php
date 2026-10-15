<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class ParseMentions
{
    /**
     * Usernames: 1–39 alphanumeric characters, hyphens, or periods; not starting or ending with a hyphen or period.
     * The @ must not follow a letter, digit, or underscore (so emails like a@b.com are ignored).
     * A trailing sentence period after a mention is left alone (lookahead allows ".").
     * Keep in sync with resources/js/lib/mentions.ts.
     */
    private const string PATTERN = '/(?<![\p{L}\p{N}_])@([A-Za-z0-9](?:[A-Za-z0-9.-]{0,37}[A-Za-z0-9])?)(?![\p{L}\p{N}-])/u';

    /**
     * @return Collection<int, User>
     */
    public function handle(string $body): Collection
    {
        $usernames = $this->usernames($body);

        if ($usernames === []) {
            return collect();
        }

        return User::query()
            ->where(function (Builder $query) use ($usernames): void {
                foreach ($usernames as $username) {
                    $query->orWhereRaw('LOWER(username) = ?', [$username]);
                }
            })
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @return list<string>
     */
    public function usernames(string $body): array
    {
        preg_match_all(self::PATTERN, $body, $matches);

        /** @var list<string> $handles */
        $handles = $matches[1] ?? [];

        return array_values(array_unique(array_map(mb_strtolower(...), $handles)));
    }
}
