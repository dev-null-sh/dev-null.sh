<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Models\Guild;
use App\Models\GuildReport;
use App\Models\User;
use App\Services\Features;

beforeEach(function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);

    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('reports a guild once, keeping the first details until an admin dismisses it', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust']);

    signedApiRequest($this->keyPair, 'POST', route('api-guild-report.store', 'rust'), ['details' => 'Promotes a scam.'])->assertNoContent();
    signedApiRequest($this->keyPair, 'POST', route('api-guild-report.store', 'rust'), ['details' => 'Again.'])->assertNoContent();

    $report = GuildReport::query()->sole();
    expect($report->guild_id)->toBe($rust->id)
        ->and($report->reporter_id)->toBe($this->user->id)
        ->and($report->details)->toBe('Promotes a scam.');

    $report->update(['dismissed_at' => now()]);

    signedApiRequest($this->keyPair, 'POST', route('api-guild-report.store', 'rust'), ['details' => 'A new issue.'])->assertNoContent();

    expect($report->fresh())->details->toBe('A new issue.')->dismissed_at->toBeNull();
});

it('reports a paused guild', function (): void {
    Guild::factory()->create(['handle' => 'go', 'user_id' => null]);

    signedApiRequest($this->keyPair, 'POST', route('api-guild-report.store', 'go'), ['details' => 'Spam.'])->assertNoContent();

    $this->assertDatabaseCount('guild_reports', 1);
});

it('lets admins report during the admin preview', function (): void {
    config(['app.admins' => [$this->user->handle]]);
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: true);
    Guild::factory()->create(['handle' => 'rust']);

    signedApiRequest($this->keyPair, 'POST', route('api-guild-report.store', 'rust'), ['details' => 'Spam.'])->assertNoContent();

    $this->assertDatabaseCount('guild_reports', 1);
});

it('does not find the guild for its moderator, an unknown guild or while guilds are not available to the user', function (bool $moderates, bool $adminOnly, string $handle): void {
    Guild::factory()->create(['handle' => 'rust', 'user_id' => $moderates ? $this->user->id : User::factory()]);
    resolve(Features::class)->update(Feature::Guilds, true, $adminOnly);

    signedApiRequest($this->keyPair, 'POST', route('api-guild-report.store', $handle), ['details' => 'Spam.'])->assertNotFound();

    $this->assertDatabaseCount('guild_reports', 0);
})->with([
    'moderator' => [true, false, 'rust'],
    'unknown guild' => [false, false, 'go'],
    'admin preview' => [false, true, 'rust'],
]);

it('requires details of at most 500 characters', function (mixed $details): void {
    Guild::factory()->create(['handle' => 'rust']);

    signedApiRequest($this->keyPair, 'POST', route('api-guild-report.store', 'rust'), ['details' => $details])
        ->assertUnprocessable()->assertJsonValidationErrors('details');
})->with(['missing' => [null], 'empty' => [''], 'too long' => [str_repeat('a', 501)]]);

it('requires a signed request', function (): void {
    Guild::factory()->create(['handle' => 'rust']);

    $this->postJson(route('api-guild-report.store', 'rust'), ['details' => 'Spam.'])->assertUnauthorized();
});

it('limits how often the user reports guilds', function (): void {
    Guild::factory()->create(['handle' => 'rust']);

    foreach (range(1, 10) as $attempt) {
        signedApiRequest($this->keyPair, 'POST', route('api-guild-report.store', 'rust'), ['details' => 'Spam.'])->assertNoContent();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-guild-report.store', 'rust'), ['details' => 'Spam.'])->assertTooManyRequests();
});
