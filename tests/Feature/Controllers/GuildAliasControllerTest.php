<?php

declare(strict_types=1);

use App\Actions\CreateGuild;
use App\Actions\CreateGuildAlias;
use App\Actions\CreateGuildSubmissionApproval;
use App\Contracts\Services\ModerationProvider;
use App\Enums\Feature;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\GuildAlias;
use App\Models\GuildSubmission;
use App\Models\User;
use App\Services\Features;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
});

it('lets the moderator add an alias and tells the people who see the guild', function (): void {
    $moderator = User::factory()->create();
    $member = User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'go']);
    $guild->members()->attach($member, ['joined_at' => now()]);
    Event::fake([UsersUpdated::class]);

    $this->actingAs($moderator)->post(route('guild-alias.store', 'go'), ['handle' => ' #GoLang '])
        ->assertSessionHasNoErrors()->assertRedirectToRoute('guild.show', 'go');

    $this->assertDatabaseHas('guild_aliases', ['guild_id' => $guild->id, 'handle' => 'golang']);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds
        && in_array($member->id, $event->userIds, true) && in_array($moderator->id, $event->userIds, true));

    $this->get(route('guild.show', 'go'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('guilds.0.handle', 'go')->where('guilds.0.aliases', ['golang']));
});

it('brings the commits of an alias to the guild and opens the guild by its alias', function (): void {
    $moderator = User::factory()->create();
    $reader = User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'go']);
    $tagged = Commit::factory()->create(['body' => 'Learning #go', 'created_at' => now()->subMinutes(2)]);
    $aliased = Commit::factory()->create(['body' => 'Learning #GoLang', 'created_at' => now()->subMinute()]);
    $other = Commit::factory()->create(['body' => 'Learning #golanguage']);

    $this->actingAs($reader)->get(route('guild.show', 'go'))->assertInertia(fn ($page) => $page
        ->has('commits', 1)->where('commits.0.id', $tagged->id));
    $this->get(route('guild.show', 'golang'))->assertNotFound();

    $this->actingAs($moderator)->post(route('guild-alias.store', 'go'), ['handle' => 'golang'])->assertSessionHasNoErrors();

    $this->actingAs($reader)->get(route('guild.show', 'go'))->assertInertia(fn ($page) => $page
        ->has('commits', 2)->where('commits.0.id', $aliased->id)->where('commits.1.id', $tagged->id));
    $this->get(route('guild.show', 'golang'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('handle', 'go')->has('commits', 2));

    $this->get(route('log.show', 'following'))->assertInertia(fn ($page) => $page->has('commits.data', 0));
    $this->put(route('guild-membership.update', $guild), ['joined' => true])->assertRedirect();
    $this->get(route('log.show', 'following'))->assertInertia(fn ($page) => $page
        ->has('commits.data', 2)->where('commits.data.0.id', $aliased->id)->where('commits.data.0.joined_guild', 'go'));

    $this->put(route('guild-mute.update', $guild), ['muted' => true])->assertRedirect();
    $this->get(route('log.show', 'following'))->assertInertia(fn ($page) => $page->has('commits.data', 0));
    $this->get(route('log.show', 'latest'))->assertInertia(fn ($page) => $page
        ->has('commits.data', 1)->where('commits.data.0.id', $other->id));
});

