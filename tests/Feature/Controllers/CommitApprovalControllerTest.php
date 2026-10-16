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

it('may approve a commit', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-approval.store', $commit));

    $response->assertRedirectToRoute('moderation.show');

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
});

it('may not approve a deleted commit', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();
    $commit->delete();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-approval.store', $commit));

    $response->assertNotFound();

    expect(Commit::withTrashed()->findOrFail($commit->id)->status)->toBe(CommitStatus::Pending);
});

it('may not approve a commit as a non admin', function (): void {
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($commit->user)
        ->post(route('commit-approval.store', $commit));

    $response->assertForbidden();

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
});

it('records who approved the commit', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();

    $this->actingAs($admin)
        ->post(route('commit-approval.store', $commit));

    $decision = CommitDecision::query()->sole();

    expect($decision->user_id)->toBe($admin->id)
        ->and($decision->commit_id)->toBe($commit->id)
        ->and($decision->status)->toBe(CommitStatus::Approved);
});

it('may approve a commit the admin is reviewing', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();
    CommitClaim::factory()->for($commit)->for($admin)->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-approval.store', $commit));

    $response->assertSessionHasNoErrors();

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
});

it('may not approve a commit another admin is reviewing', function (): void {
    config(['app.admins' => ['admin', 'other']]);

    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();
    CommitClaim::factory()->for($commit)->for(User::factory()->create(['username' => 'other']))->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-approval.store', $commit));

    $response->assertRedirectToRoute('moderation.show')
        ->assertSessionHasErrors(['commit' => '@other is reviewing this commit.']);

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
});
