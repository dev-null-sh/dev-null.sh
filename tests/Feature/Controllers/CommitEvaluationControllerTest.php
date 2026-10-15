<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\EvaluationDecision;
use App\Models\Commit;
use App\Models\CommitEvaluation;
use App\Models\ModerationRule;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['app.admins' => ['admin'], 'services.typesafe.key' => 'test-key', 'services.typesafe.enabled' => true]);
});

it('evaluates a commit again after jev failed', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $spam = ModerationRule::factory()->create();
    $commit = Commit::factory()->pending()->create();
    CommitEvaluation::factory()->for($commit)->failed()->create();

    $this->travel(1)->minute();

    Http::fake(['api.typesafe.ai/*' => Http::response(jevAnswers(["rule_{$spam->id}" => 0.99]))]);

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-evaluation.store', $commit));

    $response->assertRedirectToRoute('moderation.show');

    expect($commit->refresh())
        ->status->toBe(CommitStatus::Rejected)
        ->evaluation->decision->toBe(EvaluationDecision::Rejected);
});

it('may not evaluate commits as a non admin', function (): void {
    $commit = Commit::factory()->pending()->create();

    Http::fake();

    $response = $this->actingAs($commit->user)->post(route('commit-evaluation.store', $commit));

    $response->assertForbidden();

    Http::assertNothingSent();
});
