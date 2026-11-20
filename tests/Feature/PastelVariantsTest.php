<?php

declare(strict_types=1);

use App\Actions\CreateCommitPastel;
use App\Actions\CreateExport;
use App\Enums\PastelVariant;
use App\Models\Commit;
use App\Models\User;
use App\Services\PastelSeason;
use Illuminate\Support\Lottery;

beforeEach(function (): void {
    config(['pasteis.frosted_day' => '12-25', 'pasteis.frosted_chance' => 50]);
    Lottery::alwaysLose();
});

afterEach(function (): void {
    Lottery::determineResultsNormally();
});

it('gives one diamond per week independently of the daily quota and can still give originals to the same post', function (): void {
    $this->travelTo(now()->setDate(2026, 11, 15)->setTime(23, 59, 59));
    $sender = User::factory()->create(['pasteis_mode' => true]);
    $commit = Commit::factory()->create();
    $this->actingAs($sender)->postJson(route('commit-pastel.store', $commit), ['variant' => 'diamond'])
        ->assertOk()->assertJsonPath('pasteis_diamond_count', 1)->assertJsonPath('remaining', 10)
        ->assertJsonPath('diamond_remaining', 0)->assertJsonPath('diamond_resets_at', '2026-11-16T00:00:00Z');
    $this->postJson(route('commit-pastel.store', $commit), ['variant' => 'diamond'])->assertUnprocessable();
    for ($i = 0; $i < 10; $i++) {
        $this->postJson(route('commit-pastel.store', $commit))->assertOk();
    }

    $this->postJson(route('commit-pastel.store', $commit))->assertUnprocessable();
    expect($commit->refresh()->pasteis_count)->toBe(11)->and($commit->pasteis_diamond_count)->toBe(1);
    $this->travel(1)->seconds();
    $this->postJson(route('commit-pastel.store', $commit), ['variant' => 'diamond'])
        ->assertOk()->assertJsonPath('pasteis_diamond_count', 2)->assertJsonPath('remaining', 10);
});

it('never accumulates unused diamonds and checks a fresh locked sender rather than stale callers', function (): void {
    $sender = User::factory()->create(['pasteis_mode' => true, 'diamond_pastel_given_at' => now()->subWeeks(8)]);
    $stale = User::query()->findOrFail($sender->id);
    $action = resolve(CreateCommitPastel::class);
    $first = Commit::factory()->create();
    $second = Commit::factory()->create();
    expect($sender->diamondPasteisRemaining())->toBe(1)
        ->and($action->handle($sender, $first, PastelVariant::Diamond))->toBeTrue()
        ->and($action->handle($stale, $second, PastelVariant::Diamond))->toBeFalse()
        ->and($second->refresh()->pasteis_count)->toBe(0);
});

it('only awards Frosted pastéis on Christmas Day in UTC', function (string $date, bool $christmas): void {
    $this->travelTo(now()->parse($date, 'UTC'));
    Lottery::alwaysWin();
    $sender = User::factory()->create(['pasteis_mode' => true]);
    $commit = Commit::factory()->create();
    $this->actingAs($sender)->postJson(route('commit-pastel.store', $commit))
        ->assertOk()->assertJsonPath('pasteis_count', 1)->assertJsonPath('remaining', 9)
        ->assertJsonPath('pasteis_frosted_count', $christmas ? 1 : 0);
    expect($commit->user->refresh()->pasteis_frosted_received)->toBe($christmas ? 1 : 0);
})->with([
    ['2026-12-24T23:59:59Z', false],
    ['2026-12-25T00:00:00Z', true],
    ['2026-12-25T23:59:59Z', true],
    ['2026-12-26T00:00:00Z', false],
    ['2027-01-01T12:00:00Z', false],
    ['2027-07-01T12:00:00Z', false],
    ['2027-12-25T12:00:00Z', true],
]);

