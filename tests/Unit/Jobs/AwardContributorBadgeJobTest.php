<?php

declare(strict_types=1);

use App\Jobs\AwardContributorBadgeJob;
use App\Models\User;

it('retries the award by immutable github id after the reporter renames', function (): void {
    $reporter = User::factory()->create(['github_id' => '42', 'username' => 'old-name']);
    $job = new AwardContributorBadgeJob($reporter->github_id);

    $reporter->update(['username' => 'new-name']);
    $impostor = User::factory()->create(['username' => 'old-name']);

    dispatch($job);

    expect($reporter->refresh()->is_contributor)->toBeTrue()
        ->and($impostor->refresh()->is_contributor)->toBeFalse();

    $awardedAt = $reporter->updated_at;
    $this->travel(1)->minute();
    dispatch($job);

    expect($reporter->refresh()->updated_at->equalTo($awardedAt))->toBeTrue();
});

it('ignores an account that no longer exists', function (): void {
    $other = User::factory()->create();

    dispatch(new AwardContributorBadgeJob('42'));

    expect($other->refresh()->is_contributor)->toBeFalse();
});
