<?php

declare(strict_types=1);

use App\Actions\UpdateCommitTopics;
use App\Jobs\LinkCommitTopics;
use App\Models\Commit;
use App\Models\Profile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
