<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\Report\Facade;
use SebastianBergmann\CodeCoverage\Serialization\Merger;

require dirname(__DIR__).'/vendor/autoload.php';

$coverage = (new Merger)->merge(array_map(
    fn (int $shard): string => $argv[1].'/shard-'.$shard.'.cov',
    range(1, 4),
));
$summary = Facade::fromSerializedData($coverage)->summary();

printf(
    "Line coverage: %d/%d (%.1f%%)\n",
    $summary->numberOfExecutedLines(),
    $summary->numberOfExecutableLines(),
    $summary->lineCoverageAsPercentage(),
);

exit($summary->numberOfExecutableLines() > 0 && $summary->numberOfExecutedLines() === $summary->numberOfExecutableLines() ? 0 : 1);
