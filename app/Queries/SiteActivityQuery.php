<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ActivityMetric;
use App\Enums\ActivityScale;
use Carbon\CarbonImmutable;

/**
 * @phpstan-import-type ActivityBucket from ActivityBucketsQuery
 */
final readonly class SiteActivityQuery
{
    public function __construct(private ActivityBucketsQuery $buckets) {}

    /**
     * The rows of the metric, everyone's, over a window ending now, so the first cell only holds the end of the hour
     * or day it starts in.
     *
     * @return array{metric: ActivityMetric, scale: ActivityScale, buckets: list<ActivityBucket>}
     */
    public function get(ActivityMetric $metric, ActivityScale $scale): array
    {
        $now = CarbonImmutable::now();

        return [
            'metric' => $metric,
            'scale' => $scale,
            'buckets' => $this->buckets->get($metric->rows(), $scale, $scale->rollingStart($now), $now),
        ];
    }
}
