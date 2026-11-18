<?php

declare(strict_types=1);

use App\Actions\UpdateGuildMembership;
use App\Actions\UpdateGuildMute;
use App\Enums\Feature;
use App\Models\Commit;
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
            'icon_url' => route('guild-icon.show', ['guild' => 'rust', 'v' => $rust->updated_at->getTimestampMs()]),
            'member_count' => 2,
            'is_joined' => true,
            'is_muted' => false,
            'is_paused' => false,
            'html_url' => route('guild.show', 'rust'),
        ])
        ->assertJsonStructure(['data' => ['*' => ['icon_url']]])
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

it('requires a signed request', function (string $route, array $parameters): void {
    Guild::factory()->create(['handle' => 'rust']);

    $this->getJson(route($route, $parameters))->assertUnauthorized();
})->with([
    'directory' => ['api-guild.index', []],
    'board' => ['api-guild.show', ['guild' => 'rust']],
]);

it('shows a guild with its board, newest first, with reply previews', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust', 'name' => 'Rust']);
    resolve(UpdateGuildMembership::class)->handle($this->user, $rust, true);
    $tagged = Commit::factory()->create(['body' => 'Learning #RUST', 'created_at' => now()->subDay()]);
    $board = Commit::factory()->for($rust)->create(['body' => 'Straight to the board']);
    $reply = Commit::factory()->for($board, 'parent')->create();
    Commit::factory()->create(['body' => 'Learning #rustacean']);
    Commit::factory()->for(Guild::factory())->create();
    $muted = User::factory()->create();
    $this->user->muting()->attach($muted);
    Commit::factory()->for($muted)->create(['body' => 'Muted #rust']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'rust'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$board->id, $tagged->id])
        ->assertJsonPath('data.0.guild_handle', 'rust')
        ->assertJsonPath('data.0.reply_preview.data.0.id', $reply->id)
        ->assertJsonPath('guild.handle', 'rust')
        ->assertJsonPath('guild.member_count', 1)
        ->assertJsonPath('guild.is_joined', true)
        ->assertJsonPath('guild.is_muted', false)
        ->assertJsonStructure(['pinned_commit'])
        ->assertJsonPath('pinned_commit', null)
        ->assertJsonPath('can_moderate', false);
});

it('pages the board 30 roots at a time', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust']);
    $oldest = Commit::factory()->for($rust)->create(['created_at' => now()->subYear()]);
    Commit::factory()->for($rust)->count(30)->create();

    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'rust'))
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('meta.per_page', 30)
        ->assertJsonPath('links.next', route('api-guild.show', ['guild' => 'rust', 'page' => 2]));

    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', ['guild' => 'rust', 'page' => 2]))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$oldest->id])
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonStructure(['links' => ['next']])
        ->assertJsonPath('links.next', null);

    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', ['guild' => 'rust', 'page' => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('page');
});

it('keeps the number of reply previews when following the next page', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust']);
    $oldest = Commit::factory()->for($rust)->create(['created_at' => now()->subYear()]);
    Commit::factory()->for($oldest, 'parent')->count(3)->create();
    Commit::factory()->for($rust)->count(30)->create();

    $next = signedApiRequest($this->keyPair, 'GET', route('api-guild.show', ['guild' => 'rust', 'replies_per_page' => 3]))
        ->assertOk()
        ->json('links.next');

    expect($next)->toBe(route('api-guild.show', ['guild' => 'rust', 'replies_per_page' => 3, 'page' => 2]));
    signedApiRequest($this->keyPair, 'GET', $next)
        ->assertOk()
        ->assertJsonPath('data.*.id', [$oldest->id])
        ->assertJsonCount(3, 'data.0.reply_preview.data');
});

it('gives the moderator the board pin, which stays in its place on the board', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust', 'user_id' => $this->user->id]);
    $pinned = Commit::factory()->for($rust)->create(['created_at' => now()->subDay()]);
    $reply = Commit::factory()->for($pinned, 'parent')->create();
    $newer = Commit::factory()->for($rust)->create();
    $rust->update(['pinned_commit_id' => $pinned->id]);

    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'rust'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$newer->id, $pinned->id])
        ->assertJsonPath('pinned_commit.id', $pinned->id)
        ->assertJsonPath('pinned_commit.reply_preview.data.0.id', $reply->id)
        ->assertJsonPath('can_moderate', true);
});

it('still shows a paused guild and its board', function (): void {
    $go = Guild::factory()->create(['handle' => 'go', 'user_id' => null]);
    $post = Commit::factory()->for($go)->create();
    resolve(UpdateGuildMute::class)->handle($this->user, $go, true);

    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'go'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$post->id])
        ->assertJsonPath('guild.is_paused', true)
        ->assertJsonPath('guild.is_joined', false)
        ->assertJsonPath('guild.is_muted', true)
        ->assertJsonPath('can_moderate', false);
});

it('leaves out a pin the user cannot see', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust']);
    $muted = User::factory()->create();
    $this->user->muting()->attach($muted);
    $rust->update(['pinned_commit_id' => Commit::factory()->for($muted)->for($rust)->create()->id]);

    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'rust'))
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonStructure(['pinned_commit'])
        ->assertJsonPath('pinned_commit', null);
});

it('does not find a board for an unknown guild or while guilds are not available to the user', function (bool $adminOnly, string $handle): void {
    resolve(Features::class)->update(Feature::Guilds, true, $adminOnly);
    Guild::factory()->create(['handle' => 'rust']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', $handle))->assertNotFound();
})->with([
    'unknown guild' => [false, 'go'],
    'admin preview' => [true, 'rust'],
]);
