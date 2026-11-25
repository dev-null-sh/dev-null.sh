<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Models\Guild;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('finds six guilds across the catalogue with handle prefixes before popular name matches', function (): void {
    $viewer = User::factory()->create(['handle' => 'admin']);
    Guild::factory()->count(30)->sequence(fn ($sequence): array => [
        'handle' => 'group'.mb_str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT),
        'name' => 'Rust developers',
    ])->create();
    $popular = Guild::query()->where('handle', 'group29')->firstOrFail();
    $popular->members()->attach($viewer, ['joined_at' => now()]);
    Guild::factory()->create(['handle' => 'rustaceans', 'name' => 'Systems']);

    $this->actingAs($viewer)->getJson(route('guild-suggestions.index', ['q' => 'RUST']))
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(6, 'guilds')->assertJsonPath('guilds.0.handle', 'rustaceans')
        ->assertJsonPath('guilds.1.handle', 'group29')->assertJsonPath('guilds.2.handle', 'group00')
        ->assertExactJson(['guilds' => collect(['rustaceans', 'group29', 'group00', 'group01', 'group02', 'group03'])
            ->map(fn (string $handle): array => ['handle' => $handle, 'name' => $handle === 'rustaceans' ? 'Systems' : 'Rust developers', 'emoji' => '💻', 'icon_url' => null])->all()]);
});

it('looks up exact requested handles without a directory limit or private guild fields', function (): void {
    $viewer = User::factory()->create(['handle' => 'admin']);
    Guild::factory()->count(25)->create();
    Guild::factory()->create(['handle' => 'rust', 'name' => 'Rust', 'icon_path' => 'guild-icons/icon.webp']);
    Guild::factory()->create(['handle' => 'rustlang', 'name' => 'Rust language']);

    $this->actingAs($viewer)->getJson(route('guild-suggestions.index', ['handles' => ['RUST', 'rust', 'missing']]))
        ->assertOk()->assertJsonCount(1, 'guilds')->assertJsonPath('guilds.0.handle', 'rust')
        ->assertJsonStructure(['guilds' => [['handle', 'name', 'emoji', 'icon_url']]])
        ->assertJsonMissingPath('guilds.0.icon_path')->assertJsonMissingPath('guilds.0.user_id')->assertJsonMissingPath('guilds.0.is_joined');
    $this->getJson(route('guild-suggestions.index', ['handles' => ['missing']]))->assertExactJson(['guilds' => []]);
});

it('matches literal wildcards and Unicode names when suggesting guilds', function (string $term, string $name): void {
    Guild::factory()->create(['handle' => 'match', 'name' => $name]);
    Guild::factory()->create(['handle' => 'other', 'name' => 'Other']);

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->getJson(route('guild-suggestions.index', ['q' => $term]))
        ->assertOk()->assertJsonCount(1, 'guilds')->assertJsonPath('guilds.0.handle', 'match');
})->with([['%', '100%'], ['_', 'Safe_code'], ['!', 'Hello!'], ['école', 'ÉCOLE'], ['κοσμος', 'ΚΟΣΜΟΣ']]);

it('protects suggestions behind sign in and guild feature access', function (): void {
    $this->getJson(route('guild-suggestions.index'))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson(route('guild-suggestions.index'))->assertNotFound();
    $admin = User::factory()->create(['handle' => 'admin']);
    $this->actingAs($admin)->put(route('feature.update', Feature::Guilds), ['enabled' => false])->assertRedirect();
    $this->getJson(route('guild-suggestions.index'))->assertNotFound();
});

it('validates and bounds guild lookup inputs', function (array $input, string $field): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->getJson(route('guild-suggestions.index', $input))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['q' => ['invalid']], 'q'],
    [['q' => str_repeat('x', 101)], 'q'],
    [['q' => 'rust', 'handles' => ['rust']], 'q'],
    [['handles' => 'rust'], 'handles'],
    [['handles' => array_fill(0, 101, 'rust')], 'handles'],
    [['handles' => ['invalid handle']], 'handles.0'],
    [['handles' => [str_repeat('a', 61)]], 'handles.0'],
]);
