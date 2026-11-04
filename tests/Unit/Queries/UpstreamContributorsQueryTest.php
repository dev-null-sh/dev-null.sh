<?php

declare(strict_types=1);

use App\Models\User;
use App\Queries\UpstreamContributorsQuery;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * @param  list<int>  $ids
 * @return list<array{id: int, login: string, type: string}>
 */
function contributorsResponse(array $ids): array
{
    return array_map(fn (int $id): array => ['id' => $id, 'login' => "contributor-{$id}", 'type' => 'User'], $ids);
}

it('finds contributors by their github account id, not their username', function (): void {
    $contributor = User::factory()->create(['github_id' => '123', 'username' => 'renamed-since']);
    $other = User::factory()->create(['github_id' => '456']);

    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/contributors*' => Http::response(contributorsResponse([123, 789])),
    ]);

    $query = resolve(UpstreamContributorsQuery::class);

    expect($query->includes($contributor))->toBeTrue()
        ->and($query->includes($other))->toBeFalse();

    Http::assertSentCount(1);
});

it('reads every page of contributors and keeps them for an hour', function (): void {
    $lastPage = User::factory()->create(['github_id' => '1000']);

    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/contributors*' => Http::sequence()
            ->push(contributorsResponse(range(1, 100)))
            ->push(contributorsResponse([1000])),
    ]);

    $query = resolve(UpstreamContributorsQuery::class);

    expect($query->includes($lastPage))->toBeTrue();

    $this->travel(59)->minutes();

    expect($query->includes($lastPage))->toBeTrue();

    Http::assertSentCount(2);
});

it('keeps the last known contributors while github does not answer', function (): void {
    $contributor = User::factory()->create(['github_id' => '123']);

    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/contributors*' => Http::sequence()
            ->push(contributorsResponse([123]))
            ->push('Unavailable', 503)
            ->push(contributorsResponse([])),
    ]);

    $query = resolve(UpstreamContributorsQuery::class);

    expect($query->includes($contributor))->toBeTrue();

    $this->travel(61)->minutes();

    expect($query->includes($contributor))->toBeTrue();

    $this->travel(2)->minutes();

    expect($query->includes($contributor))->toBeFalse();

    Http::assertSentCount(3);
});

it('has nobody when github has never answered', function (): void {
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/contributors*' => Http::response('Unavailable', 503),
    ]);

    expect(resolve(UpstreamContributorsQuery::class)->includes(User::factory()->create()))->toBeFalse();
});

it('keeps the last known contributors without asking github while another request refreshes them', function (): void {
    $contributor = User::factory()->create(['github_id' => '123']);
    Cache::put('upstream-contributors:last', ['123']);
    Cache::lock('upstream-contributors:refresh', 30)->get();
    Http::fake();

    expect(resolve(UpstreamContributorsQuery::class)->includes($contributor))->toBeTrue();

    Http::assertNothingSent();
});

it('uses the list another request refreshed while it waited for its turn', function (): void {
    $contributor = User::factory()->create(['github_id' => '123']);
    Http::fake();

    // Another request finishes its refresh just after this one found the list missing.
    Event::listen(CacheMissed::class, function (CacheMissed $event): void {
        Cache::put($event->key, ['123'], 60);
    });

    expect(resolve(UpstreamContributorsQuery::class)->includes($contributor))->toBeTrue();

    Http::assertNothingSent();
});
