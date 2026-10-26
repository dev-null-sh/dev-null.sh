<?php

declare(strict_types=1);

use App\Jobs\ImportGitHubFollowsJob;
use App\Models\Notification;
use App\Models\User;

it('imports the github follows of the user', function (bool $notify, int $notifications): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();

    fakeGitHubFollows($user, following: [$followed]);

    dispatch_sync(new ImportGitHubFollowsJob($user, $notify));

    expect($user->isFollowing($followed))->toBeTrue()
        ->and(Notification::query()->count())->toBe($notifications);
})->with([
    'notifying' => [true, 1],
    'silently' => [false, 0],
]);
