<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Jobs\SyncGitHubSshKeysJob;
use App\Models\Commit;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SshKeyPair;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('queues a sync of the github ssh keys when a signed in user opens a page', function (): void {
    Queue::fake();
    Http::fake();

    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();

    Queue::assertPushed(SyncGitHubSshKeysJob::class, fn (SyncGitHubSshKeysJob $job): bool => $job->user->is($this->user));
    Http::assertNothingSent();
});

it('does not queue another sync when the keys were just synced', function (): void {
    Queue::fake();
    Cache::put("ssh-keys-synced:{$this->user->id}", true, now()->addMinutes(5));

    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();

    Queue::assertNotPushed(SyncGitHubSshKeysJob::class);
});

it('does not queue another sync while one is already queued', function (): void {
    Queue::fake();

    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();
    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();

    Queue::assertPushed(SyncGitHubSshKeysJob::class, 1);
});

it('queues the sync again after the short lock expires', function (): void {
    Queue::fake();

    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();

    $this->travel(61)->seconds();

    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();

    Queue::assertPushed(SyncGitHubSshKeysJob::class, 2);
});

it('does not queue a sync for what a page requests on its own', function (array $headers): void {
    Queue::fake();

    $this->actingAs($this->user)->get(route('log.show', Log::Following), $headers);

    Queue::assertNotPushed(SyncGitHubSshKeysJob::class);
})->with([
    'a partial reload' => [['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'log/show', 'X-Inertia-Partial-Data' => 'commits']],
    'a prefetch' => [['Purpose' => 'prefetch']],
    'a json request' => [['Accept' => 'application/json']],
]);

it('does not queue a sync for a request that fails', function (): void {
    Queue::fake();

    $this->actingAs($this->user)->get(route('operations.show'))->assertNotFound();

    Queue::assertNotPushed(SyncGitHubSshKeysJob::class);
});

it('queues a sync when a signed in user does something without opening a page', function (): void {
    Queue::fake();

    $this->actingAs($this->user)
        ->postJson(route('commit-star.store', Commit::factory()->create()))
        ->assertOk();

    Queue::assertPushed(SyncGitHubSshKeysJob::class, 1);
});

it('does not queue a sync for a guest', function (): void {
    Queue::fake();

    $this->get(route('log.show', Log::Trending))->assertOk();

    Queue::assertNotPushed(SyncGitHubSshKeysJob::class);
});

it('does not call github when looking up an unknown key', function (): void {
    Queue::fake();
    Http::fake();

    $this->call('POST', route('cli-key-lookup.store'), content: SshKeyPair::generate()->publicKey->toOpenSsh())
        ->assertNotFound();

    Http::assertNothingSent();
    Queue::assertNotPushed(SyncGitHubSshKeysJob::class);
});

it('does not queue a sync for a terminal commit', function (): void {
    Queue::fake();
    $keyPair = SshKeyPair::generate();
    SshKey::factory()->for($this->user)->withPublicKey($keyPair->publicKey)->create();
    fakeGitHubSshKeys($this->user, [$keyPair->publicKey]);
    $commit = signedCommit($keyPair, 'It was DNS.');

    $this->call('POST', route('cli-commit.store'), server: [
        'CONTENT_TYPE' => 'text/plain; charset=utf-8',
        'HTTP_X_SSH_SIGNATURE' => $commit['signature'],
    ], content: $commit['payload'])->assertCreated();

    Queue::assertNotPushed(SyncGitHubSshKeysJob::class);
});

it('reports a failure to queue the sync without breaking the page', function (): void {
    Exceptions::fake();
    Schema::drop('jobs');

    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();

    Exceptions::assertReported(QueryException::class);
});