it('refuses an alias that is not a free hashtag', function (string $handle, string $message): void {
    $moderator = User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'go']);
    GuildAlias::factory()->for($guild)->create(['handle' => 'gopher']);
    Guild::factory()->create(['handle' => 'rust']);
    Guild::factory()->create(['handle' => 'paused', 'user_id' => null]);
    GuildAlias::factory()->create(['handle' => 'rustlang']);

    $this->actingAs($moderator)->postJson(route('guild-alias.store', 'go'), ['handle' => $handle])
        ->assertUnprocessable()->assertJsonValidationErrors(['handle' => $message]);

    $this->assertDatabaseCount('guild_aliases', 2);
})->with([
    'its own handle' => ['go', 'A guild already uses this hashtag.'],
    'another guild' => ['#Rust', 'A guild already uses this hashtag.'],
    'a paused guild' => ['paused', 'A guild already uses this hashtag.'],
    'an alias it already has' => ['gopher', 'A guild already uses this hashtag.'],
    'an alias of another guild' => ['rustlang', 'A guild already uses this hashtag.'],
    'not starting with a letter' => ['1337', 'Use a hashtag that starts with a letter and contains only letters, numbers and underscores.'],
    'punctuation' => ['go-lang', 'Use a hashtag that starts with a letter and contains only letters, numbers and underscores.'],
    'too long' => [str_repeat('g', 61), 'The handle field must not be greater than 60 characters.'],
    'empty' => ['#', 'The handle field is required.'],
]);

it('refuses an alias that is not text', function (): void {
    $moderator = User::factory()->create();
    Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'go']);

    $this->actingAs($moderator)->postJson(route('guild-alias.store', 'go'), ['handle' => ['golang']])
        ->assertUnprocessable()->assertJsonValidationErrors('handle');

    $this->assertDatabaseCount('guild_aliases', 0);
});

it('caps the aliases of a guild at ten', function (): void {
    $moderator = User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'go']);
    GuildAlias::factory()->for($guild)->count(9)->create();

    $this->actingAs($moderator)->post(route('guild-alias.store', 'go'), ['handle' => 'golang'])->assertSessionHasNoErrors();
    $this->postJson(route('guild-alias.store', 'go'), ['handle' => 'gopher'])
        ->assertUnprocessable()->assertJsonValidationErrors(['handle' => 'A guild can have up to 10 aliases.']);

    $this->assertDatabaseCount('guild_aliases', 10);
});

it('refuses an alias the moderation flags', function (array $scores, bool $harmful): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->once()->with('#mess', [])->andReturn($scores);
    $moderation->shouldReceive('isHarmful')->with('#mess')->andReturn($harmful);
    $moderator = User::factory()->create();
    Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'go']);

    $this->actingAs($moderator)->postJson(route('guild-alias.store', 'go'), ['handle' => 'mess'])
        ->assertUnprocessable()->assertJsonValidationErrors(['handle' => 'This hashtag cannot be used as an alias.']);

    $this->assertDatabaseCount('guild_aliases', 0);
})->with([
    'flagged' => [['harassment' => 0.9], false],
    'harmful' => [[], true],
]);

it('hides the aliases endpoints from everyone but the moderator', function (): void {
    $moderator = User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'go']);
    GuildAlias::factory()->for($guild)->create(['handle' => 'golang']);

    $this->actingAs(User::factory()->create())->post(route('guild-alias.store', 'go'), ['handle' => 'gopher'])->assertNotFound();
    $this->delete(route('guild-alias.destroy', ['go', 'golang']))->assertNotFound();

    resolve(Features::class)->update(Feature::Guilds, false);
    $this->actingAs($moderator)->post(route('guild-alias.store', 'go'), ['handle' => 'gopher'])->assertNotFound();
    $this->delete(route('guild-alias.destroy', ['go', 'golang']))->assertNotFound();

    expect(GuildAlias::query()->pluck('handle')->all())->toBe(['golang']);
});

