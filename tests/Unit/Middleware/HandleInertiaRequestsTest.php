<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\LivePlatform;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use App\Queries\SuggestedUsersQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

it('shares app name from config', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');

    $shared = $middleware->share($request);

    expect($shared)->toHaveKey('name')
        ->and($shared['name'])->toBe(config('app.name'));
});

it('shares null user when guest', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');

    $shared = $middleware->share($request);

    expect($shared)->toHaveKey('auth')
        ->and($shared['auth'])->toHaveKey('user')
        ->and($shared['auth']['user']())->toBeNull();
});

it('shares authenticated user data', function (): void {
    $user = User::factory()->create([
        'name' => 'Test User',
        'handle' => 'test-user',
    ]);

    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    $sharedUser = $middleware->share($request)['auth']['user']();

    expect($sharedUser)->not->toBeNull()
        ->and($sharedUser->id)->toBe($user->id)
        ->and($sharedUser->name)->toBe('Test User')
        ->and($sharedUser->handle)->toBe('test-user');
});

it('shares the authenticated user commit and follow counts', function (): void {
    $user = User::factory()->has(Commit::factory()->count(2))->create();
    $user->following()->attach(User::factory()->count(3)->create());
    User::factory()->create()->following()->attach($user);

    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    $sharedUser = $middleware->share($request)['auth']['user']();

    expect($sharedUser->commits_count)->toBe(2)
        ->and($sharedUser->following_count)->toBe(3)
        ->and($sharedUser->followers_count)->toBe(1);
});

it('defers loading the authenticated user until the prop is resolved', function (): void {
    $user = User::factory()->create();

    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    DB::enableQueryLog();

    $middleware->share($request);

    expect(DB::getQueryLog())->toBeEmpty();
});

it('shares how many developers are here with guests only', function (): void {
    $user = User::factory()->create();
    User::factory()->count(149)->create();

    $request = Request::create('/', 'GET');

    expect(new HandleInertiaRequests()->share($request)['developersCount']())->toBe(100);

    $request->setUserResolver(fn (): User => $user);

    expect(new HandleInertiaRequests()->share($request)['developersCount']())->toBeNull();
});

it('defaults sidebarOpen to true when no cookie', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');

    $shared = $middleware->share($request);

    expect($shared)->toHaveKey('sidebarOpen')
        ->and($shared['sidebarOpen'])->toBeTrue();
});

it('sets sidebarOpen to true when cookie is true', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->cookies->set('sidebar_state', 'true');

    $shared = $middleware->share($request);

    expect($shared['sidebarOpen'])->toBeTrue();
});

it('sets sidebarOpen to false when cookie is false', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->cookies->set('sidebar_state', 'false');

    $shared = $middleware->share($request);

    expect($shared['sidebarOpen'])->toBeFalse();
});

it('includes parent shared data', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');

    $shared = $middleware->share($request);

    // Parent Inertia middleware shares 'errors' by default
    expect($shared)->toHaveKey('errors');
});

it('shares whether the authenticated user is an admin', function (bool $isAdmin): void {
    config(['app.admins' => $isAdmin ? ['octocat'] : []]);

    $user = User::factory()->create(['handle' => 'octocat']);

    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    $shared = $middleware->share($request);

    expect($shared['auth']['isAdmin'])->toBe($isAdmin);
})->with([true, false]);

it('shares guests as non admins', function (): void {
    $middleware = new HandleInertiaRequests();

    $shared = $middleware->share(Request::create('/', 'GET'));

    expect($shared['auth']['isAdmin'])->toBeFalse();
});

it('shares whether there are unmoderated commits for admins', function (): void {
    config(['app.admins' => ['admin']]);

    $admin = User::factory()->create(['handle' => 'admin']);

    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $admin);

    $shared = $middleware->share($request);

    expect(value($shared['hasUnmoderatedCommits']))->toBeFalse();

    Commit::factory()->pending()->create();

    expect(value($shared['hasUnmoderatedCommits']))->toBeTrue();
});

