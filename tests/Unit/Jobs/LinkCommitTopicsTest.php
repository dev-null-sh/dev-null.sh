<?php

declare(strict_types=1);

use App\Actions\UpdateCommitTopics;
use App\Jobs\LinkCommitTopics;
use App\Models\Commit;
use App\Models\Profile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('links the organizations the commit mentions', function (): void {
    Http::fake();

    $organization = Profile::factory()->organization()->create(['handle' => 'laravel']);
    $commit = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);

    dispatch_sync(new LinkCommitTopics($commit));

    expect($commit->refresh()->organization_mentions)->toBe(['laravel' => $organization->id]);
});

it('runs again once the rate limit resets when it cut the lookups short', function (): void {
    Http::fake();

    $resetsAt = now()->addMinutes(30)->startOfSecond();
    Cache::put('github-organizations:paused', $resetsAt->getTimestamp(), $resetsAt);
    $commit = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);
    $job = new LinkCommitTopics($commit)->withFakeQueueInteractions();

    $job->handle(resolve(UpdateCommitTopics::class));

    Http::assertNothingSent();
    $job->assertReleased($resetsAt);
});

it('is done once it looked every handle up', function (): void {
    Http::fake(['api.github.com/users/*' => Http::response(status: 404)]);

    $commit = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);
    $job = new LinkCommitTopics($commit)->withFakeQueueInteractions();

    $job->handle(resolve(UpdateCommitTopics::class));

    $job->assertNotReleased();
});

it('waits for as many rate limit resets as it takes', function (): void {
    Http::fake([
        'api.github.com/users/laravel' => Http::response(['login' => 'laravel', 'id' => 958072, 'type' => 'Organization', 'name' => 'Laravel', 'avatar_url' => null, 'bio' => null, 'location' => null, 'blog' => '', 'company' => null]),
    ]);

    $commit = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);
    Queue::connection('database')->push(new LinkCommitTopics($commit));

    foreach (range(1, 4) as $reset) {
        Cache::put('github-organizations:paused', now()->addHour()->getTimestamp(), now()->addHour());
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        $this->travel(1)->hour();
    }

    Http::assertNothingSent();

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    expect($commit->refresh()->organization_mentions)->toBe(['laravel' => Profile::query()->where('handle', 'laravel')->value('id')]);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
});

it('gives up after three failures', function (): void {
    Exceptions::fake();
    Http::fake(['api.github.com/users/*' => Http::response(status: 502)]);

    $commit = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);
    Queue::connection('database')->push(new LinkCommitTopics($commit));

    foreach ([0, 60, 600] as $delay) {
        $this->travel($delay)->seconds();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
    }

    Http::assertSentCount(3);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 1);
});
