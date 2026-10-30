<?php

declare(strict_types=1);

use App\Actions\UpdateCommitTopics;
use App\Contracts\Services\GitHubProvider;
use App\Jobs\LinkCommitTopics;
use App\Models\Commit;
use App\Models\Profile;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;

it('links the organizations the commit mentions', function (): void {
    $organization = Profile::factory()->organization()->create(['handle' => 'laravel']);
    $commit = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);

    dispatch_sync(new LinkCommitTopics($commit));

    expect($commit->refresh()->organization_mentions)->toBe(['laravel' => $organization->id]);
});

it('runs again once the rate limit resets when it cut the lookups short', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldNotReceive('accountByLogin');

    $resetsAt = now()->addMinutes(30)->startOfSecond();
    Cache::put('github-organizations:paused', $resetsAt->getTimestamp(), $resetsAt);
    $commit = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);
    $job = new LinkCommitTopics($commit)->withFakeQueueInteractions();

    $job->handle(resolve(UpdateCommitTopics::class));

    $job->assertReleased($resetsAt);
});

it('is done once it looked every handle up', function (): void {
    $commit = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);
    $job = new LinkCommitTopics($commit)->withFakeQueueInteractions();

    $job->handle(resolve(UpdateCommitTopics::class));

    $job->assertNotReleased();
});

it('waits for as many rate limit resets as it takes', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->with('laravel')->andReturn(['login' => 'laravel', 'id' => 958072, 'type' => 'Organization', 'name' => 'Laravel', 'avatar_url' => null, 'bio' => null, 'location' => null, 'blog' => '', 'company' => null]);

    $commit = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);
    Queue::connection('database')->push(new LinkCommitTopics($commit));

    foreach (range(1, 4) as $reset) {
        Cache::put('github-organizations:paused', now()->addHour()->getTimestamp(), now()->addHour());
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        $this->travel(1)->hour();
    }

    $github->shouldNotHaveReceived('accountByLogin');

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    expect($commit->refresh()->organization_mentions)->toBe(['laravel' => Profile::query()->where('handle', 'laravel')->value('id')]);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
});

it('gives up after three failures', function (): void {
    Exceptions::fake();
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->andThrow(new RequestException(new Response(new Psr7Response(502))));

    $commit = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);
    Queue::connection('database')->push(new LinkCommitTopics($commit));

    foreach ([0, 60, 600] as $delay) {
        $this->travel($delay)->seconds();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
    }

    $github->shouldHaveReceived('accountByLogin')->times(3);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 1);
});