it('does not count deleted pending commits as unmoderated', function (): void {
    config(['app.admins' => ['admin']]);

    $admin = User::factory()->create(['handle' => 'admin']);
    Commit::factory()->pending()->create()->delete();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $admin);

    $shared = new HandleInertiaRequests()->share($request);

    expect(value($shared['hasUnmoderatedCommits']))->toBeFalse();
});

it('shares hasUnmoderatedCommits as false for non admins', function (): void {
    Commit::factory()->pending()->create();

    $user = User::factory()->create(['handle' => 'user']);

    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    $shared = $middleware->share($request);

    expect(value($shared['hasUnmoderatedCommits']))->toBeFalse();
});

it('shares the count of unread notification groups that still hold', function (): void {
    $user = User::factory()->create();
    Notification::factory()->for($user, 'recipient')->count(2)->create();
    Notification::factory()->for($user, 'recipient')->starred()->create();
    Notification::factory()->for($user, 'recipient')->read()->create();
    Notification::factory()->for($user, 'recipient')->replied()->create()->commit->update(['status' => CommitStatus::Rejected]);

    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    expect($middleware->share($request)['auth']['unreadNotificationsCount']())->toBe(2);
});

it('counts grouped notifications once', function (): void {
    $user = User::factory()->create();
    $starred = Notification::factory()->for($user, 'recipient')->starred()->create();
    Notification::factory()->for($user, 'recipient')->starred($starred->commit)->create();

    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    expect($middleware->share($request)['auth']['unreadNotificationsCount']())->toBeOne();
});

it('shares no unread notifications for guests', function (): void {
    $middleware = new HandleInertiaRequests();

    $shared = $middleware->share(Request::create('/', 'GET'));

    expect($shared['auth']['unreadNotificationsCount']())->toBe(0);
});

it('dims lanes by default or when enabled', function (string $cookie): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');

    if ($cookie !== '') {
        $request->cookies->set('lane_dimming', $cookie);
    }

    $shared = $middleware->share($request);

    expect($shared['dimsLanes'])->toBeTrue();
})->with(['', 'true']);

it('does not dim lanes when disabled', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->cookies->set('lane_dimming', 'false');

    $shared = $middleware->share($request);

    expect($shared['dimsLanes'])->toBeFalse();
});

it('shares the moment the page is rendered, which relative times count from on the server and while hydrating', function (): void {
    $this->travelTo(Date::parse('2026-10-24T12:34:56.789Z'));

    $shared = new HandleInertiaRequests()->share(Request::create('/', 'GET'));

    expect($shared['now'])->toBe(1792845296789);
});

it("shares the reader's clock their browser left in cookies, so the server shows times as the browser will", function (?string $timeZone, ?string $hourCycle, array $clock, mixed $dateOrder = null): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');

    if ($timeZone !== null) {
        $request->cookies->set('time_zone', $timeZone);
    }

    if ($hourCycle !== null) {
        $request->cookies->set('hour_cycle', $hourCycle);
    }

    $request->cookies->set('date_order', $dateOrder);

    $shared = $middleware->share($request);

    expect($shared['clock'])->toBe($clock + ['dateOrder' => null]);
})->with([
    'unknown on a first visit' => [null, null, ['timeZone' => null, 'hourCycle' => null]],
    'both' => ['Pacific/Auckland', 'h23', ['timeZone' => 'Pacific/Auckland', 'hourCycle' => 'h23']],
    'a 12-hour clock' => ['America/New_York', 'h12', ['timeZone' => 'America/New_York', 'hourCycle' => 'h12']],
    'a former name browsers still report' => ['Asia/Calcutta', 'h12', ['timeZone' => 'Asia/Calcutta', 'hourCycle' => 'h12']],
    'the current name of a former name' => ['Asia/Kolkata', 'h12', ['timeZone' => 'Asia/Kolkata', 'hourCycle' => 'h12']],
    'a legacy regional alias' => ['US/Eastern', 'h12', ['timeZone' => 'US/Eastern', 'hourCycle' => 'h12']],
    'a custom offset rather than a system time zone' => ['GMT+05:00', 'h23', ['timeZone' => null, 'hourCycle' => 'h23']],
    'UTC' => ['UTC', 'h23', ['timeZone' => 'UTC', 'hourCycle' => 'h23']],
    'not a time zone' => ['Mars/Olympus', 'h23', ['timeZone' => null, 'hourCycle' => 'h23']],
    'not an hour cycle' => ['Europe/Paris', 'h25', ['timeZone' => 'Europe/Paris', 'hourCycle' => null]],
    'day first' => ['UTC', 'h23', ['timeZone' => 'UTC', 'hourCycle' => 'h23', 'dateOrder' => 'day_first'], 'day_first'],
    'month first' => ['UTC', 'h23', ['timeZone' => 'UTC', 'hourCycle' => 'h23', 'dateOrder' => 'month_first'], 'month_first'],
    'not a date order' => ['UTC', 'h23', ['timeZone' => 'UTC', 'hourCycle' => 'h23'], 'unknown'],
    'not a date order string' => ['UTC', 'h23', ['timeZone' => 'UTC', 'hourCycle' => 'h23'], []],
]);

