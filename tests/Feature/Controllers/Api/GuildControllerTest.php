<?php

declare(strict_types=1);

use App\Actions\CreateGuild;
use App\Actions\UpdateGuild;
use App\Actions\UpdateGuildMembership;
use App\Actions\UpdateGuildMute;
use App\Contracts\Services\ModerationProvider;
use App\Enums\Feature;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\GuildSubmission;
use App\Models\User;
use App\Models\UserTimeout;
use App\Queries\GuildsQuery;
use App\Services\Features;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

it('shows a nested guild mention instead of its untagged root', function (): void {
    Guild::factory()->create(['handle' => 'rust']);
    $root = Commit::factory()->create(['body' => 'An older conversation', 'created_at' => now()->subYear()]);
    $parent = Commit::factory()->for($root, 'parent')->create(['body' => 'An untagged reply']);
    $match = Commit::factory()->for($parent, 'parent')->create(['body' => 'Learning #rust']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'rust'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$match->id])
        ->assertJsonPath('data.0.body', 'Learning #rust')
        ->assertJsonPath('data.0.parent.id', $parent->id);
});

it('keeps a matching guild reply visible with only a tombstone for its deleted parent', function (): void {
    Guild::factory()->create(['handle' => 'rust']);
    $root = Commit::factory()->create();
    $parent = Commit::factory()->for($root, 'parent')->create(['body' => 'Deleted parent context']);
    $match = Commit::factory()->for($parent, 'parent')->create(['body' => 'A public #rust reply']);
    $parent->delete();

    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'rust'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$match->id])
        ->assertJsonPath('data.0.body', 'A public #rust reply')
        ->assertJsonPath('data.0.parent', [
            'id' => $parent->id,
            'parent_id' => $root->id,
            'deleted_at' => now()->toIso8601ZuluString(),
        ]);
});

it('pages the board 30 matching commits at a time', function (): void {
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

it('takes the moderator away together with the pause when the guild is paused during the request', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust', 'user_id' => $this->user->id]);
    $this->app->beforeResolving(GuildsQuery::class, fn () => $rust->update(['user_id' => null]));

    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'rust'))
        ->assertOk()
        ->assertJsonPath('guild.is_paused', true)
        ->assertJsonPath('can_moderate', false);
});

it('shows the pin the guild has when the snapshot starts', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust', 'user_id' => User::factory()->create()->id]);
    $old = Commit::factory()->for($rust)->create(['created_at' => now()->subDay()]);
    $new = Commit::factory()->for($rust)->create();
    $rust->update(['pinned_commit_id' => $old->id]);
    $this->app->beforeResolving(GuildsQuery::class, fn () => $rust->update(['pinned_commit_id' => $new->id]));

    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'rust'))
        ->assertOk()
        ->assertJsonPath('pinned_commit.id', $new->id);
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

it('creates a guild from its display name, with the user as its moderator and first member', function (): void {
    Storage::fake();

    signedApiRequestWithImages($this->keyPair, 'POST', route('api-guild.store'), [
        'name' => 'Jo bs', 'description' => 'Hiring and freelance work.', 'emoji' => '💼',
    ], [UploadedFile::fake()->image('icon.png', 80, 60)])
        ->assertCreated()
        ->assertJsonPath('data.handle', 'jobs')
        ->assertJsonPath('data.name', 'Jo bs')
        ->assertJsonPath('data.member_count', 1)
        ->assertJsonPath('data.is_joined', true)
        ->assertJsonPath('data.is_paused', false);

    $guild = Guild::query()->sole();
    expect($guild->user_id)->toBe($this->user->id)
        ->and($guild->icon_path)->toEndWith('.webp');
    Storage::assertExists($guild->icon_path);
    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'jobs'))->assertJsonPath('can_moderate', true);
});

it('claims a paused guild', function (): void {
    $go = Guild::factory()->create(['handle' => 'go', 'user_id' => null]);

    signedApiRequest($this->keyPair, 'POST', route('api-guild.store'), ['name' => 'Go', 'description' => 'Gophers.', 'emoji' => '🐹'])
        ->assertCreated()->assertJsonPath('data.handle', 'go')->assertJsonPath('data.is_paused', false);

    expect($go->fresh()->user_id)->toBe($this->user->id);
});

it('holds a flagged guild for review instead of creating it', function (): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->andReturn(['harassment' => 0.7]);
    $moderation->shouldReceive('isHarmful')->andReturn(false);

    signedApiRequest($this->keyPair, 'POST', route('api-guild.store'), ['name' => 'Jobs', 'description' => 'Flagged words.', 'emoji' => '💼'])
        ->assertAccepted()
        ->assertExactJson(['message' => 'Your guild is waiting for review. It goes live once an admin approves it.']);

    $this->assertDatabaseCount('guilds', 0);
    expect(GuildSubmission::query()->sole())->handle->toBe('jobs')->user_id->toBe($this->user->id);
});

