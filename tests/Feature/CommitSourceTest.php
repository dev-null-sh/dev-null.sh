<?php

declare(strict_types=1);

use App\Actions\UpdateScheduledCommitSubmittedAt;
use App\Enums\CommitSource;
use App\Models\Commit;
use App\Models\Device;
use App\Models\ScheduledCommit;
use App\Models\User;

it('records web commits and replies as web, even in a mobile browser', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();
    $browser = ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)'];

    $this->actingAs($user)->post(route('commit.store'), [
        'body' => 'Created in Safari.',
        'source' => 'ios',
    ], $browser)->assertSessionHasNoErrors();

    $this->postJson(route('commit-reply.store', $parent), [
        'body' => 'Replied in Safari.',
        'source' => 'android',
    ], $browser)->assertCreated();

    expect($user->commits()->oldest()->get()->pluck('source')->all())
        ->toBe([CommitSource::Web, CommitSource::Web]);
});


it('recognizes the lite mobile web view as distinct from a normal browser', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->withHeader('User-Agent', 'Mozilla/5.0 DevNullLite/1.0.0')
        ->post(route('commit.store'), ['body' => 'Sent from the lite app.'])
        ->assertSessionHasNoErrors();

    expect($user->commits()->sole()->source)->toBe(CommitSource::LiteMobile);
});

it('records the approved app platform rather than a client-provided source', function (?string $appId, CommitSource $source): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    Device::query()->where('user_id', $user->id)->sole()->forceFill(['app_id' => $appId])->save();

    signedApiRequest($keyPair, 'POST', route('api-commit.store'), [
        'body' => 'Posted from the app.',
        'source' => 'web',
    ])->assertCreated()->assertJsonPath('data.source', $source->value);

    expect($user->commits()->sole()->source)->toBe($source);
})->with([
    'iOS NativePHP' => ['ios:com.nativephp.devnull', CommitSource::Ios],
    'Android NativePHP' => ['android:com.nativephp.devnull', CommitSource::Android],
    'unrecognized iOS app' => ['ios:sh.dev-null.app', CommitSource::Api],
    'mac app id alone is not enough' => ['macos:sh.dev-null.mac', CommitSource::Api],
    'older app without an id' => [null, CommitSource::Api],
    'unknown app platform' => ['windows:sh.dev-null.app', CommitSource::Api],
]);

it('keeps the original posting source when a web commit is scheduled', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('scheduled-commit.store'), [
        'body' => 'Scheduled from the browser.',
        'scheduled_at' => now()->addHour()->toIso8601String(),
    ])->assertSessionHasNoErrors();

    $scheduled = ScheduledCommit::query()->sole();

    expect($scheduled->source)->toBe(CommitSource::Web);

    expect(resolve(UpdateScheduledCommitSubmittedAt::class)->handle($scheduled->id, early: true))->toBeTrue();
    expect(Commit::query()->sole()->source)->toBe(CommitSource::Web);
});

it('keeps the approved app platform when a native commit is scheduled', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    Device::query()->where('user_id', $user->id)->sole()->forceFill(['app_id' => 'android:com.nativephp.devnull'])->save();

    signedApiRequest($keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => 'Scheduled from Android.',
        'scheduled_at' => now()->addHour()->toIso8601String(),
    ])->assertCreated();

    $scheduled = ScheduledCommit::query()->sole();

    expect($scheduled->source)->toBe(CommitSource::Android);

    expect(resolve(UpdateScheduledCommitSubmittedAt::class)->handle($scheduled->id, early: true))->toBeTrue();
    expect(Commit::query()->sole()->source)->toBe(CommitSource::Android);
});

it('keeps the source of existing commits unknown without guessing', function (): void {
    $commit = Commit::factory()->create();

    expect($commit->source)->toBeNull();

    $this->getJson(route('api-commit.show', $commit))
        ->assertOk()
        ->assertJsonMissingPath('data.source');
});
