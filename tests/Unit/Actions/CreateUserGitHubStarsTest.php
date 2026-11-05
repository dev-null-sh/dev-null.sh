<?php

declare(strict_types=1);

use App\Actions\CreateUserGitHubStars;
use App\Contracts\Services\GitHubProvider;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

function starredGitHubOwnersOf(User $user): array
{
    return DB::table('starred_github_owners')
        ->where('user_id', $user->id)
        ->orderBy('github_id')
        ->get(['github_id', 'repositories_count'])
        ->map(fn (object $row): array => (array) $row)
        ->all();
}

it('remembers whose repositories the user starred, and how many of each', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('starredOwnerIds')->with($user->handle, 3)->andReturn(['1', '2', '1', '1']);

    resolve(CreateUserGitHubStars::class)->handle($user);

    expect(starredGitHubOwnersOf($user))->toBe([
        ['github_id' => '1', 'repositories_count' => 3],
        ['github_id' => '2', 'repositories_count' => 1],
    ])->and($user->refresh()->github_stars_imported_at)->not->toBeNull();
});

it('leaves out the repositories the user starred of their own', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('starredOwnerIds')->with($user->handle, 3)->andReturn([$user->github_id, '2']);

    resolve(CreateUserGitHubStars::class)->handle($user);

    expect(starredGitHubOwnersOf($user))->toBe([['github_id' => '2', 'repositories_count' => 1]]);
});

it('tells only the user that their suggestions can change, once the stars are in', function (): void {
    Event::fake([UsersUpdated::class]);
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('starredOwnerIds')->with($user->handle, 3)->andReturn(['1']);

    resolve(CreateUserGitHubStars::class)->handle($user);

    Event::assertDispatchedTimes(UsersUpdated::class, 1);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::GitHubStars && $event->userIds === [$user->id]);
});

it('follows nobody', function (): void {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('starredOwnerIds')->with($user->handle, 3)->andReturn([$owner->github_id]);

    resolve(CreateUserGitHubStars::class)->handle($user);

    expect($user->following()->count())->toBe(0);
});

it('imports the stars of a user only once', function (): void {
    $user = User::factory()->create(['github_stars_imported_at' => now()]);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->never();

    resolve(CreateUserGitHubStars::class)->handle($user);
});

it('marks the stars as imported when the github account no longer exists', function (): void {
    $user = User::factory()->create();

    resolve(CreateUserGitHubStars::class)->handle($user);

    expect(starredGitHubOwnersOf($user))->toBe([])
        ->and($user->refresh()->github_stars_imported_at)->not->toBeNull();
});

it('leaves the stars to import again when github fails', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('starredOwnerIds')->andThrow(new RequestException(new Response(new Psr7Response(500))));

    expect(fn () => resolve(CreateUserGitHubStars::class)->handle($user))->toThrow(RequestException::class)
        ->and($user->refresh()->github_stars_imported_at)->toBeNull();
});
