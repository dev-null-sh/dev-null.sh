<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('configures the users index and queues every user', function (): void {
    configureAlgolia();
    Http::fake(['testapp.algolia.net/1/indexes/testing_users/settings' => Http::response(['taskID' => 1])]);

    $users = User::factory()->count(2)->create();

    $this->artisan('search:import users')
        ->expectsOutputToContain('Queued 2 records for testing_users.')
        ->assertSuccessful();

    expect(pendingSearchSyncs())->toEqualCanonicalizing($users->modelKeys());

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request['searchableAttributes'] === ['username', 'name']);
});

it('rejects an unknown index', function (): void {
    configureAlgolia();
    Http::fake();

    $this->artisan('search:import commits')
        ->expectsOutputToContain('Unknown index. Try: users.')
        ->assertFailed();

    Http::assertNothingSent();
});

it('asks for the keys while Algolia is not set up', function (): void {
    Http::fake();

    $this->artisan('search:import users')
        ->expectsOutputToContain('Set ALGOLIA_APP_ID and ALGOLIA_WRITE_KEY first.')
        ->assertFailed();

    Http::assertNothingSent();
});
