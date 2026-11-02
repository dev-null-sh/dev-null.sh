<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Serialization\Serializer;

beforeEach(function (): void {
    Process::preventStrayProcesses(false);
});

afterEach(function (): void {
    File::deleteDirectory($this->coverageDirectory);
});

/**
 * @param  list<list<int>>  $coveredLines
 */
function coverageShards(array $coveredLines, bool $empty = false): string
{
    $directory = storage_path('framework/testing/coverage-'.Str::random(12));
    test()->coverageDirectory = $directory;
    File::ensureDirectoryExists($directory);
    $source = $directory.'/example.php';
    File::put($source, $empty ? '<?php' : "<?php\nfunction coverageExample(bool \$first): int\n{\n    if (\$first) {\n        \$value = 1;\n        \$value++;\n        return \$value;\n    }\n\n    return 2;\n}\n");

    $coveredSource = $directory.'/covered.php';
    File::put($coveredSource, $empty ? '<?php' : "<?php\nreturn 1;\n");

    foreach ($coveredLines as $index => $lines) {
        $driver = Mockery::mock(Driver::class);
        $driver->shouldReceive('name')->andReturn('fixture');
        $driver->shouldReceive('version')->andReturn('1');
        $driver->shouldReceive('collectsHitCounts')->andReturn(false);
        $filter = new Filter;
        $filter->includeFile($source);
        $filter->includeFile($coveredSource);
        $coverage = new CodeCoverage($driver, $filter);
        $data = new ProcessedCodeCoverageData(false);
        $test = 'shard-'.($index + 1);
        $data->setTestIds([$test]);
        $data->setLineCoverage($empty ? [] : [$source => [
            5 => in_array(5, $lines, true) ? [0 => 1] : [],
            6 => in_array(6, $lines, true) ? [0 => 1] : [],
            7 => in_array(7, $lines, true) ? [0 => 1] : [],
            9 => null,
            10 => in_array(10, $lines, true) ? [0 => 1] : [],
        ], $coveredSource => [2 => [0 => 1]]]);
        $coverage->setData($data);
        $coverage->setTests([$test => ['size' => 'small', 'status' => 'success', 'time' => 0.0]]);
        (new Serializer)->serialize($directory.'/'.$test.'.cov', $coverage);
    }

    return $directory;
}

it('passes when partial shard reports together cover every executable line', function (): void {
    $directory = coverageShards([[5, 6, 7], [10], [], []]);

    $result = Process::run([PHP_BINARY, base_path('.github/merge-coverage.php'), $directory]);

    expect($result->exitCode())->toBe(0)
        ->and($result->output())->toBe("Line coverage: 5/5 (100.0%)\n");
});

it('fails and lists the uncovered lines after merging', function (array $coveredLines, string $summary, string $missingLines): void {
    $directory = coverageShards([$coveredLines, $coveredLines, [], []]);
    $file = str_replace(base_path().'/', '', $directory).'/example.php';

    $result = Process::run([PHP_BINARY, base_path('.github/merge-coverage.php'), $directory]);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toBe($summary."\n".$file.': '.$missingLines."\n");
})->with([
    'single line' => [[5, 6, 7], 'Line coverage: 4/5 (80.0%)', '10'],
    'consecutive lines' => [[10], 'Line coverage: 2/5 (40.0%)', '5-7'],
    'separate lines' => [[6], 'Line coverage: 2/5 (40.0%)', '5, 7, 10'],
    'fully uncovered file' => [[], 'Line coverage: 1/5 (20.0%)', '5-7, 10'],
]);

it('fails when any shard report is missing', function (int $shard): void {
    $directory = coverageShards([[5, 6, 7, 10], [], [], []]);
    File::delete($directory.'/shard-'.$shard.'.cov');

    $result = Process::run([PHP_BINARY, base_path('.github/merge-coverage.php'), $directory]);

    expect($result->exitCode())->not->toBe(0)
        ->and(($result->output().$result->errorOutput()))->toContain('shard-'.$shard.'.cov');
})->with([1, 2, 3, 4]);

it('fails when the reports contain no executable code', function (): void {
    $directory = coverageShards([[], [], [], []], empty: true);

    $result = Process::run([PHP_BINARY, base_path('.github/merge-coverage.php'), $directory]);

    expect($result->exitCode())->toBe(1);
});
