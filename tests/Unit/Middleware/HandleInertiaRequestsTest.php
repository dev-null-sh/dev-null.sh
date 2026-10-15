<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
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
        'username' => 'test-user',
    ]);

    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    $sharedUser = $middleware->share($request)['auth']['user']();

    expect($sharedUser)->not->toBeNull()
        ->and($sharedUser->id)->toBe($user->id)
        ->and($sharedUser->name)->toBe('Test User')
        ->and($sharedUser->username)->toBe('test-user');
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

    $user = User::factory()->create(['username' => 'octocat']);

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

    $admin = User::factory()->create(['username' => 'admin']);

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

    $admin = User::factory()->create(['username' => 'admin']);
    Commit::factory()->pending()->create()->delete();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $admin);

    $shared = new HandleInertiaRequests()->share($request);

    expect(value($shared['hasUnmoderatedCommits']))->toBeFalse();
});

it('shares hasUnmoderatedCommits as false for non admins', function (): void {
    Commit::factory()->pending()->create();

    $user = User::factory()->create(['username' => 'user']);

    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    $shared = $middleware->share($request);

    expect(value($shared['hasUnmoderatedCommits']))->toBeFalse();
});

it('shares the count of unread notifications that still hold', function (): void {
    $user = User::factory()->create();
    Notification::factory()->for($user, 'recipient')->count(2)->create();
    Notification::factory()->for($user, 'recipient')->read()->create();
    Notification::factory()->for($user, 'recipient')->replied()->create()->commit->update(['status' => CommitStatus::Rejected]);

    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    expect($middleware->share($request)['auth']['unreadNotificationsCount']())->toBe(2);
});

it('shares no unread notifications for guests', function (): void {
    $middleware = new HandleInertiaRequests();

    $shared = $middleware->share(Request::create('/', 'GET'));

    expect($shared['auth']['unreadNotificationsCount']())->toBe(0);
});