it('keeps an Original on a losing Christmas draw and respects the configured probability', function (): void {
    $this->travelTo(now()->setDate(2026, 12, 25));
    expect(resolve(PastelSeason::class)->variant())->toBe(PastelVariant::Original);
    Lottery::determineResultsNormally();
    config(['pasteis.frosted_chance' => 0]);
    expect(resolve(PastelSeason::class)->variant())->toBe(PastelVariant::Original);
    config(['pasteis.frosted_chance' => 100]);
    expect(resolve(PastelSeason::class)->variant())->toBe(PastelVariant::Frosted);
});

it('does not roll Christmas odds for a diamond and still offers it after the ordinary quota is exhausted', function (): void {
    $this->travelTo(now()->setDate(2026, 12, 25));
    Lottery::alwaysWin();
    $sender = User::factory()->create(['pasteis_mode' => true, 'pasteis_given_today' => 10, 'pasteis_given_on' => today()]);
    $commit = Commit::factory()->create();
    $this->actingAs($sender)->postJson(route('commit-pastel.store', $commit), ['variant' => 'diamond'])
        ->assertOk()->assertJsonPath('remaining', 0)->assertJsonPath('pasteis_diamond_count', 1)->assertJsonPath('pasteis_frosted_count', 0);
});

it('rejects forged variants without spending either quota', function (mixed $variant): void {
    $sender = User::factory()->create(['pasteis_mode' => true]);
    $commit = Commit::factory()->create();
    $this->actingAs($sender)->postJson(route('commit-pastel.store', $commit), ['variant' => $variant])
        ->assertUnprocessable()->assertJsonValidationErrors('variant');
    expect($sender->refresh()->pasteisRemaining())->toBe(10)->and($sender->diamondPasteisRemaining())->toBe(1)
        ->and($commit->refresh()->pasteis_count)->toBe(0);
})->with(['frosted', 'unknown', null, 42, 'array' => [['diamond']]]);

it('exposes collections only to opted-in readers and keeps the weekly timestamp private', function (): void {
    $reader = User::factory()->create(['pasteis_mode' => true]);
    $recipient = User::factory()->create(['pasteis_received' => 8, 'pasteis_diamond_received' => 2, 'pasteis_frosted_received' => 3]);
    $commit = Commit::factory()->for($recipient)->create(['pasteis_count' => 8, 'pasteis_diamond_count' => 2, 'pasteis_frosted_count' => 3]);
    $this->actingAs($reader)->get(route('user.show', $recipient))->assertInertia(fn ($page) => $page
        ->where('pasteisReceived', 8)->where('pasteisCollection.diamond', 2)->where('pasteisCollection.frosted', 3)
        ->missing('user.diamond_pastel_given_at'));
    $reader->update(['pasteis_mode' => false]);
    $this->get(route('user.show', $recipient))->assertInertia(fn ($page) => $page->where('pasteisCollection', null)->where('auth.pasteis', null));
    expect($commit->toArray())->not->toHaveKeys(['pasteis_diamond_count', 'pasteis_frosted_count']);
});

it('exports all new account and commit data', function (): void {
    $user = User::factory()->create(['pasteis_received' => 3, 'pasteis_diamond_received' => 1, 'pasteis_frosted_received' => 2, 'diamond_pastel_given_at' => now()]);
    Commit::factory()->for($user)->create(['pasteis_count' => 3, 'pasteis_diamond_count' => 1, 'pasteis_frosted_count' => 2]);
    $export = resolve(CreateExport::class)->handle($user);
    expect($export['profile']['pasteis_diamond_received'])->toBe(1)
        ->and($export['profile']['pasteis_frosted_received'])->toBe(2)
        ->and($export['profile']['diamond_pastel_given_at'])->toBe($user->diamond_pastel_given_at->toIso8601String());
    $commit = iterator_to_array($export['commits'])[0];
    expect($commit['pasteis_diamond_count'])->toBe(1)->and($commit['pasteis_frosted_count'])->toBe(2);
});
