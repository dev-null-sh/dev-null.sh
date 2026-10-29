<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Moderation\DeterministicModerationSignals;

it('hashes bodies after normalizing case and whitespace', function (): void {
    $signals = resolve(DeterministicModerationSignals::class);

    expect($signals->bodyHash("  Hello   World\n"))
        ->toBe($signals->bodyHash('hello world'));
});

it('does not flag the same text pending from another user', function (): void {
    $hash = resolve(DeterministicModerationSignals::class)->bodyHash('thanks!');

    Commit::factory()->pending()->create(['body' => 'Thanks!', 'body_hash' => $hash]);
    $commit = Commit::factory()->pending()->create(['body' => 'thanks!', 'body_hash' => $hash]);

    $codes = array_column(resolve(DeterministicModerationSignals::class)->handle($commit), 'code');

    expect($codes)->not->toContain('duplicate');
});

it('flags duplicates of the same author pending bodies', function (): void {
    $hash = resolve(DeterministicModerationSignals::class)->bodyHash('same message');
    $user = User::factory()->create();

    Commit::factory()->pending()->for($user)->create([
        'body' => 'same message',
        'body_hash' => $hash,
    ]);

    $commit = Commit::factory()->pending()->for($user)->create([
        'body' => 'Same   Message',
        'body_hash' => $hash,
    ]);

    $codes = array_column(resolve(DeterministicModerationSignals::class)->handle($commit), 'code');

    expect($codes)->toContain('duplicate');
});

it('flags duplicates of rejected bodies', function (): void {
    $hash = resolve(DeterministicModerationSignals::class)->bodyHash('rejected twin');

    Commit::factory()->rejected()->create([
        'body' => 'rejected twin',
        'body_hash' => $hash,
    ]);

    $commit = Commit::factory()->pending()->create([
        'body' => 'rejected twin',
        'body_hash' => $hash,
    ]);

    $codes = array_column(resolve(DeterministicModerationSignals::class)->handle($commit), 'code');

    expect($codes)->toContain('duplicate');
});

it('flags author flood when pending commits meet the threshold', function (): void {
    config(['moderation.author_flood_threshold' => 3]);

    $user = User::factory()->create();
    Commit::factory()->pending()->for($user)->count(3)->create();

    $commit = Commit::query()->where('user_id', $user->id)->pending()->latest('id')->firstOrFail();

    $codes = array_column(resolve(DeterministicModerationSignals::class)->handle($commit), 'code');

    expect($codes)->toContain('author_flood');
});

it('flags burst posting', function (): void {
    config([
        'moderation.burst.count' => 3,
        'moderation.burst.minutes' => 10,
    ]);

    $user = User::factory()->create();
    Commit::factory()->for($user)->count(3)->create([
        'status' => CommitStatus::Pending,
        'created_at' => now()->subMinute(),
    ]);

    $commit = Commit::query()->where('user_id', $user->id)->latest('id')->firstOrFail();

    $codes = array_column(resolve(DeterministicModerationSignals::class)->handle($commit), 'code');

    expect($codes)->toContain('burst');
});

it('measures bursts from when the commit was posted', function (): void {
    config([
        'moderation.burst.count' => 3,
        'moderation.burst.minutes' => 10,
    ]);

    $user = User::factory()->create();
    Commit::factory()->for($user)->count(3)->create([
        'status' => CommitStatus::Pending,
        'created_at' => now()->subDay(),
    ]);

    $old = Commit::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    $recent = Commit::factory()->for($user)->pending()->create(['created_at' => now()]);

    $signals = resolve(DeterministicModerationSignals::class);

    expect(array_column($signals->handle($old), 'code'))->toContain('burst')
        ->and(array_column($signals->handle($recent), 'code'))->not->toContain('burst');
});

it('does not count commits posted after it in the same second as a burst', function (): void {
    config([
        'moderation.burst.count' => 3,
        'moderation.burst.minutes' => 10,
    ]);

    $user = User::factory()->create();
    $commits = Commit::factory()->for($user)->count(3)->create([
        'status' => CommitStatus::Pending,
        'created_at' => now()->subMinute(),
    ]);

    $signals = resolve(DeterministicModerationSignals::class);

    expect(array_column($signals->handle($commits->first()), 'code'))->not->toContain('burst')
        ->and(array_column($signals->handle($commits->last()), 'code'))->toContain('burst');
});

it('flags urls', function (): void {
    $commit = Commit::factory()->pending()->create([
        'body' => 'Check https://example.com/path',
    ]);

    $codes = array_column(resolve(DeterministicModerationSignals::class)->handle($commit), 'code');

    expect($codes)->toContain('has_url');
});

it('flags invite-like copy', function (): void {
    $commit = Commit::factory()->pending()->create([
        'body' => 'Join my discord for free tips',
    ]);

    $codes = array_column(resolve(DeterministicModerationSignals::class)->handle($commit), 'code');

    expect($codes)->toContain('invite_like');
});

it('returns no signals for ordinary text', function (): void {
    $commit = Commit::factory()->pending()->create([
        'body' => 'Shipped a quiet refactor of the log query.',
    ]);

    expect(resolve(DeterministicModerationSignals::class)->handle($commit))->toBe([]);
});

it('does not flag image-only commits as duplicates of each other', function (): void {
    $signals = resolve(DeterministicModerationSignals::class);

    Commit::factory()->pending()->create(['body' => '', 'body_hash' => $signals->bodyHash('')]);
    $commit = Commit::factory()->pending()->create(['body' => '', 'body_hash' => $signals->bodyHash('')]);

    expect(array_column($signals->handle($commit), 'code'))->not->toContain('duplicate');
});
