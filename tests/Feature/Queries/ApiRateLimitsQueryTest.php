<?php

declare(strict_types=1);

use App\Queries\ApiRateLimitsQuery;

it('lists every budget an API request counts toward, as the code sets it', function (): void {
    expect(resolve(ApiRateLimitsQuery::class)->get())->toBe([
        ['what' => 'Unsigned requests', 'limit' => 60, 'per' => 'minute, per IP'],
        ['what' => 'Signed requests', 'limit' => 120, 'per' => 'minute, per person'],
        ['what' => 'Signatures that fail to check out', 'limit' => 60, 'per' => 'minute, per IP'],
        ['what' => 'Commits, replies and quotes, now or scheduled', 'limit' => 10, 'per' => 'minute, shared with the web and the terminal'],
        ['what' => 'Commit searches', 'limit' => 15, 'per' => 'minute'],
        ['what' => 'People searches, mention suggestions and profile cards', 'limit' => 120, 'per' => 'minute'],
        ['what' => 'Issue suggestions', 'limit' => 30, 'per' => 'minute'],
        ['what' => 'Issue suggestions', 'limit' => 300, 'per' => 'day'],
        ['what' => 'Stars', 'limit' => 60, 'per' => 'minute'],
        ['what' => 'Pins', 'limit' => 20, 'per' => 'minute'],
        ['what' => 'Follows', 'limit' => 30, 'per' => 'minute'],
        ['what' => 'Pastéis', 'limit' => 60, 'per' => 'minute'],
        ['what' => 'Translations', 'limit' => 10, 'per' => 'minute'],
        ['what' => 'Reports', 'limit' => 5, 'per' => 'minute'],
        ['what' => 'Organization claims', 'limit' => 10, 'per' => 'minute'],
        ['what' => 'Syncs from GitHub', 'limit' => 1, 'per' => 'minute'],
        ['what' => 'Feature requests and bug reports', 'limit' => 10, 'per' => 'minute'],
        ['what' => 'Feature requests and bug reports opened on GitHub', 'limit' => 10, 'per' => 'day, shared with the web'],
        ['what' => 'Reading and polling remotes', 'limit' => 300, 'per' => 'minute'],
        ['what' => 'Messages', 'limit' => 30, 'per' => 'minute'],
        ['what' => 'Pings', 'limit' => 3, 'per' => 'minute'],
    ]);
});
