<?php

declare(strict_types=1);

use App\Actions\CreateSearchSyncs;
use App\Actions\UpdateSearchIndex;
use App\Enums\SearchIndex;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    configureAlgolia();
});

it('sends the latest state of each changed user once, removes deleted ones, and forgets what it sent', function (): void {
    Http::fake(['testapp.algolia.net/1/indexes/testing_users/batch' => Http::response(['taskID' => 1])]);

    $user = User::factory()->create(['username' => 'nuno', 'name' => 'Nuno Maduro']);
    $gone = Str::uuid7()->toString();
    resolve(CreateSearchSyncs::class)->handle(SearchIndex::Users, [$user->id]);
    resolve(CreateSearchSyncs::class)->handle(SearchIndex::Users, [$user->id, $gone]);

    expect(resolve(UpdateSearchIndex::class)->handle(SearchIndex::Users))->toBe(3)
        ->and(pendingSearchSyncs())->toBe([]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['requests'] === [
        ['action' => 'updateObject', 'body' => ['objectID' => $user->id, 'username' => 'nuno', 'name' => 'Nuno Maduro']],
        ['action' => 'deleteObject', 'body' => ['objectID' => $gone]],
    ]);
});

it('keeps a change noted while a batch was being sent for the next run', function (): void {
    $user = User::factory()->create();
    resolve(CreateSearchSyncs::class)->handle(SearchIndex::Users, [$user->id]);

    Http::fake(['testapp.algolia.net/*' => function () use ($user) {
        resolve(CreateSearchSyncs::class)->handle(SearchIndex::Users, [$user->id]);

        return Http::response(['taskID' => 1]);
    }]);

    resolve(UpdateSearchIndex::class)->handle(SearchIndex::Users);

    expect(pendingSearchSyncs())->toBe([$user->id]);
});

it('keeps every change when Algolia fails, to send them again', function (): void {
    Http::fake(['testapp.algolia.net/*' => Http::response([], 503)]);

    $user = User::factory()->create();
    resolve(CreateSearchSyncs::class)->handle(SearchIndex::Users, [$user->id]);

    expect(fn () => resolve(UpdateSearchIndex::class)->handle(SearchIndex::Users))->toThrow(RequestException::class)
        ->and(pendingSearchSyncs())->toBe([$user->id]);
});

it('sends at most a batch per run, oldest first', function (): void {
    Http::fake(['testapp.algolia.net/*' => Http::response(['taskID' => 1])]);

    $ids = array_map(fn (): string => Str::uuid7()->toString(), range(1, UpdateSearchIndex::BATCH_SIZE + 1));
    resolve(CreateSearchSyncs::class)->handle(SearchIndex::Users, $ids);

    expect(resolve(UpdateSearchIndex::class)->handle(SearchIndex::Users))->toBe(UpdateSearchIndex::BATCH_SIZE)
        ->and(pendingSearchSyncs())->toBe([end($ids)]);
});

it('sends nothing with nothing waiting', function (): void {
    Http::fake();

    expect(resolve(UpdateSearchIndex::class)->handle(SearchIndex::Users))->toBe(0);

    Http::assertNothingSent();
});
