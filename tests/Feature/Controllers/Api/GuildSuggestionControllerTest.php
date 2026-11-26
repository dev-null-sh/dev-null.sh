<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Models\Guild;
use App\Models\User;
use App\Services\Features;

beforeEach(function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);

    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('suggests six guilds with handle prefixes before popular name matches, as the web composer does', function (): void {
    Guild::factory()->count(7)->sequence(fn ($sequence): array => [
        'handle' => 'group0'.$sequence->index,
        'name' => 'Rust developers',
        'emoji' => '🦀',
        'icon_path' => null,
    ])->create();
    Guild::query()->where('handle', 'group06')->firstOrFail()->members()->attach($this->user, ['joined_at' => now()]);
    Guild::factory()->create(['handle' => 'rustaceans', 'name' => 'Systems', 'emoji' => '🦀', 'icon_path' => null]);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-suggestion.index', ['q' => 'RUST']))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['data' => collect(['rustaceans', 'group06', 'group00', 'group01', 'group02', 'group03'])
            ->map(fn (string $handle): array => [
                'handle' => $handle,
                'name' => $handle === 'rustaceans' ? 'Systems' : 'Rust developers',
                'emoji' => '🦀',
                'icon_url' => null,
            ])->all()]);
});

it('ignores a leading hashtag and surrounding spaces', function (string $query): void {
    Guild::factory()->create(['handle' => 'laravel', 'name' => 'Artisans']);
    Guild::factory()->create(['handle' => 'symfony', 'name' => 'Symfony']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-suggestion.index', ['q' => $query]))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['laravel']);
})->with(['#lara', ' #LARA ', '# lara', '##lara']);

it('matches display names as well as handles', function (): void {
    Guild::factory()->create(['handle' => 'laravel', 'name' => 'Artisans']);
    Guild::factory()->create(['handle' => 'symfony', 'name' => 'Symfony']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-suggestion.index', ['q' => 'ARTISANS']))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['laravel']);
});

it('suggests the six most popular guilds for a bare hashtag', function (?string $query): void {
    Guild::factory()->count(7)->sequence(fn ($sequence): array => ['handle' => 'guild'.$sequence->index])->create();
    $rust = Guild::factory()->create(['handle' => 'rust']);
    $rust->members()->attach($this->user, ['joined_at' => now()]);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-suggestion.index', array_filter(['q' => $query])))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['rust', 'guild0', 'guild1', 'guild2', 'guild3', 'guild4']);
})->with([null, '#']);

it('still suggests paused guilds and guilds the user muted, as the web composer does', function (): void {
    Guild::factory()->create(['handle' => 'rustpaused', 'user_id' => null]);
    Guild::factory()->create(['handle' => 'rustmuted'])->muters()->attach($this->user, ['muted_at' => now()]);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-suggestion.index', ['q' => 'rust']))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['rustmuted', 'rustpaused']);
});

it('gives icons the API signs for the user, without private guild fields', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust', 'name' => 'Rust', 'description' => 'Fearless', 'emoji' => '🦀', 'icon_path' => 'guild-icons/rust.webp']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-suggestion.index', ['q' => 'rust']))
        ->assertOk()
        ->assertExactJson(['data' => [[
            'handle' => 'rust',
            'name' => 'Rust',
            'emoji' => '🦀',
            'icon_url' => $rust->apiIconUrl($this->user),
        ]]]);
});

it('matches literal wildcards and Unicode names', function (string $term, string $name): void {
    Guild::factory()->create(['handle' => 'match', 'name' => $name]);
    Guild::factory()->create(['handle' => 'other', 'name' => 'Other']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-suggestion.index', ['q' => $term]))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['match']);
})->with([['%', '100%'], ['_', 'Safe_code'], ['!', 'Hello!'], ['école', 'ÉCOLE'], ['κοσμος', 'ΚΟΣΜΟΣ'], ['İstanbul', 'İstanbul'], ['istanbul', 'İSTANBUL']]);

it('matches a handle with a dotted capital I however it is typed', function (string $term): void {
    Guild::factory()->create(['handle' => mb_strtolower('İstanbul'), 'name' => 'City']);
    Guild::factory()->create(['handle' => 'other', 'name' => 'Other']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-suggestion.index', ['q' => $term]))
        ->assertOk()
        ->assertJsonPath('data.*.handle', [mb_strtolower('İstanbul')]);
})->with(['İstanbul', 'istanbul', 'İst']);

it('suggests no guilds while guilds are not available to the user', function (bool $enabled, bool $adminOnly): void {
    resolve(Features::class)->update(Feature::Guilds, $enabled, $adminOnly);
    Guild::factory()->create(['handle' => 'rust']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-suggestion.index', ['q' => 'rust']))->assertNotFound();
})->with([
    'admin preview' => [true, true],
    'switched off' => [false, false],
]);

it('suggests guilds to admins during the admin preview', function (): void {
    config(['app.admins' => [$this->user->handle]]);
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: true);
    Guild::factory()->create(['handle' => 'rust']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-suggestion.index', ['q' => 'rust']))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['rust']);
});

it('rejects an invalid search', function (mixed $query): void {
    signedApiRequest($this->keyPair, 'GET', route('api-guild-suggestion.index', ['q' => $query]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
})->with([
    'array' => [['rust']],
    'too long' => [str_repeat('x', 101)],
]);

it('shares the 120 people lookups a minute with the web', function (): void {
    foreach (range(1, 120) as $lookup) {
        $this->actingAs($this->user)->getJson(route('guild-suggestions.index', ['q' => 'rust']))->assertOk();
    }

    signedApiRequest($this->keyPair, 'GET', route('api-guild-suggestion.index', ['q' => 'rust']))
        ->assertTooManyRequests()
        ->assertExactJson(['message' => 'Too Many Attempts.', 'code' => 'rate_limited']);
});

it('requires a signed request', function (): void {
    $this->getJson(route('api-guild-suggestion.index', ['q' => 'rust']))->assertUnauthorized();
});