it('shows 3 replies at a time unless the user picked one of the other counts', function (string $cookie, int $count): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');

    if ($cookie !== '') {
        $request->cookies->set('replies_per_page', $cookie);
    }

    $shared = $middleware->share($request);

    expect($shared['repliesPerPage'])->toBe($count);
})->with([
    'default' => ['', 3],
    'picked' => ['20', 20],
    'one' => ['1', 1],
    'not an option' => ['7', 3],
    'garbage' => ['many', 3],
    'garbage after an option' => ['10garbage', 3],
    'an option as a decimal' => ['1.0', 3],
]);

it('does not share suggested users with guests', function (): void {
    $middleware = new HandleInertiaRequests();

    $shared = $middleware->share(Request::create('/', 'GET'));

    expect($shared)->not->toHaveKey('suggestedUsers');
});

it('does not show who to follow to guests, who have nobody to be suggested to', function (): void {
    $middleware = new HandleInertiaRequests();

    $shared = $middleware->share(Request::create('/', 'GET'));

    expect($shared['showsWhoToFollow'])->toBeFalse();
});

it('shows who to follow by default', function (string $cookie): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => User::factory()->create());

    if ($cookie !== '') {
        $request->cookies->set('who_to_follow', $cookie);
    }

    $shared = $middleware->share($request);

    expect($shared['showsWhoToFollow'])->toBeTrue()
        ->and($shared)->toHaveKey('suggestedUsers');
})->with(['', 'true']);

it('leaves who to follow and live following out of the operations console, which has no aside', function (string $path): void {
    $middleware = new HandleInertiaRequests();
    $user = User::factory()->create();
    $live = User::factory()->create([
        'live_platform' => LivePlatform::YouTube,
        'live_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'live_ends_at' => now()->addHour(),
    ]);
    $user->following()->attach($live);

    $request = Request::create($path, 'GET');
    $request->setUserResolver(fn () => $user);

    $shared = $middleware->share($request);

    expect($shared)->not->toHaveKey('suggestedUsers')
        ->and($shared['liveFollowing']())->toBeEmpty();
})->with(['/operations', '/operations/events']);

it('suggests the round of people the user refreshed to', function (string $cookie, int $round): void {
    $middleware = new HandleInertiaRequests();
    $user = User::factory()->create();
    User::factory()->count(6)->has(Commit::factory())->create();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->cookies->set('who_to_follow_round', $cookie);

    $suggested = $middleware->share($request)['suggestedUsers']();

    expect($suggested->pluck('id')->all())
        ->toBe(resolve(SuggestedUsersQuery::class)->get($user, round: $round, spares: 3)->pluck('id')->all());
})->with([
    'next round' => ['1', 1],
    'not a number' => ['abc', 0],
    'negative' => ['-3', 0],
]);
