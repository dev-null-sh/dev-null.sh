<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Jobs\ImportGitHubFollowsJob;
use App\Models\Notification;
use App\Models\User;

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
