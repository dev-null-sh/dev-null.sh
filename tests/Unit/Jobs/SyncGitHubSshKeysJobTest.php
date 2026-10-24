<?php

declare(strict_types=1);

use App\Jobs\SyncGitHubSshKeysJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SshKeyPair;

it('syncs the ssh keys of the user', function (): void {
    $user = User::factory()->create();
    $publicKey = SshKeyPair::generate()->publicKey;

    fakeGitHubSshKeys($user, [$publicKey]);

    dispatch_sync(new SyncGitHubSshKeysJob($user));

    expect($user->sshKeys()->sole()->fingerprint)->toBe($publicKey->fingerprint());
});

it('is dropped when its user is gone', function (): void {
    Http::fake();
    $user = User::factory()->create();
    Queue::connection('database')->push(new SyncGitHubSshKeysJob($user));
    $user->delete();

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    Http::assertNothingSent();
});
