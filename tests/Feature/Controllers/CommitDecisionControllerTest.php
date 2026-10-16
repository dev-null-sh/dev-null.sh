<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitDecision;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('lists the moderation decisions newest first', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $author = User::factory()->create(['username' => 'octocat']);

    CommitDecision::factory()->for($admin)->for(Commit::factory()->for($author)->create(['body' => 'Approved one']))->create([
        'status' => CommitStatus::Approved,
        'created_at' => now()->subMinute(),
    ]);
    CommitDecision::factory()->for($admin)->for(Commit::factory()->rejected()->create(['body' => 'Rejected one']))->create([
        'status' => CommitStatus::Rejected,
        'reason' => 'Spam.',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('commit-decision.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderation/decisions')
            ->has('decisions.data', 2)
            ->where('decisions.data.0.status', 'rejected')
            ->where('decisions.data.0.reason', 'Spam.')
            ->where('decisions.data.0.user.username', 'admin')
            ->where('decisions.data.0.commit.body', 'Rejected one')
            ->where('decisions.data.1.status', 'approved')
            ->where('decisions.data.1.commit.user.username', 'octocat'));
});

it('keeps decisions about deleted commits', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->create();
    CommitDecision::factory()->for($commit)->create();

    $commit->delete();

    $response = $this->actingAs($admin)
        ->get(route('commit-decision.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('decisions.data', 1)
            ->missing('decisions.data.0.commit.body')
            ->has('decisions.data.0.commit.deleted_at'));
});

it('may not be viewed by non admins', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit-decision.index'));

    $response->assertForbidden();
});
