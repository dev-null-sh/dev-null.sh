<?php

declare(strict_types=1);

use App\Actions\UpdateUserLastSeenAt;
use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));

    $this->user = User::factory()->create(['last_seen_at' => '2026-10-17', 'updated_at' => '2026-10-01 00:00:00']);
});

it('remembers the day a signed in user opened a page, without touching the profile', function (): void {
    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();

    expect($this->user->fresh())
        ->last_seen_at->toDateString()->toBe('2026-10-19')
        ->updated_at->toDateTimeString()->toBe('2026-10-01 00:00:00');
});

it('leaves the day alone when it is already today', function (): void {
    $this->user->update(['last_seen_at' => '2026-10-19']);
    $this->travelTo(now()->addHour());

    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();

    expect($this->user->fresh()->last_seen_at->toDateString())->toBe('2026-10-19');
});

it('does not count what a page requests on its own', function (array $headers): void {
    $this->actingAs($this->user)
        ->get(route('log.show', Log::Following), $headers);

    expect($this->user->fresh()->last_seen_at->toDateString())->toBe('2026-10-17');
})->with([
    'a partial reload' => [['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'log/show', 'X-Inertia-Partial-Data' => 'commits']],
    'a prefetch' => [['Purpose' => 'prefetch']],
    'a json request' => [['Accept' => 'application/json']],
]);

it('does not count requests that fail', function (): void {
    $this->actingAs($this->user)->get(route('operations.show'))->assertNotFound();

    expect($this->user->fresh()->last_seen_at->toDateString())->toBe('2026-10-17');
});

it('remembers the day a user used the api from the mobile app', function (): void {
    $keyPair = apiKeyPairFor($this->user);

    signedApiRequest($keyPair, 'GET', route('api-current-user.show'))->assertOk();

    expect($this->user->fresh()->last_seen_at->toDateString())->toBe('2026-10-19');
});

it('does not remember anything about guests', function (): void {
    $this->get(route('log.show', Log::Trending))->assertOk();

    expect(User::query()->whereNotNull('last_seen_at')->count())->toBe(1);
});

it('remembers the day a signed in user did something without opening a page', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('commit-star.store', Commit::factory()->create()))
        ->assertOk();

    expect($this->user->fresh()->last_seen_at->toDateString())->toBe('2026-10-19');
});

it('counts each person once in the active users of the day they are first seen', function (): void {
    $other = User::factory()->create();

    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();
    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();
    $this->actingAs($other)->get(route('log.show', Log::Following))->assertOk();

    expect(DB::table('active_user_counts')->get(['seen_at', 'count'])->map(fn (object $row): array => (array) $row)->all())
        ->toBe([['seen_at' => '2026-10-19', 'count' => 2]]);
});

it('remembers each day a person was seen, once a day', function (): void {
    $other = User::factory()->create();

    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();
    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();
    $this->travelTo(now()->addDay());
    $this->actingAs($this->user->fresh())->get(route('log.show', Log::Following))->assertOk();
    $this->actingAs($other)->get(route('log.show', Log::Following))->assertOk();

    $days = fn (User $user): array => DB::table('user_active_days')->where('user_id', $user->id)->oldest('seen_at')->pluck('seen_at')->all();

    expect($days($this->user))->toBe(['2026-10-19', '2026-10-20'])
        ->and($days($other))->toBe(['2026-10-20']);
});

it('does not count someone twice when another request saw them first', function (): void {
    $stale = User::query()->find($this->user->id);
    $this->user->update(['last_seen_at' => '2026-10-19']);

    resolve(UpdateUserLastSeenAt::class)->handle($stale);

    expect(DB::table('active_user_counts')->count())->toBe(0)
        ->and($stale->last_seen_at->toDateString())->toBe('2026-10-19');
});

it('reports a failure to remember the day without breaking the page', function (): void {
    Exceptions::fake();
    Schema::drop('active_user_counts');

    $this->actingAs($this->user)->get(route('log.show', Log::Following))->assertOk();

    Exceptions::assertReported(QueryException::class);
});
