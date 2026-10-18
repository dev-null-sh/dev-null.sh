<?php

declare(strict_types=1);

use App\Jobs\ImportGitHubFollowsJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('imports the github follows of the user', function (bool $notify, int $notifications): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();

    fakeGitHubFollows($user, following: [$followed]);

    dispatch(new ImportGitHubFollowsJob($user, $notify));

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
