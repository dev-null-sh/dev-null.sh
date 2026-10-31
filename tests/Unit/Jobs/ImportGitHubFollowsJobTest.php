<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Jobs\ImportGitHubFollowsJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('imports the github follows of the user', function (bool $notify, int $notifications): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn([$followed->github_id]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([]);

    dispatch_sync(new ImportGitHubFollowsJob($user, $notify));

    expect($user->isFollowing($followed))->toBeTrue()
        ->and(Notification::query()->count())->toBe($notifications);
})->with([
    'notifying' => [true, 1],
    'silently' => [false, 0],
]);

it('is dropped when its user is gone', function (): void {
    Http::fake();
    $user = User::factory()->create();
    Queue::connection('database')->push(new ImportGitHubFollowsJob($user));
    $user->delete();

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    Http::assertNothingSent();
});
