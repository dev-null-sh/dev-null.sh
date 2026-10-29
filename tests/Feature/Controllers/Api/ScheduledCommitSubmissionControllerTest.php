<?php

declare(strict_types=1);

use App\Actions\DeleteScheduledCommit;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\ScheduledCommit;
use App\Models\User;
use App\Models\UserTimeout;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('commits a scheduled commit now, before its time, through the usual review', function (): void {
    $scheduled = ScheduledCommit::factory()->for($this->user)->create(['scheduled_at' => now()->addDay(), 'body' => 'Shipping early']);

    $response = signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit-submission.store', $scheduled));

    $commit = Commit::query()->sole();

    $response->assertOk()
        ->assertJsonPath('data.id', $scheduled->id)
        ->assertJsonPath('data.body', null)
        ->assertJsonPath('data.submitted_at', now()->toIso8601String())
        ->assertJsonPath('data.status', 'pending');

    expect($commit->body)->toBe('Shipping early')
        ->and($commit->status)->toBe(CommitStatus::Pending)
        ->and($scheduled->fresh()->commit_id)->toBe($commit->id);

    $this->travel(1)->day();
    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    expect(Commit::query()->count())->toBe(1);
});

it('tells the app to refresh when committing now one already submitted or canceled', function (bool $canceled): void {
    $scheduled = ScheduledCommit::factory()->for($this->user)->create();

    if ($canceled) {
        resolve(DeleteScheduledCommit::class)->handle($this->user, $scheduled);
    } else {
        signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit-submission.store', $scheduled))->assertOk();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit-submission.store', $scheduled))
        ->assertConflict()
        ->assertExactJson(['message' => 'This commit was already submitted or canceled. Refresh to see its status.']);

    expect(Commit::query()->count())->toBe($canceled ? 0 : 1);
})->with(['canceled' => true, 'submitted' => false]);

it("may not commit now another user's scheduled commit", function (): void {
    $scheduled = ScheduledCommit::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit-submission.store', $scheduled))->assertNotFound();

    expect(Commit::query()->count())->toBe(0)
        ->and($scheduled->fresh()->submitted_at)->toBeNull();
});

it('may not commit now while the user is in timeout', function (): void {
    $scheduled = ScheduledCommit::factory()->for($this->user)->create();
    UserTimeout::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit-submission.store', $scheduled))
        ->assertForbidden()
        ->assertJsonPath('code', 'timed_out');

    expect(Commit::query()->count())->toBe(0);
});

it('requires a signed request', function (): void {
    $scheduled = ScheduledCommit::factory()->for($this->user)->create();

    $this->postJson(route('api-scheduled-commit-submission.store', $scheduled))->assertUnauthorized();
});
