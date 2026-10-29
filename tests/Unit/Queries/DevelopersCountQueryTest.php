<?php

declare(strict_types=1);

use App\Models\User;
use App\Queries\DevelopersCountQuery;
use Illuminate\Support\Facades\Cache;

it('rounds down to the hundred below a thousand, leaving banned people out', function (): void {
    User::factory()->count(250)->create();
    User::factory()->count(60)->create(['banned_at' => now()]);

    expect(resolve(DevelopersCountQuery::class)->get())->toBe(200);
});

it('leaves the bots out', function (): void {
    User::factory()->count(99)->create();

    foreach (User::BOT_GITHUB_IDS as $githubId) {
        User::factory()->create(['github_id' => $githubId]);
    }

    expect(resolve(DevelopersCountQuery::class)->get())->toBe(0);
});

it('rounds down to the thousand from a thousand', function (): void {
    User::query()->insert(User::factory()->count(1250)->make()->map(fn (User $user): array => [...$user->getAttributes(), 'id' => $user->newUniqueId()])->all());

    expect(resolve(DevelopersCountQuery::class)->get())->toBe(1000);
});

it('returns an integer when the cached count is a numeric string', function (string $cachedCount, int $expected): void {
    Cache::put('developers-count', $cachedCount, 3600);

    expect(resolve(DevelopersCountQuery::class)->get())->toBe($expected);
})->with([
    ['0', 0],
    ['12000', 12000],
]);

it('counts once an hour', function (): void {
    User::factory()->count(100)->create();

    expect(resolve(DevelopersCountQuery::class)->get())->toBe(100);

    User::factory()->count(100)->create();

    expect(resolve(DevelopersCountQuery::class)->get())->toBe(100);

    Cache::forget('developers-count');

    expect(resolve(DevelopersCountQuery::class)->get())->toBe(200);
});
