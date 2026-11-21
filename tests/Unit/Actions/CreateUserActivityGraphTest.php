<?php

declare(strict_types=1);

use App\Actions\CreateUserActivityGraph;
use App\Enums\ActivityGraphTheme;
use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 30));
});

function activityGraph(string $svg): DOMXPath
{
    $document = new DOMDocument;

    expect($document->loadXML($svg))->toBeTrue();

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');

    return $xpath;
}

/**
 * @return list<DOMElement>
 */
function activityGraphCells(DOMXPath $xpath): array
{
    $cells = [];

    foreach ($xpath->query('//svg:g[@class="cells"]/svg:rect') ?: [] as $cell) {
        assert($cell instanceof DOMElement);

        $cells[] = $cell;
    }

    return $cells;
}

/**
 * @return list<string>
 */
function activityGraphLevels(DOMXPath $xpath): array
{
    return array_map(fn (DOMElement $cell): string => $cell->getAttribute('class'), activityGraphCells($xpath));
}

function activityGraphText(DOMXPath $xpath): string
{
    $text = $xpath->evaluate('normalize-space(/svg:svg)');
    assert(is_string($text));

    return $text;
}

it('draws a cell for every day of the last year, shaded by its share of the busiest day', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->count(4)->create();
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(3)]);
    Commit::factory()->for($user)->count(2)->create(['created_at' => now()->setDate(2025, 10, 12)]);
    Commit::factory()->for($user)->create(['created_at' => now()->setDate(2025, 10, 11)]);
    Commit::factory()->create();

    $levels = activityGraphLevels(activityGraph(resolve(CreateUserActivityGraph::class)->handle($user, ActivityGraphTheme::Light)));

    expect($levels)->toHaveCount(369)
        ->and($levels[0])->toBe('l2')
        ->and($levels[365])->toBe('l1')
        ->and($levels[368])->toBe('l4')
        ->and(array_count_values($levels))->toBe(['l2' => 1, 'l0' => 366, 'l1' => 1, 'l4' => 1]);
});

it('lays the days out in columns of weeks starting on sunday', function (): void {
    $xpath = activityGraph(resolve(CreateUserActivityGraph::class)->handle(User::factory()->create(), ActivityGraphTheme::Light));

    $positions = array_map(
        fn (DOMElement $cell): array => [(float) $cell->getAttribute('x'), (float) $cell->getAttribute('y')],
        activityGraphCells($xpath),
    );

    expect($positions[1][0])->toBe($positions[0][0])
        ->and($positions[1][1])->toBeGreaterThan($positions[0][1])
        ->and($positions[7][0])->toBeGreaterThan($positions[6][0])
        ->and($positions[7][1])->toBe($positions[0][1])
        ->and(activityGraphText($xpath))->toContain('Mon Wed Fri')->toContain('Nov Dec Jan');
});

it('only draws approved commits that still exist', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->pending()->count(3)->create();
    Commit::factory()->for($user)->rejected()->create(['created_at' => now()->subDay()]);
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(2)])->delete();

    $xpath = activityGraph(resolve(CreateUserActivityGraph::class)->handle($user, ActivityGraphTheme::Light));

    expect(array_count_values(activityGraphLevels($xpath)))->toBe(['l0' => 368, 'l4' => 1])
        ->and(activityGraphText($xpath))->toContain('1 commit in the last year');
});

it('labels the graph with the handle, the commit total, and the streaks', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);

    Commit::factory()->for($user)->count(2)->create();
    Commit::factory()->for($user)->create(['created_at' => now()->subDay()]);
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(5)]);

    $text = activityGraphText(activityGraph(resolve(CreateUserActivityGraph::class)->handle($user, ActivityGraphTheme::Light)));

    expect($text)
        ->toContain('@octocat')
        ->toContain('/dev/null')
        ->toContain('4 commits in the last year')
        ->toContain('longest streak 2 days')
        ->toContain('current 2')
        ->toContain('Less')
        ->toContain('More');
});

it('leaves out the streaks before the first commit', function (): void {
    $text = activityGraphText(activityGraph(resolve(CreateUserActivityGraph::class)->handle(User::factory()->create(), ActivityGraphTheme::Light)));

    expect($text)->toContain('0 commits in the last year')
        ->not->toContain('streak');
});

it('leaves out the current streak once it is broken', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create(['created_at' => now()->subDays(5)]);

    $text = activityGraphText(activityGraph(resolve(CreateUserActivityGraph::class)->handle($user, ActivityGraphTheme::Light)));

    expect($text)->toContain('longest streak 1 day')
        ->not->toContain('current');
});

it('escapes the handle', function (): void {
    $user = User::factory()->create(['handle' => '"><script>alert(1)</script>']);

    $svg = resolve(CreateUserActivityGraph::class)->handle($user, ActivityGraphTheme::Light);

    expect($svg)->not->toContain('<script')
        ->and(activityGraphText(activityGraph($svg)))->toContain('@"><script>alert(1)</script>');
});

it('draws in the colors of the theme and the lane of the user', function (ActivityGraphTheme $theme, array $present, array $absent): void {
    $user = User::factory()->create(['preferred_lane' => 2]);

    $svg = resolve(CreateUserActivityGraph::class)->handle($user, $theme);

    expect($svg)->toContain(...$present)
        ->and(array_values(array_filter($absent, fn (string $needle): bool => str_contains($svg, $needle))))->toBe([]);
})->with([
    'light' => [ActivityGraphTheme::Light, ['#ffffff', '#eef0f2', '#9c36b5'], ['#18181a', '#da77f2', '@media']],
    'dark' => [ActivityGraphTheme::Dark, ['#18181a', '#232326', '#da77f2'], ['#ffffff', '#9c36b5', '@media']],
    'auto' => [ActivityGraphTheme::Auto, ['#ffffff', '#9c36b5', '@media (prefers-color-scheme: dark)', '#18181a', '#da77f2'], []],
]);

it('draws in the custom color of the user in every theme', function (ActivityGraphTheme $theme): void {
    $user = User::factory()->create(['lane_color' => '#1a2b3c']);

    $svg = resolve(CreateUserActivityGraph::class)->handle($user, $theme);

    expect($svg)->toContain('.lane{fill:#1a2b3c}', '.l4{fill:#1a2b3c}');
})->with([ActivityGraphTheme::Light, ActivityGraphTheme::Dark]);

it('mixes the lower levels from the lane into the card like the profile does', function (ActivityGraphTheme $theme, array $levels): void {
    $user = User::factory()->create(['preferred_lane' => 0]);

    $svg = resolve(CreateUserActivityGraph::class)->handle($user, $theme);

    foreach ($levels as $level => $color) {
        expect($svg)->toContain(".l{$level}{fill:{$color}}");
    }
})->with([
    'light' => [ActivityGraphTheme::Light, [1 => '#fac8b8', 2 => '#f19e84', 3 => '#e5724e', 4 => '#d9480f']],
    'dark' => [ActivityGraphTheme::Dark, [1 => '#5a3a2d', 2 => '#915639', 3 => '#cc7244', 4 => '#ff8a4c']],
]);
