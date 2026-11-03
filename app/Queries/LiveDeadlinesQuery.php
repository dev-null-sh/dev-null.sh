<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class LiveDeadlinesQuery
{
    /**
     * The most streams a page is told the end of; anyone beyond them is asked about when shown.
     */
    public const int LIMIT = 100;

    /**
     * When the streams on now run out, the soonest first, for the people the viewer can be shown: pages show people's
     * streams without their end, and stop showing each one then.
     *
     * @return array<string, string>
     */
    public function get(?User $viewer): array
    {
        /** @var array<string, string> $deadlines */
        $deadlines = User::query()
            ->where('live_ends_at', '>', now())
            ->whereNotNull('live_platform')
            ->whereNotNull('live_url')
            ->when($viewer, fn (Builder $query, User $viewer): Builder => $query->whereNotIn('users.id', $viewer->idsAcrossBlocks()))
            ->orderBy('live_ends_at')
            ->limit(self::LIMIT)
            ->get(['id', 'live_ends_at'])
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->live_ends_at?->toJSON()])
            ->all();

        return $deadlines;
    }
}
