<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;

it('reads only the viewers follow and dismissal state for the loaded suggestions', function (): void {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $dismissed = User::factory()->create();
    $other = User::factory()->create();
    $viewer->following()->attach($followed);
    $viewer->dismissedSuggestions()->attach($dismissed);
    $other->following()->attach($dismissed);
    $other->dismissedSuggestions()->attach($followed);

    $this->actingAs($viewer)->getJson(route('suggested-user-state.index', [
        'ids' => [$followed->id, $dismissed->id, $other->id],
    ]))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertExactJson(['users' => [
        ['id' => $followed->id, 'is_following' => true, 'is_dismissed' => false],
        ['id' => $dismissed->id, 'is_following' => false, 'is_dismissed' => true],
        ['id' => $other->id, 'is_following' => false, 'is_dismissed' => false],
    ]]);
});

it('reads follows removed elsewhere without exposing another accounts state', function (): void {
    $viewer = User::factory()->create();
    $other = User::factory()->create();
    $suggested = User::factory()->create();
    $viewer->following()->attach($suggested);
    $url = route('suggested-user-state.index', ['ids' => [$suggested->id]]);

    $this->actingAs($viewer)->getJson($url)->assertJsonPath('users.0.is_following', true);
    $this->actingAs($other)->getJson($url)->assertJsonPath('users.0.is_following', false);
    $viewer->following()->detach($suggested);
    $this->actingAs($viewer)->getJson($url)->assertJsonPath('users.0.is_following', false);
});

it('requires authentication to read suggestion state', function (): void {
    $this->getJson(route('suggested-user-state.index', ['ids' => [(string) Str::uuid()]]))->assertUnauthorized();
});

it('validates a bounded unique list of suggestion ids', function (array $query): void {
    $this->actingAs(User::factory()->create())
        ->getJson(route('suggested-user-state.index', $query))->assertUnprocessable();
})->with([
    'missing' => [[]],
    'empty' => [['ids' => []]],
    'invalid' => [['ids' => ['not-a-uuid']]],
    'too many' => [fn (): array => ['ids' => array_map(fn (): string => (string) Str::uuid(), range(1, 101))]],
    'duplicate' => [fn (): array => ['ids' => array_fill(0, 2, (string) Str::uuid())]],
    'not a list' => [fn (): array => ['ids' => ['key' => (string) Str::uuid()]]],
]);
