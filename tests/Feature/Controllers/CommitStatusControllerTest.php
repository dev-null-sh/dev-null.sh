<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

it('returns that the authors own pending commit is being checked', function (): void {
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->pending()->create();

    $this->actingAs($author)
        ->getJson(route('commit-status.show', $commit))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['status' => 'pending', 'is_checking' => true]);
});

it('returns that a checked pending commit awaits a moderator', function (): void {
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->pending()->create(['ai_decision' => CommitStatus::Approved]);

    $this->actingAs($author)
        ->getJson(route('commit-status.show', $commit))
        ->assertOk()
        ->assertExactJson(['status' => 'pending', 'is_checking' => false]);
});

it('returns that a pending commit is no longer being checked once every attempt has had its chance', function (): void {
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->pending()->create([
        'created_at' => now()->subMinutes(Commit::CHECKING_MINUTES + 1),
    ]);

    $this->actingAs($author)
        ->getJson(route('commit-status.show', $commit))
        ->assertOk()
        ->assertExactJson(['status' => 'pending', 'is_checking' => false]);
});

it('keeps a pending commit checking during its retry window even when the provider is misconfigured', function (): void {
    config(['ai.providers.openai.key' => null]);

    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->pending()->create();

    $this->actingAs($author)
        ->getJson(route('commit-status.show', $commit))
        ->assertOk()
        ->assertExactJson(['status' => 'pending', 'is_checking' => true]);
});

it('returns the status of an approved commit', function (): void {
    $commit = Commit::factory()->create();

    $this->getJson(route('commit-status.show', $commit))
        ->assertOk()
        ->assertExactJson(['status' => 'approved', 'is_checking' => false]);
});

it('hides the status of commits the viewer cannot see', function (?string $viewer): void {
    $commit = Commit::factory()->pending()->create();

    if ($viewer === 'user') {
        $this->actingAs(User::factory()->create());
    }

    $this->getJson(route('commit-status.show', $commit))->assertNotFound();
})->with(['guest' => null, 'user' => 'user']);
