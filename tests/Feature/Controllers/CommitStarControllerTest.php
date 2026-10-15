<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;

it('may star a commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit-star.store', $commit));

    $response->assertRedirectToRoute('log.show', Log::Everyone);

    expect($user->hasStarred($commit))->toBeTrue();
});

it('starring is idempotent', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    $this->actingAs($user)->post(route('commit-star.store', $commit))->assertRedirect();
    $this->actingAs($user)->post(route('commit-star.store', $commit))->assertRedirect();

    expect($user->starredCommits()->count())->toBeOne();
});

it('may star their own pending commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->pending()->create();

    $this->actingAs($user)
        ->post(route('commit-star.store', $commit))
        ->assertRedirect();

    expect($user->hasStarred($commit))->toBeTrue();
});

it('may not star a commit that is not visible', function (string $state): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->{$state}()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit-star.store', $commit))
        ->assertForbidden();

    expect($user->hasStarred($commit))->toBeFalse();
})->with(['pending', 'rejected']);

it('may unstar a commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    $user->starredCommits()->attach($commit);

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Everyone)
        ->delete(route('commit-star.destroy', $commit));

    $response->assertRedirectToRoute('log.show', Log::Everyone);

    expect($user->hasStarred($commit))->toBeFalse();
});

it('requires authentication to star', function (): void {
    $commit = Commit::factory()->create();

    $this->post(route('commit-star.store', $commit))
        ->assertRedirectToRoute('home');

    expect($commit->stars()->count())->toBe(0);
});

it('requires authentication to unstar', function (): void {
    $commit = Commit::factory()->create();

    $this->delete(route('commit-star.destroy', $commit))
        ->assertRedirectToRoute('home');
});

it('shows the star count and whether the viewer starred the commit', function (): void {
    $user = User::factory()->create();
    $starred = Commit::factory()->create(['body' => 'Starred', 'created_at' => now()->subMinute()]);
    $plain = Commit::factory()->create(['body' => 'Plain']);

    $user->starredCommits()->attach($starred);
    User::factory()->create()->starredCommits()->attach($starred);

    $response = $this->actingAs($user)->get(route('log.show', Log::Everyone));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.body', 'Plain')
            ->where('commits.data.0.stars_count', 0)
            ->where('commits.data.0.starred', false)
            ->where('commits.data.1.body', 'Starred')
            ->where('commits.data.1.stars_count', 2)
            ->where('commits.data.1.starred', true));
});