it('rejects invalid guild details without creating a guild', function (array $payload, string $field): void {
    signedApiRequest($this->keyPair, 'POST', route('api-guild.store'), ['name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼', ...$payload])
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('guilds', 0);
})->with([
    'missing name' => [['name' => null], 'name'],
    'name without a hashtag' => [['name' => '!!!'], 'handle'],
    'unrelated name' => [['handle' => 'jobs', 'name' => 'Job Board'], 'name'],
]);

it('rejects an icon that is not an image', function (): void {
    signedApiRequestWithImages($this->keyPair, 'POST', route('api-guild.store'), ['name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼'], [
        UploadedFile::fake()->createWithContent('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
    ])->assertUnprocessable()->assertJsonValidationErrors('images.0');

    $this->assertDatabaseCount('guilds', 0);
});

it('refuses a taken hashtag, a guild the user was removed from, and more than a hundred guilds', function (string $case): void {
    match ($case) {
        'taken' => Guild::factory()->create(['handle' => 'jobs']),
        'removed' => DB::table('guild_moderator_removals')->insert(['github_id' => $this->user->github_id, 'handle' => 'jobs', 'created_at' => now()]),
        'limit' => Guild::factory()->count(100)->create(['user_id' => $this->user->id]),
    };

    signedApiRequest($this->keyPair, 'POST', route('api-guild.store'), ['name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼'])
        ->assertUnprocessable()->assertJsonValidationErrors('handle');

    expect(Guild::query()->where('handle', 'jobs')->where('user_id', $this->user->id)->exists())->toBeFalse();
})->with(['taken', 'removed', 'limit']);

it('refuses the claim when a removal wins the race after validation', function (): void {
    $this->app->beforeResolving(CreateGuild::class, function (): void {
        DB::table('guild_moderator_removals')->insert(['github_id' => $this->user->github_id, 'handle' => 'jobs', 'created_at' => now()]);
    });

    signedApiRequest($this->keyPair, 'POST', route('api-guild.store'), ['name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.handle', ['You cannot reclaim a guild you were removed from.']);

    $this->assertDatabaseCount('guilds', 0);
});

it('refuses the claim when another creation of the hashtag wins the race after validation', function (bool $flagged): void {
    if ($flagged) {
        $moderation = $this->mock(ModerationProvider::class);
        $moderation->shouldReceive('moderate')->andReturn(['harassment' => 0.7]);
        $moderation->shouldReceive('isHarmful')->andReturn(false);
    }

    $other = User::factory()->create();
    $this->app->beforeResolving(CreateGuild::class, function () use ($other): void {
        Guild::factory()->create(['handle' => 'jobs', 'user_id' => $other->id]);
    });

    signedApiRequest($this->keyPair, 'POST', route('api-guild.store'), ['name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.handle', ['A guild already uses this hashtag. Choose a different display name.']);

    expect(Guild::query()->sole()->user_id)->toBe($other->id);
    $this->assertDatabaseCount('guild_submissions', 0);
})->with(['published' => false, 'flagged' => true]);

it('does not create guilds while they are not available to the user', function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: true);

    signedApiRequest($this->keyPair, 'POST', route('api-guild.store'), ['name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼'])->assertNotFound();

    $this->assertDatabaseCount('guilds', 0);
});

it('requires a signed request to create a guild', function (): void {
    $this->postJson(route('api-guild.store'), ['name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼'])->assertUnauthorized();
});

it('refuses to create a guild while the user is in timeout', function (): void {
    UserTimeout::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, 'POST', route('api-guild.store'), ['name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼'])
        ->assertForbidden()->assertJsonPath('code', 'timed_out');

    $this->assertDatabaseCount('guilds', 0);
});