it('leaves the alias out when it stops being allowed after validation', function (string $change): void {
    $moderator = User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'go']);
    Event::fake([UsersUpdated::class]);
    $changed = false;
    $this->app->beforeResolving(CreateGuildAlias::class, function () use (&$changed, $change, $guild): void {
        if (! $changed) {
            $changed = true;
            match ($change) {
                'guild' => Guild::factory()->create(['handle' => 'golang']),
                'alias' => GuildAlias::factory()->create(['handle' => 'golang']),
                'moderator' => Guild::query()->whereKey($guild->id)->update(['user_id' => null]),
                'limit' => GuildAlias::factory()->for($guild)->count(GuildAlias::MAX_PER_GUILD)->create(),
            };
        }
    });

    $this->actingAs($moderator)->post(route('guild-alias.store', 'go'), ['handle' => 'golang'])->assertRedirectToRoute('guild.show', 'go');

    expect($changed)->toBeTrue()->and($guild->aliases()->where('handle', 'golang')->exists())->toBeFalse();
    Event::assertNotDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds);
})->with(['guild', 'alias', 'moderator', 'limit']);

it('lets the moderator remove an alias, which takes its commits off the board', function (): void {
    $moderator = User::factory()->create();
    $member = User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'go']);
    $guild->members()->attach($member, ['joined_at' => now()]);
    GuildAlias::factory()->for($guild)->create(['handle' => 'golang']);
    GuildAlias::factory()->for($guild)->create(['handle' => 'gopher']);
    Commit::factory()->create(['body' => 'Learning #golang']);
    Event::fake([UsersUpdated::class]);

    $this->actingAs($moderator)->get(route('guild.show', 'go'))->assertInertia(fn ($page) => $page->has('commits', 1));
    $this->delete(route('guild-alias.destroy', ['go', 'golang']))->assertRedirectToRoute('guild.show', 'go');

    expect($guild->aliases()->pluck('handle')->all())->toBe(['gopher']);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds
        && in_array($member->id, $event->userIds, true) && in_array($moderator->id, $event->userIds, true));
    $this->get(route('guild.show', 'go'))->assertInertia(fn ($page) => $page->has('commits', 0)->where('guilds.0.aliases', ['gopher']));
    $this->get(route('guild.show', 'golang'))->assertNotFound();
});

it('does not remove the alias of another guild', function (): void {
    $moderator = User::factory()->create();
    Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'go']);
    GuildAlias::factory()->create(['handle' => 'rustlang']);

    $this->actingAs($moderator)->delete(route('guild-alias.destroy', ['go', 'rustlang']))->assertNotFound();

    $this->assertDatabaseCount('guild_aliases', 1);
});

it('refuses a new guild whose hashtag is an alias', function (): void {
    GuildAlias::factory()->create(['handle' => 'golang']);

    $this->actingAs(User::factory()->create())->postJson(route('guild.store'), ['name' => 'Go Lang', 'description' => 'Gophers.', 'emoji' => '💻'])
        ->assertUnprocessable()->assertJsonValidationErrors(['handle' => 'A guild already uses this hashtag. Choose a different display name.']);

    $this->assertDatabaseMissing('guilds', ['handle' => 'golang']);
});

it('refuses a new guild whose hashtag becomes an alias after validation', function (): void {
    $changed = false;
    $this->app->beforeResolving(CreateGuild::class, function () use (&$changed): void {
        if (! $changed) {
            $changed = true;
            GuildAlias::factory()->create(['handle' => 'golang']);
        }
    });

    $this->actingAs(User::factory()->create())->from(route('guild.index'))
        ->post(route('guild.store'), ['name' => 'Go Lang', 'description' => 'Gophers.', 'emoji' => '💻'])
        ->assertRedirectToRoute('guild.index')
        ->assertSessionHasErrors(['handle' => 'A guild already uses this hashtag. Choose a different display name.']);

    $this->assertDatabaseMissing('guilds', ['handle' => 'golang']);
});

it('discards an approved submission whose hashtag became an alias', function (): void {
    $submission = GuildSubmission::factory()->create(['handle' => 'golang']);
    GuildAlias::factory()->create(['handle' => 'golang']);

    resolve(CreateGuildSubmissionApproval::class)->handle($submission);

    $this->assertDatabaseCount('guild_submissions', 0);
    $this->assertDatabaseMissing('guilds', ['handle' => 'golang']);
});
