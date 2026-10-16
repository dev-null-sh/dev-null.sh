<?php

declare(strict_types=1);

use App\Actions\UpdateCommitClaim;
use App\Models\Commit;
use App\Models\CommitClaim;
use App\Models\User;

it('claims a commit for five minutes', function (): void {
    $this->freezeSecond();

    $user = User::factory()->create();
    $commit = Commit::factory()->pending()->create();

    $claim = resolve(UpdateCommitClaim::class)->handle($user, $commit);

    expect($claim->commit_id)->toBe($commit->id)
        ->and($claim->user_id)->toBe($user->id)
        ->and($claim->expires_at->equalTo(now()->addMinutes(5)))->toBeTrue();
});

it('extends a claim the user already holds', function (): void {
    $this->freezeSecond();

    $user = User::factory()->create();
    $commit = Commit::factory()->pending()->create();
    CommitClaim::factory()->for($commit)->for($user)->create(['expires_at' => now()->addMinute()]);

    $this->travel(30)->seconds();

    resolve(UpdateCommitClaim::class)->handle($user, $commit);

    expect(CommitClaim::query()->sole()->expires_at->equalTo(now()->addMinutes(5)))->toBeTrue();
});

it('takes over the claim of another user', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->pending()->create();
    CommitClaim::factory()->for($commit)->create();

    resolve(UpdateCommitClaim::class)->handle($user, $commit);

    expect(CommitClaim::query()->sole()->user_id)->toBe($user->id);
});

it('keeps one claim per user', function (): void {
    $user = User::factory()->create();
    $first = Commit::factory()->pending()->create();
    $second = Commit::factory()->pending()->create();

    resolve(UpdateCommitClaim::class)->handle($user, $first);
    resolve(UpdateCommitClaim::class)->handle($user, $second);

    expect(CommitClaim::query()->sole()->commit_id)->toBe($second->id);
});