it('limits how often the user creates guilds', function (): void {
    foreach (range(1, 10) as $attempt) {
        signedApiRequest($this->keyPair, 'POST', route('api-guild.store'), ['name' => 'Jobs', 'description' => '', 'emoji' => '💼'])->assertUnprocessable();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-guild.store'), ['name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼'])->assertTooManyRequests();
});

it('edits the display name, description, emoji and icon of a guild the user moderates', function (): void {
    Storage::fake();
    $guild = Guild::factory()->create(['user_id' => $this->user->id, 'handle' => 'jobs', 'name' => 'Jobs', 'icon_path' => null]);

    signedApiRequestWithImages($this->keyPair, 'PUT', route('api-guild.update', 'jobs'), [
        'name' => 'J.O.B.S', 'description' => 'Remote jobs.', 'emoji' => '🌍', 'remove_icon' => false, 'handle' => 'changed',
    ], [UploadedFile::fake()->image('icon.png', 80, 60)])
        ->assertOk()
        ->assertJsonPath('data.handle', 'jobs')
        ->assertJsonPath('data.name', 'J.O.B.S')
        ->assertJsonPath('data.description', 'Remote jobs.')
        ->assertJsonPath('data.emoji', '🌍');

    $icon = $guild->fresh()->icon_path;
    expect($icon)->toEndWith('.webp')->and($guild->fresh()->user_id)->toBe($this->user->id);
    Storage::assertExists($icon);

    signedApiRequest($this->keyPair, 'PUT', route('api-guild.update', 'jobs'), ['name' => 'Jobs', 'description' => 'Remote jobs.', 'emoji' => '🌍', 'remove_icon' => true])
        ->assertOk()->assertJsonPath('data.icon_url', null);

    expect($guild->fresh()->icon_path)->toBeNull();
    Storage::assertMissing($icon);
});

it('holds a flagged edit for review and keeps the guild as it was', function (): void {
    $guild = Guild::factory()->create(['user_id' => $this->user->id, 'handle' => 'jobs', 'name' => 'Jobs', 'description' => 'Hiring.']);
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->andReturn(['harassment' => 0.7]);
    $moderation->shouldReceive('isHarmful')->andReturn(false);

    signedApiRequest($this->keyPair, 'PUT', route('api-guild.update', 'jobs'), ['name' => 'JOBS', 'description' => 'Flagged words.', 'emoji' => '💼', 'remove_icon' => false])
        ->assertAccepted()
        ->assertExactJson(['message' => 'Your changes are waiting for review. They go live once an admin approves them.']);

    expect($guild->fresh())->name->toBe('Jobs')->description->toBe('Hiring.')
        ->and(GuildSubmission::query()->sole())->name->toBe('JOBS')->user_id->toBe($this->user->id);
});

it('rejects invalid guild edits without changing the guild', function (array $payload, string $field): void {
    $guild = Guild::factory()->create(['user_id' => $this->user->id, 'handle' => 'jobs', 'name' => 'Jobs', 'description' => 'Hiring.']);

    signedApiRequest($this->keyPair, 'PUT', route('api-guild.update', 'jobs'), ['name' => 'Jobs', 'description' => 'Remote jobs.', 'emoji' => '💼', 'remove_icon' => false, ...$payload])
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($guild->fresh()->description)->toBe('Hiring.');
})->with([
    'name for another hashtag' => [['name' => 'Job Board'], 'name'],
    'missing description' => [['description' => ''], 'description'],
    'unknown emoji' => [['emoji' => 'invalid'], 'emoji'],
    'missing remove_icon' => [['remove_icon' => null], 'remove_icon'],
]);

it('does not edit a guild the user does not moderate or while guilds are not available to the user', function (bool $moderates, bool $adminOnly): void {
    $guild = Guild::factory()->create(['user_id' => $moderates ? $this->user->id : User::factory(), 'handle' => 'jobs', 'name' => 'Jobs', 'description' => 'Hiring.']);
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: $adminOnly);

    signedApiRequest($this->keyPair, 'PUT', route('api-guild.update', 'jobs'), ['name' => 'Jobs', 'description' => 'Remote jobs.', 'emoji' => '💼', 'remove_icon' => false])->assertNotFound();

    expect($guild->fresh()->description)->toBe('Hiring.');
})->with([
    'another moderator' => [false, false],
    'admin-only guilds' => [true, true],
]);

it('does not edit a guild when its moderator is removed after validation', function (): void {
    $guild = Guild::factory()->create(['user_id' => $this->user->id, 'handle' => 'jobs', 'name' => 'Jobs', 'description' => 'Hiring.']);
    $this->app->beforeResolving(UpdateGuild::class, function () use ($guild): void {
        Guild::query()->whereKey($guild->id)->update(['user_id' => null]);
    });

    signedApiRequest($this->keyPair, 'PUT', route('api-guild.update', 'jobs'), ['name' => 'Jobs', 'description' => 'Remote jobs.', 'emoji' => '💼', 'remove_icon' => false])->assertNotFound();

    expect($guild->fresh())->description->toBe('Hiring.')->user_id->toBeNull();
});

it('requires a signed request to edit a guild', function (): void {
    Guild::factory()->create(['user_id' => $this->user->id, 'handle' => 'jobs']);

    $this->putJson(route('api-guild.update', 'jobs'), ['name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼', 'remove_icon' => false])->assertUnauthorized();
});

it('refuses to edit a guild while the user is in timeout', function (): void {
    $guild = Guild::factory()->create(['user_id' => $this->user->id, 'handle' => 'jobs', 'name' => 'Jobs', 'description' => 'Hiring.']);
    UserTimeout::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, 'PUT', route('api-guild.update', 'jobs'), ['name' => 'Jobs', 'description' => 'Remote jobs.', 'emoji' => '💼', 'remove_icon' => false])
        ->assertForbidden()->assertJsonPath('code', 'timed_out');

    expect($guild->fresh()->description)->toBe('Hiring.');
});

it('counts guild edits and creations against the same limit', function (): void {
    Guild::factory()->create(['user_id' => $this->user->id, 'handle' => 'jobs', 'name' => 'Jobs']);

    foreach (range(1, 10) as $attempt) {
        signedApiRequest($this->keyPair, 'POST', route('api-guild.store'), ['name' => 'Go', 'description' => '', 'emoji' => '💼'])->assertUnprocessable();
    }

    signedApiRequest($this->keyPair, 'PUT', route('api-guild.update', 'jobs'), ['name' => 'Jobs', 'description' => 'Remote jobs.', 'emoji' => '💼', 'remove_icon' => false])->assertTooManyRequests();
});
