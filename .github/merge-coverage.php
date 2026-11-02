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

if ($summary->numberOfExecutableLines() > 0 && $summary->numberOfExecutedLines() === $summary->numberOfExecutableLines()) {
    exit(0);
}

foreach ($coverage['codeCoverage']->lineCoverage() as $file => $lines) {
    $uncovered = array_keys(array_filter($lines, fn (?array $tests): bool => $tests === []));
    $ranges = [];

    foreach ($uncovered as $line) {
        $last = array_key_last($ranges);

        if ($last !== null && $ranges[$last][1] + 1 === $line) {
            $ranges[$last][1] = $line;
        } else {
            $ranges[] = [$line, $line];
        }
    }

    if ($ranges === []) {
        continue;
    }

    printf(
        "%s: %s\n",
        str_replace(dirname(__DIR__).'/', '', $coverage['basePath'].'/'.$file),
        implode(', ', array_map(
            fn (array $range): string => $range[0] === $range[1] ? (string) $range[0] : $range[0].'-'.$range[1],
            $ranges,
        )),
    );
}

exit(1);
