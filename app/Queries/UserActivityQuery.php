<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ActivityScale;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * @phpstan-import-type ActivityBucket from ActivityBucketsQuery
 */
final readonly class UserActivityQuery
{
    public function __construct(private ActivityBucketsQuery $buckets) {}

    /**
     * Without a scale, it is the smallest of the given ones that fits all of the visible activity, or else the year.
     *
     * @param  list<ActivityScale>  $scales
     * @return array{scale: ActivityScale, buckets: list<ActivityBucket>}
     */
    public function get(User $user, ?User $viewer, ?ActivityScale $scale = null, array $scales = ActivityScale::FITTING): array
    {
        $now = CarbonImmutable::now();

        $scale ??= $this->scale($user, $viewer, $now, $scales);

        return [
            'scale' => $scale,
            'buckets' => $this->buckets->get($user->commits()->visibleTo($viewer)->toBase(), $scale, $scale->start($now), $now),
        ];
    }

    /**
     * @param  list<ActivityScale>  $scales
     */
    private function scale(User $user, ?User $viewer, CarbonImmutable $now, array $scales): ActivityScale
    {
        $firstCommitAt = $user->commits()->visibleTo($viewer)->min('created_at');

        return ActivityScale::since(
            is_string($firstCommitAt) ? CarbonImmutable::parse($firstCommitAt) : $user->created_at,
            $now,
            $scales,
        );
    }
}
