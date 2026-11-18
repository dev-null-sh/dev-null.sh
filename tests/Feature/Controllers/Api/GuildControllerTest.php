<?php

declare(strict_types=1);

use App\Actions\UpdateGuildMembership;
use App\Actions\UpdateGuildMute;
use App\Enums\Feature;
use App\Models\Guild;
use App\Models\User;
use App\Services\Features;

beforeEach(function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);

    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('lists guilds by member count then handle, with whether the user joined or muted each one', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust', 'name' => 'Rust', 'description' => 'Fearless concurrency', 'emoji' => '🦀', 'icon_path' => 'guild-icons/rust.webp']);
    Guild::factory()->create(['handle' => 'go', 'user_id' => null]);
    $php = Guild::factory()->create(['handle' => 'php']);
    resolve(UpdateGuildMembership::class)->handle($this->user, $rust, true);
    resolve(UpdateGuildMembership::class)->handle(User::factory()->create(), $rust, true);
    resolve(UpdateGuildMembership::class)->handle(User::factory()->create(), $php, true);
    resolve(UpdateGuildMute::class)->handle($this->user, $php, true);

    signedApiRequest($this->keyPair, 'GET', route('api-guild.index'))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['rust', 'php', 'go'])
        ->assertJsonPath('data.0', [
            'handle' => 'rust',
            'name' => 'Rust',
            'description' => 'Fearless concurrency',
            'emoji' => '🦀',
            'icon_url' => $rust->apiIconUrl($this->user),
            'member_count' => 2,
            'is_joined' => true,
            'is_muted' => false,
            'is_paused' => false,
            'html_url' => route('guild.show', 'rust'),
        ])
        ->assertJsonPath('data.1.icon_url', null)
        ->assertJsonPath('data.1.is_joined', false)
        ->assertJsonPath('data.1.is_muted', true)
        ->assertJsonPath('data.2.member_count', 0)
        ->assertJsonPath('data.2.is_paused', true);
});

it('finds guilds by handle, name or description, ignoring case and a leading hashtag', function (string $query): void {
    Guild::factory()->create(['handle' => 'laravel', 'name' => 'Artisans', 'description' => 'Elegant web apps']);
    Guild::factory()->create(['handle' => 'symfony', 'name' => 'Symfony', 'description' => 'Components']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild.index', ['q' => $query]))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['laravel']);
})->with([
    'handle' => '#LARAVEL',
    'name' => 'artisans',
    'description' => 'elegant',
]);

it('pages the directory 24 guilds at a time and keeps the search in its links', function (): void {
    Guild::factory()->count(25)->sequence(fn ($sequence): array => ['handle' => sprintf('guild%02d', $sequence->index), 'name' => 'Guild'])->create();

    signedApiRequest($this->keyPair, 'GET', route('api-guild.index', ['q' => 'guild', 'page' => 2]))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['guild24'])
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 24)
        ->assertJsonPath('links.first', route('api-guild.index', ['q' => 'guild', 'page' => 1]));
});

it('hides the directory while guilds are not available to the user', function (bool $enabled, bool $adminOnly): void {
    resolve(Features::class)->update(Feature::Guilds, $enabled, $adminOnly);
    Guild::factory()->create();

    signedApiRequest($this->keyPair, 'GET', route('api-guild.index'))->assertNotFound();
})->with([
    'admin preview' => [true, true],
    'switched off' => [false, false],
]);

it('lists guilds for admins during the admin preview', function (): void {
    config(['app.admins' => [$this->user->handle]]);
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: true);
    Guild::factory()->create(['handle' => 'rust']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild.index'))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['rust']);
});

it('requires a signed request', function (): void {
    $this->getJson(route('api-guild.index'))->assertUnauthorized();
});
