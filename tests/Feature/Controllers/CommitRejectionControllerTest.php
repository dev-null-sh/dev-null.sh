<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitClaim;
use App\Models\CommitDecision;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('may reject a commit', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-rejection.store', $commit));

    $response->assertRedirectToRoute('moderation.show');

    expect($commit->refresh()->status)->toBe(CommitStatus::Rejected);
});

it('may not reject a commit as a non admin', function (): void {
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($commit->user)
        ->post(route('commit-rejection.store', $commit));

    $response->assertForbidden();

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
});

it('records who rejected the commit and why', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();

    $this->actingAs($admin)
        ->post(route('commit-rejection.store', $commit), ['reason' => 'Off-topic.']);

    $decision = CommitDecision::query()->sole();

    expect($decision->user_id)->toBe($admin->id)
        ->and($decision->status)->toBe(CommitStatus::Rejected)
        ->and($decision->reason)->toBe('Off-topic.');
});

it('may not reject a commit another admin is reviewing', function (): void {
    config(['app.admins' => ['admin', 'other']]);

    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();
    CommitClaim::factory()->for($commit)->for(User::factory()->create(['username' => 'other']))->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-rejection.store', $commit));

    $response->assertRedirectToRoute('moderation.show')
        ->assertSessionHasErrors(['commit' => '@other is reviewing this commit.']);

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
});
