<?php

declare(strict_types=1);

use App\Enums\EvaluationDecision;
use App\Models\Commit;
use App\Models\CommitEvaluation;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('lists automatically moderated commits, most recent first', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);

    $rejected = Commit::factory()->rejected()->create(['body' => 'Buy stars']);
    CommitEvaluation::factory()->for($rejected)->decided(EvaluationDecision::Rejected)->create();

    $this->travel(1)->minute();

    $approved = Commit::factory()->create(['body' => 'Shipped it']);
    CommitEvaluation::factory()->for($approved)->decided(EvaluationDecision::Approved)->create();

    CommitEvaluation::factory()->decided(EvaluationDecision::Review)->create();
    CommitEvaluation::factory()->failed()->create();
    Commit::factory()->rejected()->create();

    $response = $this->actingAs($admin)->get(route('auto-moderated-commit.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderation/automated')
            ->has('commits.data', 2)
            ->where('commits.data.0.body', 'Shipped it')
            ->where('commits.data.0.evaluation.decision', 'approved')
            ->where('commits.data.1.body', 'Buy stars')
            ->where('commits.data.1.evaluation.decision', 'rejected')
            ->has('commits.data.0.user'));
});

it('lists overturned commits by their latest evaluation', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);

    $commit = Commit::factory()->pending()->create(['body' => 'Reopened']);
    CommitEvaluation::factory()->for($commit)->decided(EvaluationDecision::Rejected)->create();

    $this->travel(1)->minute();

    $reviewed = Commit::factory()->pending()->create();
    CommitEvaluation::factory()->for($reviewed)->decided(EvaluationDecision::Rejected)->create();

    $this->travel(1)->minute();

    CommitEvaluation::factory()->for($reviewed)->decided(EvaluationDecision::Review)->create();

    $response = $this->actingAs($admin)->get(route('auto-moderated-commit.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.body', 'Reopened')
            ->where('commits.data.0.status', 'pending'));
});

it('may not be viewed by non admins', function (): void {
    $response = $this->actingAs(User::factory()->create())->get(route('auto-moderated-commit.index'));

    $response->assertForbidden();
});
