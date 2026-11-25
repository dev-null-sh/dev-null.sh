<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Actions\CreateGuild;
use App\Actions\CreateGuildReport;
use App\Actions\CreateGuildSubmissionApproval;
use App\Actions\CreateScheduledCommit;
use App\Actions\DeleteGuildModerator;
use App\Actions\DeleteGuildSubmission;
use App\Actions\DeleteUser;
use App\Actions\UpdateGuild;
use App\Actions\UpdateScheduledCommitSubmittedAt;
use App\Contracts\Services\ModerationProvider;
use App\Enums\CommitStatus;
use App\Enums\Feature;
use App\Enums\Log;
use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Exceptions\GuildModeratorRemoved;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\GuildReport;
use App\Models\GuildSubmission;
use App\Models\Organization;
use App\Models\ScheduledCommit;
use App\Models\User;
use App\Queries\LogCommitsQuery;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config(['app.admins' => ['admin', 'other-admin']]);
});

it('keeps bulletin board text unchanged and preserves the guild when scheduling', function (string $body): void {
    $user = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['handle' => 'rust']);
    $payload = ['body' => $body, 'guild_handle' => 'rust'];

    $preview = $this->actingAs($user)->postJson(route('commit-preview.store'), $payload)->assertOk();
    $this->post(route('commit.store'), $payload)->assertSessionHasNoErrors()->assertInertiaFlash('committed.guild_handle', 'rust');
    expect($user->commits()->sole())->body->toBe($body)->guild_id->toBe($guild->id);
    expect($guild->commits()->sole()->guild->id)->toBe($guild->id);
    $preview->assertJsonPath('segments', $user->commits()->sole()->toArray()['body_segments']);

    $this->post(route('scheduled-commit.store'), [...$payload, 'scheduled_at' => now()->addHour()->toIso8601String()])->assertSessionHasNoErrors();
    $scheduled = ScheduledCommit::query()->sole();
    expect($scheduled)->body->toBe($body)->guild_id->toBe($guild->id);
    resolve(UpdateScheduledCommitSubmittedAt::class)->handle($scheduled->id, early: true);
    expect($scheduled->fresh()->commit)->body->toBe($body)->guild_id->toBe($guild->id);
})->with([
    'full budget' => [str_repeat('x', 4000)],
    'already present' => ['Learning #RUST!'],
    'only inside code or a link' => ['`#rust` https://example.com/#rust'],
]);

it('keeps board posts on their guild counts them but leaves them off profile and general logs', function (): void {
    $author = User::factory()->create(['handle' => 'admin']);
    $viewer = User::factory()->create(['handle' => 'other-admin']);
    $guild = Guild::factory()->create(['handle' => 'rust']);
    Guild::factory()->create(['handle' => 'jobs']);
    $regular = Commit::factory()->for($author)->create(['body' => 'Learning #rust']);
    $this->actingAs($author)->post(route('commit.store'), ['body' => 'A board post with #jobs', 'guild_handle' => 'rust'])->assertSessionHasNoErrors();
    $board = $author->commits()->whereNotNull('guild_id')->sole();
    $author->forceFill(['pinned_commit_id' => $board->id])->save();

    $this->get(route('guild.show', 'rust'))->assertInertia(fn ($page) => $page->has('commits', 2)->where('commits.0.id', $board->id));
    $this->get(route('guild.show', 'jobs'))->assertInertia(fn ($page) => $page->has('commits', 0));
    $this->get(route('user.show', $author))->assertInertia(fn ($page) => $page
        ->where('user.commits_count', 2)->where('pinnedCommit', null)->has('commits.data', 1)->where('commits.data.0.id', $regular->id));
    $this->get(route('log.show', 'following'))->assertInertia(fn ($page) => $page->has('commits.data', 1));
    $viewer->following()->attach($author);
    $this->actingAs($viewer)->get(route('log.show', 'following'))->assertInertia(fn ($page) => $page->has('commits.data', 1));
    $this->put(route('guild-membership.update', $guild), ['joined' => true])->assertRedirect();
    $this->get(route('log.show', 'following'))->assertInertia(fn ($page) => $page
        ->has('commits.data', 2)->where('commits.data.0.id', $board->id)->where('commits.data.0.joined_guild', 'rust'));
    foreach (['latest', 'trending'] as $log) {
        $this->get(route('log.show', $log))->assertInertia(fn ($page) => $page->has('commits.data', 1)->where('commits.data.0.id', $regular->id));
    }

    $this->put(route('guild-mute.update', $guild), ['muted' => true])->assertRedirect();
    $this->get(route('log.show', 'following'))->assertInertia(fn ($page) => $page->has('commits.data', 0));
    $this->get(route('commit.show', [$author, $board]))->assertOk();
});

it('does not let bulletin board context bypass validation', function (string $route, array $payload, bool $admin, string $field): void {
    Guild::factory()->create(['handle' => 'rust']);
    $this->actingAs(User::factory()->create(['handle' => $admin ? 'admin' : 'member']))
        ->postJson(route($route), ['body' => 'Hello', 'guild_handle' => 'rust', 'scheduled_at' => now()->addHour()->toIso8601String(), ...$payload])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('commits', 0);
    $this->assertDatabaseCount('scheduled_commits', 0);
})->with(['commit.store', 'commit-preview.store', 'scheduled-commit.store'])->with([
    'over budget' => [['body' => str_repeat('x', 4001)], true, 'body'],
    'empty text' => [['body' => ''], true, 'body'],
    'missing guild' => [['guild_handle' => 'missing'], true, 'guild_handle'],
    'invalid context' => [['guild_handle' => ['rust']], true, 'guild_handle'],
    'not available' => [[], false, 'guild_handle'],
]);

it('creates a public guild and gives only its creator moderation controls', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    Event::fake([UsersUpdated::class]);

    $this->actingAs($admin)->post(route('guild.store'), [
        'handle' => ' JoBs ', 'name' => 'Jo bs', 'description' => 'Hiring and freelance work.', 'emoji' => '💼',
        'user_id' => User::factory()->create()->id,
    ])->assertRedirectToRoute('guild.show', 'jobs');

    $guild = Guild::query()->sole();
    expect($guild->handle)->toBe('jobs')->and($guild->user_id)->toBe($admin->id);
    $this->assertDatabaseHas('guild_preferences', ['guild_id' => $guild->id, 'user_id' => $admin->id, 'joined_at' => now()]);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds && in_array($admin->id, $event->userIds, true));

    $this->get(route('guild.show', 'jobs'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('guild/show')->where('canModerate', true)->where('guild.name', 'Jo bs')
        ->where('guild.icon_url', null)->missing('guild.user_id')->missing('guild.icon_path')->missing('guilds'));
    $this->actingAs(User::factory()->create(['handle' => 'other-admin']))
        ->get(route('guild.show', 'jobs'))->assertOk()->assertInertia(fn ($page) => $page->where('canModerate', false));
});

it('hides guild data and every guild endpoint from ordinary members', function (): void {
    $user = User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get('/search?q=%23jobs')->assertOk()->assertInertia(fn ($page) => $page->where('guildsEnabled', false)->missing('guilds'));
    $this->get(route('guild.show', $guild->handle))->assertNotFound();
    $this->get(route('guild-icon.show', $guild->handle))->assertNotFound();
    $this->post(route('guild.store'), [])->assertNotFound();
    $this->put(route('guild.update', $guild->handle), [])->assertNotFound();
    $this->assertDatabaseCount('guilds', 1);
});

it('creates a guild from its display name without an existing hashtag', function (string $name, string $handle): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $this->actingAs($admin)->post(route('guild.store'), [
        'name' => $name, 'description' => 'A new public group.', 'emoji' => '💻',
    ])->assertRedirectToRoute('guild.show', $handle);

    $this->assertDatabaseHas('guilds', ['handle' => $handle, 'name' => $name, 'user_id' => $admin->id]);
    $this->get(route('guild.show', $handle))->assertInertia(fn ($page) => $page->has('commits', 0));
    $this->postJson(route('guild.store'), [
        'name' => $name, 'description' => 'Another group.', 'emoji' => '💻',
    ])->assertUnprocessable()->assertJsonValidationErrors('handle');
    $this->assertDatabaseCount('guilds', 1);
})->with([
    ['Laravel 4ever', 'laravel4ever'],
    ['/dev/null', 'devnull'],
    ['☕ CAFÉ_Dev 2!', 'café_dev2'],
    [str_repeat('a', 60), str_repeat('a', 60)],
]);

it('rejects names that cannot produce a hashtag without an explicit handle', function (mixed $name): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))->postJson(route('guild.store'), [
        'name' => $name, 'description' => 'A public group.', 'emoji' => '💻',
    ])->assertUnprocessable()->assertJsonValidationErrors('handle');
    $this->assertDatabaseCount('guilds', 0);
})->with([null, [['invalid']], '!!!', '123 Developers']);

it('requires sign in and exposes no guild data to guests', function (): void {
    $guild = Guild::factory()->create();
    $this->get('/')->assertInertia(fn ($page) => $page->where('guildsEnabled', false)->missing('guilds'));
    $this->get(route('guild.show', $guild->handle))->assertRedirectToRoute('home');
    $this->getJson(route('guild-icon.show', $guild->handle))->assertUnauthorized();
    $this->postJson(route('guild.store'), [])->assertUnauthorized();
    $this->putJson(route('guild.update', $guild->handle), [])->assertUnauthorized();
});

it('switches off the entire preview even for its admin creator', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['user_id' => $admin->id]);
    $this->actingAs($admin)->put(route('feature.update', Feature::Guilds), ['enabled' => false])->assertRedirect();

    $this->actingAs($admin)->get('/search?q=%23jobs')->assertInertia(fn ($page) => $page->where('guildsEnabled', false)->missing('guilds'));
    $this->get(route('guild.show', $guild->handle))->assertNotFound();
    $this->get(route('guild-icon.show', $guild->handle))->assertNotFound();
    $this->post(route('guild.store'), [])->assertNotFound();
    $this->put(route('guild.update', $guild->handle), [])->assertNotFound();
});

it('rejects invalid guild details without creating a guild', function (array $payload, string $field): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))->postJson(route('guild.store'), [
        'handle' => 'jobs', 'name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼', ...$payload,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('guilds', 0);
})->with([
    'missing handle' => [['handle' => null], 'handle'],
    'hashtag prefix' => [['handle' => '#jobs'], 'handle'],
    'path' => [['handle' => '../jobs'], 'handle'],
    'leading number' => [['handle' => '123jobs'], 'handle'],
    'long handle' => [['handle' => str_repeat('a', 61)], 'handle'],
    'empty name' => [['name' => ''], 'name'],
    'long name' => [['name' => str_repeat('a', 61)], 'name'],
    'unrelated name' => [['name' => 'Job Board'], 'name'],
    'punctuation does not hide extra words' => [['name' => 'Job-s Board'], 'name'],
    'numbers remain significant' => [['name' => 'Jobs 2!'], 'name'],
    'accents remain significant' => [['name' => 'Jóbs!'], 'name'],
    'underscores remain significant' => [['handle' => 'jobs_dev', 'name' => 'Jobs/Dev'], 'name'],
    'empty description' => [['description' => ''], 'description'],
    'long description' => [['description' => str_repeat('a', 501)], 'description'],
    'not an emoji' => [['emoji' => '<script>'], 'emoji'],
    'svg' => [fn (): array => ['images' => [UploadedFile::fake()->createWithContent('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')]], 'images.0'],
]);

it('never transfers a claimed handle even when two creations pass validation', function (): void {
    $guild = Guild::factory()->create(['handle' => 'jobs']);
    $admin = User::factory()->create(['handle' => 'admin']);
    $this->actingAs($admin)->postJson(route('guild.store'), [
        'handle' => 'JOBS', 'name' => 'Other board', 'description' => 'Other.', 'emoji' => '💻',
    ])->assertUnprocessable()->assertJsonValidationErrors('handle');

    $duplicate = resolve(CreateGuild::class)->handle($admin, 'JOBS', 'Other board', 'Other.', '💻', null);

    expect($duplicate->id)->toBe($guild->id)->and($duplicate->user_id)->toBe($guild->user_id)->and($duplicate->name)->toBe($guild->name);
    $this->assertDatabaseCount('guilds', 1);
});

it('lets only the creator edit the display name icon and description and never the handle or owner', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $other = User::factory()->create(['handle' => 'other-admin']);
    $guild = Guild::factory()->create(['user_id' => $admin->id, 'name' => 'jobs', 'handle' => 'jobs']);
    $payload = ['description' => 'Remote jobs.', 'emoji' => '🌍', 'remove_icon' => false, 'name' => 'J.O.B.S', 'handle' => 'changed', 'user_id' => $other->id];

    $this->actingAs($other)->put(route('guild.update', 'jobs'), $payload)->assertNotFound();
    expect($guild->fresh()->description)->toBe($guild->description);
    $this->actingAs($admin)->put(route('guild.update', 'jobs'), $payload)->assertRedirectToRoute('guild.show', 'jobs');
    expect($guild->fresh())->description->toBe('Remote jobs.')->emoji->toBe('🌍')->name->toBe('J.O.B.S')->handle->toBe('jobs')->user_id->toBe($admin->id);
    $this->putJson(route('guild.update', 'jobs'), [...$payload, 'name' => 'Job Board'])->assertUnprocessable()->assertJsonValidationErrors(['name' => "Guild display name doesn't match hashtag"]);
    expect($guild->fresh()->name)->toBe('J.O.B.S');
    $this->putJson(route('guild.update', 'jobs'), ['description' => '', 'emoji' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'description', 'emoji', 'remove_icon']);
});

it('reencodes uploaded icons serves them only to admins and replaces or removes them', function (): void {
    Storage::fake();
    $admin = User::factory()->create(['handle' => 'admin']);
    $this->actingAs($admin)->post(route('guild.store'), [
        'handle' => 'jobs', 'name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼',
        'images' => [UploadedFile::fake()->image('original.png', 80, 60)],
    ])->assertRedirectToRoute('guild.show', 'jobs');
    $guild = Guild::query()->sole();
    $original = $guild->icon_path;
    expect($original)->toEndWith('.webp')->not->toContain('original');
    $this->get(route('guild-icon.show', 'jobs'))->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->get(route('guild.show', 'jobs'))->assertInertia(fn ($page) => $page->where('guild.icon_url', route('guild-icon.show', ['guild' => 'jobs', 'v' => $guild->updated_at->getTimestampMs()])));

    $this->put(route('guild.update', 'jobs'), ['name' => 'Jobs', 'description' => 'Hiring again.', 'emoji' => '💼', 'remove_icon' => false, 'images' => [UploadedFile::fake()->image('next.jpg')]])->assertRedirect();
    expect($guild->fresh()->icon_path)->not->toBe($original);
    $this->put(route('guild.update', 'jobs'), ['name' => 'Jobs', 'description' => 'Hiring again.', 'emoji' => '💻', 'remove_icon' => true])->assertRedirect();
    expect($guild->fresh()->icon_path)->toBeNull();
    $this->get(route('guild-icon.show', 'jobs'))->assertNotFound();
});

it('preserves a guild when its moderator loses ownership after edit validation', function (bool $reclaimed): void {
    Storage::fake();
    $moderator = User::factory()->create(['handle' => 'admin']);
    $replacement = $reclaimed ? User::factory()->create() : null;
    $guild = Guild::factory()->create(['user_id' => $moderator->id, 'icon_path' => 'guild-icons/original.webp']);
    Storage::put($guild->icon_path, 'original');
    $this->app->beforeResolving(UpdateGuild::class, function () use ($guild, $replacement): void {
        Guild::query()->whereKey($guild->id)->update(['user_id' => $replacement?->id]);
    });

    $this->actingAs($moderator)->put(route('guild.update', $guild->handle), [
        'name' => $guild->handle, 'description' => 'A stale edit.', 'emoji' => '🌍', 'remove_icon' => true,
    ])->assertSessionHasErrors(['description' => 'You no longer moderate this guild.']);

    expect($guild->fresh())->description->toBe($guild->description)->emoji->toBe($guild->emoji)
        ->icon_path->toBe('guild-icons/original.webp')->user_id->toBe($replacement?->id);
    Storage::assertExists('guild-icons/original.webp');
})->with([false, true]);

it('shows exact prose hashtags including older commits but excludes prefixes code urls and hidden authors', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['handle' => 'jobs']);
    $old = Commit::factory()->create(['body' => 'Hiring #JoBs and #rust', 'created_at' => now()->subYear()]);
    $new = Commit::factory()->create(['body' => 'Contract #jobs!']);
    foreach (['#jobsboard', '#jobs_uk', 'not#jobs', '`#jobs`', "```text\n#jobs\n```", 'https://example.com?q=#jobs', 'example.com?q=#jobs'] as $body) {
        Commit::factory()->create(['body' => $body]);
    }

    Commit::factory()->pending()->create(['body' => 'Not approved #jobs']);
    Commit::factory()->create(['body' => 'Deleted #jobs'])->delete();
    $muted = User::factory()->create();
    $blocked = User::factory()->create();
    $admin->muting()->attach($muted);
    $blocked->blocking()->attach($admin);
    Commit::factory()->for($muted)->create(['body' => 'Muted #jobs']);
    Commit::factory()->for($blocked)->create(['body' => 'Blocked #jobs']);

    $this->actingAs($admin)->get(route('guild.show', $guild->handle))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits', 2)->where('commits.0.id', $new->id)->where('commits.1.id', $old->id)->where('hasMore', false));
});

it('includes hex-shaped hashtags on their guild board and excludes explicit swatches', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['handle' => 'dead']);
    $tagged = Commit::factory()->create(['body' => 'Join color(#dead)#DEAD']);
    Commit::factory()->create(['body' => 'Try (COLOR(#dead)).']);

    $this->actingAs($admin)->get(route('guild.show', $guild->handle))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits', 1)->where('commits.0.id', $tagged->id));
});

it('merges board posts and exact hashtags newest first with thirty matching commits per page', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['handle' => 'café_dev']);
    $old = Commit::factory()->create(['body' => 'An old regular commit', 'created_at' => now()->subYear()]);
    $tagged = Commit::factory()->count(15)->create(['body' => '#CAFÉ_DEV', 'created_at' => now()->subDay()]);
    $board = Commit::factory()->for($guild)->count(15)->create(['body' => 'Direct to the board']);
    Commit::factory()->count(122)->create(['body' => '`#café_dev` #café_developers']);
    Commit::factory()->for(Guild::factory())->create(['body' => 'Another board #café_dev']);
    $reply = Commit::factory()->for($old, 'parent')->create(['body' => '#café_dev', 'created_at' => now()->subMinute()]);
    $nested = Commit::factory()->for($reply, 'parent')->create(['body' => 'A nested #café_dev reply']);
    $boardReply = Commit::factory()->for($board->last(), 'parent')->create(['body' => 'An ordinary reply']);
    $this->actingAs($admin)->get(route('guild.show', 'café_dev'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits', 30)->where('hasMore', true)->where('page', 1)
        ->where('commits.0.id', $nested->id)->where('commits.0.parent.id', $reply->id)
        ->where('commits.1.id', $board->last()->id)->where('commits.16.id', $reply->id)
        ->where('commits.17.id', $tagged->last()->id)
        ->has('commits.1.reply_preview.data', 1)->where('commits.1.reply_preview.data.0.id', $boardReply->id));
    $this->get(route('guild.show', ['guild' => 'café_dev', 'page' => 2]))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits', 2)->where('commits.0.id', $tagged[1]->id)->where('commits.1.id', $tagged[0]->id)
        ->where('hasMore', false)->where('page', 2));
    $this->getJson(route('guild.show', ['guild' => 'café_dev', 'page' => 0]))->assertUnprocessable()->assertJsonValidationErrors('page');
});

it('reads the roots of a guild page once however many commits carry a hashtag', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['handle' => 'rust']);
    $post = Commit::factory()->for($guild)->create(['body' => 'Direct to the board']);
    Commit::factory()->count(250)->create(['body' => 'Reading #php today']);
    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin)->get(route('guild.show', 'rust'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits', 1)->where('commits.0.id', $post->id)->where('hasMore', false));

    expect(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '"roots"')))->toHaveCount(1);

    DB::disableQueryLog();
});

it('reads the roots of a guild page one more time for its pinned commit', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['handle' => 'rust']);
    $post = Commit::factory()->for($guild)->create(['body' => 'Direct to the board']);
    DB::table('guilds')->where('id', $guild->id)->update(['pinned_commit_id' => $post->id]);
    Commit::factory()->count(250)->create(['body' => 'Reading #php today']);
    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin)->get(route('guild.show', 'rust'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('pinnedCommit.id', $post->id));

    expect(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '"roots"')))->toHaveCount(2);

    DB::disableQueryLog();
});

it('shows nested guild mentions directly without exposing hidden roots or replies', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    Guild::factory()->create(['handle' => 'rust']);
    $root = Commit::factory()->create(['body' => 'A regular thread']);
    $parent = Commit::factory()->for($root, 'parent')->create(['body' => 'An untagged reply']);
    $match = Commit::factory()->for($parent, 'parent')->create(['body' => 'A nested #rust reply']);
    $pending = Commit::factory()->pending()->create();
    Commit::factory()->for($pending, 'parent')->create(['body' => '#rust']);
    $muted = User::factory()->create();
    $admin->muting()->attach($muted);
    $mutedRoot = Commit::factory()->for($muted)->create();
    Commit::factory()->for($mutedRoot, 'parent')->create(['body' => '#rust']);
    $unmatched = Commit::factory()->create();
    Commit::factory()->for($unmatched, 'parent')->pending()->create(['body' => '#rust']);
    Commit::factory()->for($muted)->for($unmatched, 'parent')->create(['body' => '#rust']);

    $this->actingAs($admin)->get(route('guild.show', 'rust'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits', 1)->where('commits.0.id', $match->id)->where('hasMore', false)
        ->where('commits.0.parent.id', $parent->id)->where('commits.0.body', 'A nested #rust reply'));
    $this->getJson(route('commit-reply.index', $parent))->assertOk()->assertJsonPath('data.0.id', $match->id);
});

it('keeps a matching guild reply visible with only a tombstone for its deleted parent', function (): void {
    $viewer = User::factory()->create(['handle' => 'admin']);
    Guild::factory()->create(['handle' => 'rust']);
    $root = Commit::factory()->create();
    $parent = Commit::factory()->for($root, 'parent')->create(['body' => 'Deleted parent context']);
    $match = Commit::factory()->for($parent, 'parent')->create(['body' => 'A public #rust reply']);
    $parent->delete();

    $this->actingAs($viewer)->get(route('guild.show', 'rust'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits', 1)->where('commits.0.id', $match->id)->where('commits.0.body', 'A public #rust reply')
        ->where('commits.0.parent', [
            'id' => $parent->id,
            'parent_id' => $root->id,
            'deleted_at' => now()->startOfSecond()->toJSON(),
        ]));
});

it('keeps an unavailable parent out of a matching guild reply', function (string $hidden): void {
    $viewer = User::factory()->create(['handle' => 'admin']);
    Guild::factory()->create(['handle' => 'rust']);
    $root = Commit::factory()->create();
    $parent = Commit::factory()->for($root, 'parent')->create(['body' => 'Private parent context']);
    $match = Commit::factory()->for($parent, 'parent')->create(['body' => 'A public #rust reply']);

    if ($hidden === 'pending') {
        $parent->update(['status' => CommitStatus::Pending]);
    } else {
        $viewer->muting()->attach($parent->user_id);
    }

    $this->actingAs($viewer)->get(route('guild.show', 'rust'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits', 1)->where('commits.0.id', $match->id)->where('commits.0.parent', null));
})->with(['pending', 'muted']);

it('matches decorated names to their hashtag while preserving their display spelling', function (string $handle, string $name): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))->post(route('guild.store'), [
        'handle' => $handle, 'name' => $name, 'description' => 'A developer community.', 'emoji' => '☕',
    ])->assertRedirectToRoute('guild.show', $handle);
    expect(Guild::query()->sole()->name)->toBe($name);
})->with([
    'Unicode spaces and case' => ['cafédev', "CAFÉ\u{00A0}Dev"],
    'slashes' => ['devnull', '/dev/null'],
    'punctuation' => ['jobs', 'Job-s!'],
    'symbols' => ['c', 'C++'],
    'decorative emoji' => ['café_dev2', '☕ CAFÉ_Dev 2!'],
]);

it('keeps guild reports private encrypted and deduplicated until admins dismiss them', function (): void {
    $owner = User::factory()->create(['handle' => 'admin']);
    $reporter = User::factory()->create(['handle' => 'other-admin']);
    $guild = Guild::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner)->postJson(route('guild-report.store', $guild), ['details' => 'Own report'])->assertNotFound();
    $this->actingAs($reporter)->postJson(route('guild-report.store', $guild), ['details' => ''])->assertUnprocessable()->assertJsonValidationErrors('details');
    $this->post(route('guild-report.store', $guild), ['details' => 'Description promotes an unrelated scam.'])->assertRedirect();
    $this->post(route('guild-report.store', $guild), ['details' => 'Repeated report'])->assertRedirect();
    $report = GuildReport::query()->sole();
    expect($report->details)->toBe('Description promotes an unrelated scam.')
        ->and(DB::table('guild_reports')->value('details'))->not->toBe($report->details);
    $this->get(route('guild.show', $guild->handle))->assertInertia(fn ($page) => $page->missing('reports')->missing('guild.details'));
    $this->get(route('guild-report.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('reports.data', 1)->where('reports.data.0.details', $report->details)->missing('reports.data.0.reporter_id'));
    $this->delete(route('guild-report.destroy', $report))->assertRedirect();
    expect($report->fresh()->dismissed_at)->not->toBeNull();
    $this->get(route('guild-report.index'))->assertInertia(fn ($page) => $page->has('reports.data', 0));
    $this->post(route('guild-report.store', $guild), ['details' => 'The updated description has a new issue.'])->assertRedirect();
    expect(GuildReport::query()->sole()->details)->toBe('The updated description has a new issue.')
        ->and($report->fresh()->dismissed_at)->toBeNull();
});

it('pauses the guild without disconnecting its content and forbids removed moderators from reclaiming it', function (): void {
    Storage::fake();
    $owner = User::factory()->create(['handle' => 'admin']);
    $other = User::factory()->create(['handle' => 'other-admin']);
    $guild = Guild::factory()->create(['user_id' => $owner->id, 'handle' => 'jobs', 'icon_path' => 'guild-icons/removed.webp']);
    $guild->members()->attach($owner, ['joined_at' => now(), 'muted_at' => now()]);
    $guild->muters()->attach($other, ['muted_at' => now()]);
    Storage::put($guild->icon_path, 'image');
    $commit = Commit::factory()->create(['body' => '#jobs opportunity']);
    $board = Commit::factory()->for($other)->for($guild)->create();
    $scheduled = ScheduledCommit::factory()->for($other)->for($guild)->create(['scheduled_at' => now()]);
    resolve(CreateGuildReport::class)->handle($other, $guild, 'Unrelated description.');
    $this->actingAs($other)->delete(route('guild-moderator.destroy', $guild))->assertRedirectToRoute('guild.show', 'jobs');
    expect($guild->fresh())->user_id->toBeNull()->description->toBe($guild->description)->icon_path->toBe($guild->icon_path);
    $this->assertModelExists($commit);
    expect($board->fresh()->guild_id)->toBe($guild->id);
    expect(resolve(UpdateScheduledCommitSubmittedAt::class)->handle($scheduled->id))->toBeFalse();
    expect($scheduled->fresh())->guild_id->toBe($guild->id)->submitted_at->toBeNull()->canceled_at->toBeNull();
    $this->assertDatabaseCount('guild_reports', 1);
    $this->assertDatabaseCount('guild_preferences', 2);
    Storage::assertExists('guild-icons/removed.webp');
    $this->get(route('guild.show', 'jobs'))->assertOk()->assertInertia(fn ($page) => $page->where('guild.is_paused', true)->has('commits', 2));
    $this->delete(route('guild-moderator.destroy', $guild))->assertRedirect();
    $payload = ['handle' => 'JOBS', 'name' => 'Jobs', 'description' => 'Real jobs.', 'emoji' => '💼'];
    $this->actingAs($owner)->postJson(route('guild.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('handle');
    $this->postJson(route('guild.store'), array_diff_key($payload, ['handle' => true]))->assertUnprocessable()->assertJsonValidationErrors('handle');
    expect(fn () => resolve(CreateGuild::class)->handle($owner, 'JOBS', 'Jobs', 'Real jobs.', '💼', null))->toThrow(GuildModeratorRemoved::class);
    $this->actingAs($other)->post(route('guild.store'), $payload)->assertRedirectToRoute('guild.show', 'jobs');
    $replacement = Guild::query()->sole();
    expect($replacement)->user_id->toBe($other->id)->id->toBe($guild->id)->description->toBe($guild->description)->icon_path->toBe($guild->icon_path);
    expect(resolve(UpdateScheduledCommitSubmittedAt::class)->handle($scheduled->id))->toBeTrue();
    expect($scheduled->fresh()->commit->guild_id)->toBe($guild->id);
    $this->delete(route('guild-moderator.destroy', $replacement))->assertRedirect();
    $this->postJson(route('guild.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('handle');
    $this->actingAs($owner)->postJson(route('guild.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('handle');
});

it('returns a claim error if removal wins the race after validation', function (): void {
    $owner = User::factory()->create(['handle' => 'admin']);
    $this->app->beforeResolving(CreateGuild::class, function () use ($owner): void {
        DB::table('guild_moderator_removals')->insert(['github_id' => $owner->github_id, 'handle' => 'jobs', 'created_at' => now()]);
    });
    $this->actingAs($owner)->post(route('guild.store'), ['handle' => 'jobs', 'name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼'])
        ->assertSessionHasErrors('handle');
    $this->assertDatabaseCount('guilds', 0);
});

it('gates reporting removal and review behind the admin preview', function (bool $disabled): void {
    $user = User::factory()->create(['handle' => $disabled ? 'admin' : 'ordinary']);
    $guild = Guild::factory()->create();
    resolve(CreateGuildReport::class)->handle($user, $guild, 'Unrelated description.');
    $report = GuildReport::query()->sole();
    if ($disabled) {
        $this->actingAs($user)->put(route('feature.update', Feature::Guilds), ['enabled' => false])->assertRedirect();
    }

    $this->actingAs($user)->postJson(route('guild-report.store', $guild), ['details' => 'Report'])->assertNotFound();
    $this->get(route('guild-report.index'))->assertNotFound();
    $this->delete(route('guild-report.destroy', $report))->assertNotFound();
    $this->delete(route('guild-moderator.destroy', $guild))->assertNotFound();
    $this->assertModelExists($guild);
    expect($report->fresh()->dismissed_at)->toBeNull();
})->with([false, true]);

it('keeps guild filtering on one snapshot while commits change between the scan and the feed read', function (): void {
    config(['queue.default' => 'sync']);
    $default = DB::getDefaultConnection();
    config(['database.connections.guild_reader' => config('database.connections.'.$default), 'database.connections.guild_writer' => config('database.connections.'.$default)]);
    DB::setDefaultConnection('guild_reader');
    $user = User::factory()->create(['handle' => 'admin']);

    try {
        $guild = Guild::factory()->create(['user_id' => $user->id, 'handle' => 'rust']);
        $guild->muters()->attach($user, ['muted_at' => now()]);
        $commits = Commit::factory()->for($user)->count(201)->create(['body' => '#rust']);
        $plain = Commit::factory()->for($user)->create(['body' => 'An unrelated commit']);
        $changed = false;
        DB::listen(function (QueryExecuted $query) use (&$changed, $commits, $user): void {
            if (! $changed && $query->connectionName === 'guild_reader' && str_starts_with($query->sql, 'select "id", "body", "guild_id"')) {
                $changed = true;
                DB::connection('guild_writer')->table('commits')->where('id', $commits->last()->id)->delete();
                Commit::factory()->connection('guild_writer')->for($user)->create(['body' => 'A concurrent #rust commit']);
            }
        });

        $page = resolve(LogCommitsQuery::class)->paginate(Log::Following, $user);

        expect($changed)->toBeTrue()->and($page->getCollection()->modelKeys())->toBe([$plain->id]);
        expect(DB::transactionLevel())->toBe(0);
    } finally {
        Guild::query()->where('user_id', $user->id)->delete();
        resolve(DeleteUser::class)->handle($user);
        DB::table('deleted_github_accounts')->where('github_id', $user->github_id)->delete();
        DB::setDefaultConnection($default);
        DB::purge('guild_reader');
        DB::purge('guild_writer');
    }
});

it('hides muted guild replies in logs while keeping direct threads readable', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['handle' => 'rust']);
    $guild->muters()->attach($admin, ['muted_at' => now()]);
    $parent = Commit::factory()->for($admin)->create(['body' => 'What are you learning?']);
    $reply = Commit::factory()->for($parent, 'parent')->create(['body' => '#rust']);

    $this->actingAs($admin);
    foreach (['following', 'latest', 'trending'] as $log) {
        $this->get(route('log.show', $log))->assertOk()->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)->has('commits.data.0.reply_preview.data', 0));
    }

    $hidden = Commit::factory()->pending()->create();
    $this->get(route('log.show', ['log' => 'trending', 'pending' => [$parent->id, $reply->id, $hidden->id]]), [
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'log/show', 'X-Inertia-Partial-Data' => 'refreshedCommits',
    ])->assertOk()->assertJsonCount(1, 'props.refreshedCommits')
        ->assertJsonPath('props.refreshedCommits.0.id', $parent->id)
        ->assertJsonCount(0, 'props.refreshedCommits.0.reply_preview.data');
    $this->get(route('guild.show', 'rust'))->assertInertia(fn ($page) => $page
        ->has('commits', 1)->where('commits.0.id', $reply->id)->where('commits.0.parent.id', $parent->id));
    $this->getJson(route('commit-reply.index', [$parent, 'in_log' => true]))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('commit-reply.index', [$parent, 'in_log' => true, 'ids' => [$reply->id]]))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('commit-reply.index', $parent))->assertOk()->assertJsonPath('data.0.id', $reply->id);
    $this->put(route('guild-mute.update', $guild), ['muted' => false])->assertRedirect();
    $this->get(route('log.show', 'following'))->assertInertia(fn ($page) => $page->where('commits.data.0.reply_preview.data.0.id', $reply->id));
    $this->getJson(route('commit-reply.index', [$parent, 'in_log' => true]))->assertOk()->assertJsonPath('data.0.id', $reply->id);
});

it('lists guilds by members rather than commits and clears a mute when joining', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $jobs = Guild::factory()->create(['handle' => 'jobs']);
    $rust = Guild::factory()->create(['handle' => 'rust']);
    $rust->members()->attach(User::factory()->create(), ['joined_at' => now()]);
    Commit::factory()->count(3)->create(['body' => '#jobs']);

    $this->actingAs($admin)->get(route('guild.index'))->assertInertia(fn ($page) => $page
        ->component('guild/index')->where('guildPage.data.0.handle', 'rust')->where('guildPage.data.0.member_count', 1)
        ->where('guildPage.data.1.is_joined', false)->where('guildPage.data.1.is_muted', false)->where('guilds', []));
    $this->put(route('guild-membership.update', $jobs), ['joined' => true])->assertRedirect();
    $this->put(route('guild-membership.update', $jobs), ['joined' => true])->assertRedirect();
    $this->put(route('guild-mute.update', $jobs), ['muted' => true])->assertRedirect();
    $this->get(route('guild.index'))->assertInertia(fn ($page) => $page
        ->where('guildPage.data.0.handle', 'jobs')->where('guildPage.data.0.member_count', 1)
        ->where('guildPage.data.0.is_joined', true)->where('guildPage.data.0.is_muted', true));
    $this->put(route('guild-membership.update', $jobs), ['joined' => false])->assertRedirect();
    $this->assertDatabaseHas('guild_preferences', ['guild_id' => $jobs->id, 'user_id' => $admin->id, 'joined_at' => null, 'muted_at' => now()]);
    $this->put(route('guild-mute.update', $jobs), ['muted' => false])->assertRedirect();
    $this->assertDatabaseMissing('guild_preferences', ['guild_id' => $jobs->id, 'user_id' => $admin->id]);
    $this->put(route('guild-mute.update', $jobs), ['muted' => true])->assertRedirect();
    $this->put(route('guild-membership.update', $jobs), ['joined' => true])->assertRedirect();
    $this->assertDatabaseHas('guild_preferences', ['guild_id' => $jobs->id, 'user_id' => $admin->id, 'joined_at' => now(), 'muted_at' => null]);
    $this->put(route('guild-mute.update', $jobs), ['muted' => false])->assertRedirect();
    $this->assertDatabaseHas('guild_preferences', ['guild_id' => $jobs->id, 'user_id' => $admin->id, 'joined_at' => now(), 'muted_at' => null]);
    $this->put(route('guild-membership.update', $jobs), ['joined' => false])->assertRedirect();
    $this->assertDatabaseMissing('guild_preferences', ['guild_id' => $jobs->id, 'user_id' => $admin->id]);
});

it('paginates matching guilds biggest first with handle ordering for ties', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guilds = Guild::factory()->count(25)->state(['user_id' => $admin->id])->sequence(fn ($sequence): array => [
        'handle' => 'group'.mb_str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT),
        'name' => 'Builders',
    ])->create();
    $popular = $guilds->last();
    $popular->members()->attach($admin, ['joined_at' => now()]);
    Guild::factory()->create(['user_id' => $admin->id, 'name' => 'Unrelated', 'handle' => 'unrelated', 'description' => 'Other discussions.']);

    $this->actingAs($admin)->get(route('guild.index', ['q' => 'Builders']))->assertOk()->assertInertia(fn ($page) => $page
        ->where('query', 'Builders')->where('guildPage.total', 25)->where('guildPage.last_page', 2)
        ->where('guildPage.current_page', 1)->where('guildPage.data', fn ($data): bool => collect($data)->pluck('id')->all() === [$popular->id, ...$guilds->take(23)->modelKeys()])->where('guilds', []));
    $this->get(route('guild.index', ['q' => 'Builders', 'page' => 2]))->assertOk()->assertInertia(fn ($page) => $page
        ->where('query', 'Builders')->where('guildPage.current_page', 2)->where('guildPage.total', 25)
        ->where('guildPage.data', fn ($data): bool => collect($data)->pluck('id')->all() === [$guilds[23]->id]));
    $this->get(route('guild.index', ['q' => 'Builders', 'page' => 3]))->assertOk()->assertInertia(fn ($page) => $page
        ->where('guildPage.data', [])->where('guildPage.total', 25));
});

it('searches guild names handles and descriptions literally without case sensitivity', function (string $term, string $normalized, bool $matches): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['user_id' => $admin->id, 'handle' => 'rustaceans', 'name' => 'Systems Builders', 'description' => '100% safe_code!']);
    Guild::factory()->create(['user_id' => $admin->id, 'handle' => 'other', 'name' => 'Different people', 'description' => '100 percent safeXcode.']);

    $this->actingAs($admin)->get(route('guild.index', ['q' => $term]))->assertOk()->assertInertia(fn ($page) => $page
        ->where('query', $normalized)->where('guildPage.data', fn ($data): bool => collect($data)->pluck('id')->all() === ($matches ? [$guild->id] : [])));
})->with([
    'name' => ['bUiLdErS', 'bUiLdErS', true],
    'handle' => ['  #RuStAcEaNs  ', 'RuStAcEaNs', true],
    'description' => ['SAFE_CODE', 'SAFE_CODE', true],
    'literal percent' => ['%', '%', true],
    'literal underscore' => ['_', '_', true],
    'literal escape' => ['!', '!', true],
    'no matches' => ['missing', 'missing', false],
]);

it('returns complete viewer-specific cards only for the requested directory page', function (): void {
    $viewer = User::factory()->create(['handle' => 'admin']);
    $other = User::factory()->create(['handle' => 'other-admin']);
    $guilds = Guild::factory()->count(49)->sequence(fn ($sequence): array => [
        'handle' => 'group'.mb_str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT),
    ])->create();
    $target = $guilds[30];
    $target->muters()->attach($viewer, ['muted_at' => now()]);

    $response = $this->actingAs($viewer)->get(route('guild.index', ['page' => 2]), [
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'guild/index', 'X-Inertia-Partial-Data' => 'guildPage',
    ])->assertOk()->assertJsonCount(24, 'props.guildPage.data')->assertJsonPath('props.guildPage.total', 49)
        ->assertJsonPath('props.guildPage.current_page', 2)->assertJsonPath('props.guildPage.last_page', 3)
        ->assertJsonMissingPath('props.guilds')->assertJsonMissingPath('props.guildPage.data.0.user_id');
    $data = $response->json('props.guildPage.data');
    expect(collect($data)->pluck('id')->all())->toBe($guilds->slice(24, 24)->values()->modelKeys());
    expect($data[6])->toBe([
        'id' => $target->id, 'handle' => $target->handle, 'name' => $target->name,
        'description' => $target->description, 'emoji' => $target->emoji, 'icon_url' => null,
        'member_count' => 0, 'is_joined' => false, 'is_muted' => true, 'is_paused' => false,
        'html_url' => route('guild.show', $target->handle),
    ]);

    $this->actingAs($other)->get(route('guild.index', ['page' => 2]))->assertInertia(fn ($page) => $page
        ->where('guildPage.data.6.is_muted', false)->where('guilds', []));
    $this->get('/search?q=%23'.$target->handle)->assertInertia(fn ($page) => $page
        ->where('guildLookup.handle', $target->handle)->where('guildLookup.guild.name', $target->name)->missing('guilds'));
    $this->get(route('guild.show', $target->handle))->assertInertia(fn ($page) => $page
        ->where('guild.id', $target->id)->where('guild.name', $target->name)->missing('guilds'));
});

it('matches Unicode search terms consistently even with C-collated guild columns', function (string $field, string $text, string $term): void {
    DB::statement(sprintf('ALTER TABLE guilds ALTER COLUMN %s TYPE text COLLATE "C"', $field));
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['user_id' => $admin->id, $field => $text]);
    Guild::factory()->create(['user_id' => $admin->id, 'handle' => 'unrelated', 'name' => 'Other', 'description' => 'No match.']);

    $this->actingAs($admin)->get(route('guild.index', ['q' => $term]))->assertOk()->assertInertia(fn ($page) => $page
        ->where('guildPage.data', fn ($data): bool => collect($data)->pluck('id')->all() === [$guild->id]));
})->with(['handle', 'name', 'description'])->with([
    'Greek prefix ending in sigma' => ['ΟΣΑ', 'ΟΣ'],
    'lowercase final sigma' => ['κοσμος', 'ΚΟΣΜΟΣ'],
    'uppercase final sigma' => ['ΚΟΣΜΟΣ', 'κοσμος'],
    'accented capital' => ['ÉCOLE', 'école'],
]);

it('refreshes the selected directory records without repaginating after their ranks change', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guilds = Guild::factory()->count(25)->state(['user_id' => $admin->id])->sequence(fn ($sequence): array => [
        'handle' => 'group'.mb_str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT),
    ])->create();
    $guild = $guilds->last();
    $this->actingAs($admin)->get(route('guild.index', ['page' => 2]))->assertInertia(fn ($page) => $page
        ->where('guildPage.data.0.handle', 'group24')->where('guilds', []));
    $this->put(route('guild-membership.update', $guild), ['joined' => true])->assertRedirect();

    $this->get(route('guild.index', ['page' => 2]), [
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'guild/index', 'X-Inertia-Partial-Data' => 'guilds',
        'X-Guild-Handles' => json_encode([$guild->handle], JSON_THROW_ON_ERROR),
    ])->assertOk()->assertJsonCount(1, 'props.guilds')->assertJsonPath('props.guilds.0.id', $guild->id)
        ->assertJsonPath('props.guilds.0.is_joined', true)->assertJsonPath('props.guilds.0.member_count', 1)->assertJsonMissingPath('props.guildPage');
    $this->get(route('guild.index', ['page' => 2]))->assertInertia(fn ($page) => $page->where('guildPage.data.0.id', $guilds[23]->id));
});

it('rejects malformed and unbounded directory refresh headers', function (string $header): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->getJson(route('guild.index'), ['X-Guild-Handles' => $header])->assertUnprocessable()->assertJsonValidationErrors('visible_guilds');
})->with(['invalid json', '"rust"', '{"handle":"rust"}', '{"0":"rust"}', json_encode(array_fill(0, 25, 'rust'), JSON_THROW_ON_ERROR)]);

it('includes newly created guild cards when refreshing directory search results', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $this->actingAs($admin)->get(route('guild.index'))->assertOk()->assertInertia(fn ($page) => $page->where('guildPage.data', []));
    $guild = Guild::factory()->create(['user_id' => $admin->id, 'handle' => 'rust', 'name' => 'Rust']);

    $this->get(route('guild.index', ['q' => 'rust']), [
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'guild/index', 'X-Inertia-Partial-Data' => 'guildPage,query',
    ])->assertOk()->assertJsonPath('props.guildPage.data.0.id', $guild->id)->assertJsonCount(1, 'props.guildPage.data')
        ->assertJsonPath('props.guildPage.data.0.name', 'Rust')->assertJsonMissingPath('props.guilds');
});

it('validates guild directory search and page inputs', function (array $input, string $field): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->getJson(route('guild.index', $input))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'array query' => [['q' => ['rust']], 'q'],
    'long query' => [['q' => str_repeat('a', 101)], 'q'],
    'zero page' => [['page' => 0], 'page'],
    'fractional page' => [['page' => 1.5], 'page'],
    'large page' => [['page' => 10001], 'page'],
]);

it('guards guild discovery and preferences with the preview flag and validates boolean inputs', function (): void {
    $guild = Guild::factory()->create();
    $this->get(route('guild.index'))->assertRedirectToRoute('home');
    $this->putJson(route('guild-membership.update', $guild), ['joined' => true])->assertUnauthorized();
    $this->putJson(route('guild-mute.update', $guild), ['muted' => true])->assertUnauthorized();
    $this->actingAs(User::factory()->create())->get(route('guild.index'))->assertNotFound();
    $this->put(route('guild-membership.update', $guild), ['joined' => true])->assertNotFound();
    $this->put(route('guild-mute.update', $guild), ['muted' => true])->assertNotFound();
    $admin = User::factory()->create(['handle' => 'admin']);
    $this->actingAs($admin)->putJson(route('guild-membership.update', $guild), ['joined' => 'yes'])->assertUnprocessable()->assertJsonValidationErrors('joined');
    $this->putJson(route('guild-mute.update', $guild), [])->assertUnprocessable()->assertJsonValidationErrors('muted');
    $this->put(route('feature.update', Feature::Guilds), ['enabled' => false])->assertRedirect();
    $this->get(route('guild.index'))->assertNotFound();
    $this->put(route('guild-membership.update', $guild), ['joined' => true])->assertNotFound();
    $this->put(route('guild-mute.update', $guild), ['muted' => true])->assertNotFound();
    $this->assertDatabaseCount('guild_preferences', 0);
});

it('updates overlapping guild attribution and lets mute override joining or following an organization', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $rust = Guild::factory()->create(['handle' => 'rust']);
    $jobs = Guild::factory()->create(['handle' => 'jobs']);
    $rust->members()->attach($admin, ['joined_at' => now()]);
    $jobs->members()->attach($admin, ['joined_at' => now()]);
    $organization = Organization::factory()->create();
    $admin->followedOrganizations()->attach($organization);
    $personal = Commit::factory()->create(['body' => '#rust #jobs']);
    $organizationCommit = Commit::factory()->create(['author_id' => $organization->id, 'body' => '#rust #jobs']);

    $this->actingAs($admin);
    $data = collect($this->get(route('log.show', 'following'))->assertOk()->inertiaProps('commits.data'))->keyBy('id');
    expect($data)->toHaveCount(2);
    expect($data[$personal->id]['joined_guild'])->toBe('rust')->and($data[$organizationCommit->id]['joined_guild'])->toBeNull();
    $this->put(route('guild-membership.update', $rust), ['joined' => false])->assertRedirect();
    $data = collect($this->get(route('log.show', 'following'))->assertOk()->inertiaProps('commits.data'))->keyBy('id');
    expect($data[$personal->id]['joined_guild'])->toBe('jobs');
    $this->put(route('guild-mute.update', $rust), ['muted' => true])->assertRedirect();
    $this->get(route('log.show', 'following'))->assertInertia(fn ($page) => $page->has('commits.data', 0));
});

it('includes joined guild commits in Following with an attribution only for unfollowed authors', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['handle' => 'rust']);
    $guild->members()->attach($admin, ['joined_at' => now()]);
    $author = User::factory()->create();
    $followed = User::factory()->create();
    $admin->following()->attach($followed);
    $included = Commit::factory()->create(['user_id' => $author->id, 'body' => 'Learning #RuSt']);
    $regular = Commit::factory()->create(['user_id' => $followed->id, 'body' => 'Shipping #rust']);
    $own = Commit::factory()->create(['user_id' => $admin->id, 'body' => 'My #rust']);
    Commit::factory()->create(['body' => '`#rust` https://example.com/#rust #rustlang']);
    Commit::factory()->create(['body' => '#rust', 'status' => CommitStatus::Pending]);
    $blocked = User::factory()->create();
    $admin->blocking()->attach($blocked);
    Commit::factory()->for($blocked)->create(['body' => '#rust']);
    $muted = User::factory()->create();
    $admin->muting()->attach($muted);
    Commit::factory()->for($muted)->create(['body' => '#rust']);

    $response = $this->actingAs($admin)->get(route('log.show', 'following'))->assertOk();
    $response->assertInertia(fn ($page) => $page->has('commits.data', 3));

    $data = collect($response->inertiaProps('commits.data'))->keyBy('id');
    expect($data[$included->id]['joined_guild'])->toBe('rust')
        ->and($data[$regular->id]['joined_guild'])->toBeNull()
        ->and($data[$own->id]['joined_guild'])->toBeNull();

    $key = apiKeyPairFor($admin);
    signedApiRequest($key, 'GET', route('api-log.show', 'following'))->assertOk()->assertJsonCount(3, 'data');
    $this->get(route('log.show', ['log' => 'following', 'everything' => true]), [
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'log/show', 'X-Inertia-Partial-Data' => 'newCommitsCount',
    ])->assertOk()->assertJsonPath('props.newCommitsCount', 2);

    $this->put(route('guild-mute.update', $guild), ['muted' => true])->assertRedirect();
    signedApiRequest($key, 'GET', route('api-log.show', 'following'))->assertOk()->assertJsonCount(0, 'data');
    $this->get(route('log.show', ['log' => 'following', 'everything' => true, 'pending' => [$included->id]]), [
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'log/show', 'X-Inertia-Partial-Data' => 'newCommitsCount,removedCommitIds',
    ])->assertOk()->assertJsonPath('props.newCommitsCount', 0)->assertJsonPath('props.removedCommitIds', [$included->id]);
    $this->get(route('log.show', 'following'))->assertInertia(fn ($page) => $page->has('commits.data', 0));
    $this->get(route('log.show', 'latest'))->assertInertia(fn ($page) => $page->has('commits.data', 1));
    $this->get(route('guild.show', 'rust'))->assertInertia(fn ($page) => $page->has('commits', 3));
    $this->put(route('guild-mute.update', $guild), ['muted' => false])->assertRedirect();
    $this->get(route('log.show', 'following'))->assertInertia(fn ($page) => $page->has('commits.data', 3));
    $this->put(route('guild-membership.update', $guild), ['joined' => false])->assertRedirect();
    $this->get(route('log.show', 'following'))->assertInertia(fn ($page) => $page->has('commits.data', 2));
    $guild->members()->attach($admin, ['joined_at' => now(), 'muted_at' => now()]);
    $this->put(route('feature.update', Feature::Guilds), ['enabled' => false])->assertRedirect();
    $this->get(route('log.show', 'following'))->assertInertia(fn ($page) => $page->has('commits.data', 2));
});

it('limits how often a member changes a guild membership or a guild mute', function (string $route, array $payload): void {
    $guild = Guild::factory()->create();
    $this->actingAs(User::factory()->create(['handle' => 'admin']));

    foreach (range(1, 30) as $attempt) {
        $this->putJson(route($route, $guild), $payload)->assertRedirect();
    }

    $this->putJson(route($route, $guild), $payload)->assertTooManyRequests();
})->with([
    ['guild-membership.update', ['joined' => true]],
    ['guild-mute.update', ['muted' => true]],
]);

it('announces a membership to its members and refreshes the changed persons profile', function (bool $joined): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $member = User::factory()->create();
    User::factory()->create();
    $guild = Guild::factory()->create();
    $guild->members()->attach($member, ['joined_at' => now()]);
    Event::fake([UsersUpdated::class, ProfilesUpdated::class]);

    $this->actingAs($admin)->put(route('guild-membership.update', $guild), ['joined' => $joined])->assertRedirect();

    Event::assertDispatchedTimes(UsersUpdated::class, 2);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds && $event->userIds === [$member->id, $admin->id]);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Relationships && $event->userIds === [$admin->id]);
    Event::assertDispatched(ProfilesUpdated::class, fn (ProfilesUpdated $event): bool => $event->profileIds === [$admin->id]);
})->with([true, false]);

it('announces an edit of a guild only to its members and to its creator', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $member = User::factory()->create();
    User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $admin->id]);
    $guild->members()->attach($member, ['joined_at' => now()]);
    Event::fake([UsersUpdated::class]);

    $this->actingAs($admin)->put(route('guild.update', $guild->handle), ['name' => $guild->handle, 'description' => 'Remote jobs.', 'emoji' => '💼', 'remove_icon' => false])->assertRedirect();

    Event::assertDispatchedTimes(UsersUpdated::class, 1);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds && $event->userIds === [$member->id, $admin->id]);
});

it('lets a person create one hundred guilds and refuses the next one', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    Guild::factory()->count(98)->create(['user_id' => $admin->id]);
    $existing = Guild::query()->where('user_id', $admin->id)->firstOrFail();
    GuildSubmission::factory()->for($admin)->create(['handle' => $existing->handle]);
    GuildSubmission::factory()->for($admin)->create();
    Guild::factory()->count(3)->create();
    $payload = ['description' => 'A public group.', 'emoji' => '💻'];

    $this->actingAs($admin)->postJson(route('guild.store'), ['name' => 'Hundredth', ...$payload])->assertRedirectToRoute('guild.show', 'hundredth');
    $this->postJson(route('guild.store'), ['name' => 'Next', ...$payload])->assertUnprocessable()->assertJsonValidationErrors(['handle' => 'You can create up to 100 guilds.']);

    $this->assertDatabaseMissing('guilds', ['handle' => 'next']);
});

it('lets a person replace a pending creation at the guild limit', function (bool $flagged): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    Guild::factory()->count(99)->create(['user_id' => $admin->id]);
    $previous = GuildSubmission::factory()->for($admin)->create(['handle' => 'adhd']);
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->andReturn($flagged ? ['harassment' => 0.7] : []);
    $moderation->shouldReceive('isHarmful')->andReturn(false);

    $this->actingAs($admin)->post(route('guild.store'), ['name' => 'ADHD', 'description' => 'A revised description.', 'emoji' => '🧠'])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertModelMissing($previous);
    $this->assertDatabaseHas($flagged ? 'guild_submissions' : 'guilds', ['user_id' => $admin->id, 'handle' => 'adhd']);
    expect(Guild::query()->where('user_id', $admin->id)->count() + GuildSubmission::query()->where('user_id', $admin->id)->count())->toBe(100);
})->with([true, false]);

it('explains when a guild hashtag is already taken', function (): void {
    Guild::factory()->create(['handle' => 'adhd']);

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->postJson(route('guild.store'), ['name' => 'ADHD', 'description' => 'A public group.', 'emoji' => '🧠'])
        ->assertUnprocessable()->assertJsonValidationErrors(['handle' => 'A guild already uses this hashtag. Choose a different display name.']);
});

it('includes public guild pill details on board permalinks even for guests', function (): void {
    $guild = Guild::factory()->create(['handle' => 'adhd', 'name' => 'ADHD', 'emoji' => '🧠']);
    $commit = Commit::factory()->create(['guild_id' => $guild->id]);

    $this->get(route('commit.show', [$commit->author, $commit]))->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.guild', ['handle' => 'adhd', 'name' => 'ADHD', 'emoji' => '🧠', 'icon_url' => null]));
});

it('classifies queued guild edits by both handle and owner without per-submission queries', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    Guild::factory()->create(['user_id' => $admin->id, 'handle' => 'owned']);
    Guild::factory()->create(['handle' => 'taken']);
    foreach (['owned', 'taken', 'new'] as $handle) {
        GuildSubmission::factory()->for($admin)->create(['handle' => $handle]);
    }

    $this->actingAs($admin);
    DB::enableQueryLog();

    $response = $this->get(route('guild-report.index'), [
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'moderation/guilds', 'X-Inertia-Partial-Data' => 'submissions',
    ])->assertOk();

    expect(collect($response->json('props.submissions'))->pluck('is_edit', 'handle')->all())->toBe(['owned' => true, 'taken' => false, 'new' => false]);
    expect(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "guilds"')))->toHaveCount(1);
    DB::disableQueryLog();
});

it('holds a guild whose name description or icon the moderation flags for review until an admin approves it', function (array $scores, bool $harmful): void {
    Storage::fake();
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->once()
        ->withArgs(fn (string $text, array $icons): bool => $text === "ADHD\n\nYou absolute mess." && count($icons) === 1 && str_starts_with($icons[0], 'data:image/webp;base64,'))
        ->andReturn($scores);
    $moderation->shouldReceive('isHarmful')->with("ADHD\n\nYou absolute mess.")->andReturn($harmful);
    $creator = User::factory()->create(['handle' => 'admin']);
    Event::fake([UsersUpdated::class]);

    $this->actingAs($creator)->from(route('guild.index'))->post(route('guild.store'), [
        'name' => 'ADHD', 'description' => 'You absolute mess.', 'emoji' => '🧠', 'images' => [UploadedFile::fake()->image('icon.png', 80, 60)],
    ])->assertRedirectToRoute('guild.index')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Your guild is waiting for review. It goes live once an admin approves it.']);

    $this->assertDatabaseCount('guilds', 0);
    $submission = GuildSubmission::query()->sole();
    expect($submission)->user_id->toBe($creator->id)->handle->toBe('adhd')->name->toBe('ADHD')->description->toBe('You absolute mess.')
        ->and(DB::table('guild_submissions')->value('description'))->not->toBe('You absolute mess.')
        ->and(Storage::allFiles())->toBe([$submission->icon_path]);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Moderation && in_array($creator->id, $event->userIds, true));

    $this->get(route('guild-report.index'))->assertOk()->assertInertia(fn ($page) => $page->where('submissions', [[
        'id' => $submission->id, 'handle' => 'adhd', 'name' => 'ADHD', 'description' => 'You absolute mess.', 'emoji' => '🧠',
        'icon_url' => route('guild-submission-icon.show', $submission), 'remove_icon' => false, 'author' => 'admin', 'is_edit' => false,
    ]]));
    $this->get(route('guild-submission-icon.show', $submission))->assertOk()->assertHeader('Content-Type', 'image/webp');

    $this->post(route('guild-submission-approval.store', $submission))->assertRedirect();

    $guild = Guild::query()->sole();
    expect($guild)->handle->toBe('adhd')->name->toBe('ADHD')->description->toBe('You absolute mess.')->emoji->toBe('🧠')
        ->user_id->toBe($creator->id)->icon_path->toBe($submission->icon_path);
    $this->assertModelMissing($submission);
    $this->assertDatabaseHas('guild_preferences', ['guild_id' => $guild->id, 'user_id' => $creator->id]);
    Storage::assertExists($guild->icon_path);
    $this->get(route('guild-report.index'))->assertInertia(fn ($page) => $page->where('submissions', []));

    resolve(DeleteGuildSubmission::class)->handle($submission);
    resolve(CreateGuildSubmissionApproval::class)->handle($submission);
    Storage::assertExists($guild->icon_path);
})->with([
    'a flagged category' => [['harassment' => 0.88], false],
    'harmful text' => [[], true],
]);

it('keeps a guild as it was while a flagged edit waits and applies the edit once approved', function (): void {
    Storage::fake();
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['user_id' => $admin->id, 'handle' => 'jobs', 'name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💻', 'icon_path' => 'guild-icons/original.webp']);
    Storage::put($guild->icon_path, 'original');
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->andReturn(['harassment' => 0.7]);

    $this->actingAs($admin)->put(route('guild.update', 'jobs'), ['name' => 'JOBS', 'description' => 'Flagged words.', 'emoji' => '💼', 'remove_icon' => false, 'images' => [UploadedFile::fake()->image('first.png')]])
        ->assertRedirectToRoute('guild.show', 'jobs')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Your changes are waiting for review. They go live once an admin approves them.']);
    expect($guild->fresh())->name->toBe('Jobs')->description->toBe('Hiring.')->emoji->toBe('💻')->icon_path->toBe('guild-icons/original.webp');
    $first = GuildSubmission::query()->sole()->icon_path;
    $this->put(route('guild.update', 'jobs'), ['name' => 'J.O.B.S', 'description' => 'Other flagged words.', 'emoji' => '🌍', 'remove_icon' => true])->assertRedirect();

    expect($guild->fresh())->name->toBe('Jobs')->description->toBe('Hiring.')->emoji->toBe('💻')->icon_path->toBe('guild-icons/original.webp');
    $submission = GuildSubmission::query()->sole();
    expect($submission)->name->toBe('J.O.B.S')->icon_path->toBeNull()->remove_icon->toBeTrue();
    Storage::assertMissing($first);
    $this->get(route('guild-report.index'))->assertInertia(fn ($page) => $page->where('submissions.0.is_edit', true)->where('submissions.0.icon_url', null)->where('submissions.0.remove_icon', true));
    $this->get(route('guild-submission-icon.show', $submission))->assertNotFound();

    $this->post(route('guild-submission-approval.store', $submission))->assertRedirect();

    expect($guild->fresh())->name->toBe('J.O.B.S')->description->toBe('Other flagged words.')->emoji->toBe('🌍')->icon_path->toBeNull()->user_id->toBe($admin->id);
    $this->assertModelMissing($submission);
    Storage::assertMissing('guild-icons/original.webp');
});

it('leaves a replacement submission and its icon pending when a stale review targets its predecessor', function (string $action): void {
    Storage::fake();
    $admin = User::factory()->create(['handle' => 'admin']);
    $first = GuildSubmission::factory()->for($admin)->create([
        'handle' => 'jobs', 'name' => 'Jobs', 'description' => 'Previously reviewed words.', 'icon_path' => 'guild-icons/previous.webp',
    ]);
    Storage::put($first->icon_path, 'old image');
    $this->mock(ModerationProvider::class)->shouldReceive('moderate')->andReturn(['harassment' => 0.7]);
    $this->actingAs($admin)->post(route('guild.store'), [
        'name' => 'JOBS', 'description' => 'Replacement flagged words.', 'emoji' => '💼', 'images' => [UploadedFile::fake()->image('replacement.png')],
    ])->assertRedirect();
    $replacement = GuildSubmission::query()->sole();

    resolve($action)->handle($first);

    $route = $action === CreateGuildSubmissionApproval::class ? 'guild-submission-approval.store' : 'guild-submission.destroy';
    $this->from(route('guild-report.index'))->call($action === CreateGuildSubmissionApproval::class ? 'POST' : 'DELETE', route($route, $first))->assertRedirectToRoute('guild-report.index');

    $this->assertDatabaseCount('guilds', 0);
    $this->assertModelMissing($first);
    $this->assertModelExists($replacement);
    expect($replacement->fresh())->name->toBe('JOBS')->description->toBe('Replacement flagged words.');
    Storage::assertExists($replacement->icon_path);
    Storage::assertMissing($first->icon_path);
})->with(['approval' => CreateGuildSubmissionApproval::class, 'rejection' => DeleteGuildSubmission::class]);

it('ignores a bound guild review after its author deletes their account', function (string $action): void {
    $author = User::factory()->create();
    $submission = GuildSubmission::factory()->for($author)->create();
    resolve(DeleteUser::class)->handle($author);

    resolve($action)->handle($submission);

    $this->assertModelMissing($submission);
    $this->assertDatabaseCount('guilds', 0);
})->with(['approval' => CreateGuildSubmissionApproval::class, 'rejection' => DeleteGuildSubmission::class]);

it('discards a rejected submission and its icon without touching the guild', function (): void {
    Storage::fake();
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['user_id' => $admin->id, 'handle' => 'jobs', 'name' => 'Jobs', 'description' => 'Hiring.']);
    $this->mock(ModerationProvider::class)->shouldReceive('moderate')->andReturn(['harassment' => 0.7]);
    $this->actingAs($admin)->put(route('guild.update', 'jobs'), ['name' => 'Jobs', 'description' => 'Flagged words.', 'emoji' => '💼', 'remove_icon' => false, 'images' => [UploadedFile::fake()->image('icon.png')]])->assertRedirect();
    $submission = GuildSubmission::query()->sole();
    Event::fake([UsersUpdated::class]);

    $this->delete(route('guild-submission.destroy', $submission))->assertRedirect();

    $this->assertModelMissing($submission);
    expect($guild->fresh()->description)->toBe('Hiring.')->and(Storage::allFiles())->toBe([]);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Moderation);
});

it('replaces a pending submission with a clean save and never publishes the superseded content', function (bool $editing): void {
    Storage::fake();
    $admin = User::factory()->create(['handle' => 'admin']);

    if ($editing) {
        Guild::factory()->create(['user_id' => $admin->id, 'handle' => 'jobs', 'name' => 'Jobs']);
    }

    $submission = GuildSubmission::factory()->for($admin)->create([
        'handle' => 'jobs', 'name' => 'J.O.B.S', 'description' => 'Superseded submission.', 'icon_path' => 'guild-icons/superseded.webp',
    ]);
    Storage::put($submission->icon_path, 'old image');
    $payload = ['name' => 'JOBS', 'description' => 'Current description.', 'emoji' => '💼', 'remove_icon' => false];

    $response = $editing
        ? $this->actingAs($admin)->put(route('guild.update', 'jobs'), $payload)
        : $this->actingAs($admin)->post(route('guild.store'), $payload);

    $response->assertRedirectToRoute('guild.show', 'jobs');
    $this->assertModelMissing($submission);
    Storage::assertMissing($submission->icon_path);
    resolve(CreateGuildSubmissionApproval::class)->handle($submission);
    expect(Guild::query()->sole())->name->toBe('JOBS')->description->toBe('Current description.')->emoji->toBe('💼');
})->with(['new guild' => false, 'guild edit' => true]);

it('publishes nothing for a submission whose hashtag changed hands while it waited', function (bool $removed): void {
    Storage::fake();
    $author = User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $author->id, 'handle' => 'jobs', 'name' => 'Jobs', 'description' => 'Hiring.']);
    $submission = GuildSubmission::factory()->for($author)->create(['handle' => 'jobs', 'name' => 'JOBS', 'icon_path' => 'guild-icons/pending.webp']);
    Storage::put($submission->icon_path, 'image');
    $removed
        ? resolve(DeleteGuildModerator::class)->handle($guild)
        : $guild->update(['user_id' => User::factory()->create()->id]);

    $this->actingAs(User::factory()->create(['handle' => 'admin']))->post(route('guild-submission-approval.store', $submission))->assertRedirect();

    expect($guild->fresh())->name->toBe('Jobs')->description->toBe('Hiring.')->user_id->not->toBe($author->id)->icon_path->toBeNull();
    $this->assertModelMissing($submission);
    Storage::assertMissing('guild-icons/pending.webp');
})->with(['removed moderator' => true, 'claimed by someone else' => false]);

it('leaves reviewing guild submissions to admins with the preview', function (bool $disabled): void {
    Storage::fake();
    $admin = User::factory()->create(['handle' => 'admin']);
    $user = $disabled ? $admin : User::factory()->create(['handle' => 'ordinary']);
    $submission = GuildSubmission::factory()->create(['icon_path' => 'guild-icons/pending.webp']);
    Storage::put($submission->icon_path, 'image');
    $this->actingAs($admin)->get(route('guild-submission-icon.show', $submission))->assertOk()->assertStreamedContent('image');
    if ($disabled) {
        $this->actingAs($user)->put(route('feature.update', Feature::Guilds), ['enabled' => false])->assertRedirect();
    }

    $this->actingAs($user)->get(route('guild-submission-icon.show', $submission))->assertNotFound();
    $this->post(route('guild-submission-approval.store', $submission))->assertNotFound();
    $this->delete(route('guild-submission.destroy', $submission))->assertNotFound();
    $this->post(route('guild-submission-approval.store', fake()->uuid()))->assertNotFound();
    $this->delete(route('guild-submission.destroy', fake()->uuid()))->assertNotFound();
    $this->assertModelExists($submission);
    $this->assertDatabaseCount('guilds', 0);
})->with([false, true]);

it('finishes streaming an authorized icon even if its submission is rejected before the response is sent', function (): void {
    Storage::fake();
    $admin = User::factory()->create(['handle' => 'admin']);
    $submission = GuildSubmission::factory()->create(['icon_path' => 'guild-icons/pending.webp']);
    $this->actingAs($admin)->get(route('guild-submission-icon.show', $submission))->assertNotFound();
    Storage::put($submission->icon_path, 'image');

    $response = $this->actingAs($admin)->get(route('guild-submission-icon.show', $submission))->assertOk();
    resolve(DeleteGuildSubmission::class)->handle($submission);

    Storage::assertMissing($submission->icon_path);
    $response->assertStreamedContent('image')->assertHeader('Cache-Control', 'no-store, private');
});

it('sends nothing to the moderation when the guild details are invalid', function (): void {
    $this->mock(ModerationProvider::class)->shouldNotReceive('moderate', 'isHarmful');

    $this->actingAs(User::factory()->create(['handle' => 'admin']))->postJson(route('guild.store'), [
        'name' => 'Jobs', 'description' => '', 'emoji' => '💼',
    ])->assertUnprocessable()->assertJsonValidationErrors('description');
});

it('refuses an icon that passes the image rules but cannot be decoded', function (): void {
    $this->mock(ModerationProvider::class)->shouldNotReceive('moderate', 'isHarmful');
    $image = UploadedFile::fake()->image('icon.png', 80, 60);
    $png = file_get_contents($image->getRealPath());

    $this->actingAs(User::factory()->create(['handle' => 'admin']))->postJson(route('guild.store'), [
        'name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼',
        'images' => [UploadedFile::fake()->createWithContent('icon.png', mb_strcut($png, 0, 60, '8bit'))],
    ])->assertUnprocessable()->assertJsonValidationErrors(['images' => 'The icon must be a JPEG, PNG, or WebP image.']);

    $this->assertDatabaseCount('guilds', 0);
});

it('announces a paused guild only to its members and to the admins', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $member = User::factory()->create();
    $stranger = User::factory()->create();
    $guild = Guild::factory()->create();
    $guild->members()->attach($member, ['joined_at' => now()]);
    Event::fake([UsersUpdated::class]);

    $this->actingAs($admin)->delete(route('guild-moderator.destroy', $guild))->assertRedirect();

    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds && $event->userIds === [$member->id]);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Relationships && $event->userIds === [$member->id]);
    Event::assertNotDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => in_array($stranger->id, $event->userIds, true));
});

it('refuses new board submissions to a paused guild but still shows regular hashtag commits', function (string $route): void {
    $user = User::factory()->create(['handle' => 'admin']);
    Guild::factory()->create(['handle' => 'rust', 'user_id' => null]);

    $this->actingAs($user)->postJson(route($route), ['body' => 'Board post', 'guild_handle' => 'rust', 'scheduled_at' => now()->addHour()->toIso8601String()])
        ->assertUnprocessable()->assertJsonValidationErrors('guild_handle');
    $this->assertDatabaseCount('commits', 0);
    $this->assertDatabaseCount('scheduled_commits', 0);
    $this->post(route('commit.store'), ['body' => 'A regular #rust commit'])->assertSessionHasNoErrors();
    $this->get(route('guild.show', 'rust'))->assertOk()->assertInertia(fn ($page) => $page->has('commits', 1)->where('guild.is_paused', true));
})->with(['commit.store', 'commit-preview.store', 'scheduled-commit.store']);

it('refuses a board submission if the guild is paused after validation', function (string $route, string $action): void {
    $user = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['handle' => 'rust']);
    $this->app->beforeResolving($action, function () use ($guild): void {
        $guild->update(['user_id' => null]);
    });

    $this->actingAs($user)->post(route($route), ['body' => 'Board post', 'guild_handle' => 'rust', 'scheduled_at' => now()->addHour()->toIso8601String()])
        ->assertSessionHasErrors(['guild_handle' => 'This guild is paused until it has a moderator.']);
    $this->assertDatabaseCount('commits', 0);
    $this->assertDatabaseCount('scheduled_commits', 0);
})->with([
    ['commit.store', CreateCommit::class],
    ['scheduled-commit.store', CreateScheduledCommit::class],
]);

it('cascades an actual guild deletion without turning board posts into ordinary posts', function (): void {
    $guild = Guild::factory()->create();
    $board = Commit::factory()->for($guild)->create();
    $scheduled = ScheduledCommit::factory()->for($guild)->create();
    $regular = Commit::factory()->create(['body' => 'A regular #'.$guild->handle.' commit']);

    $guild->delete();

    $this->assertModelMissing($board);
    $this->assertModelMissing($scheduled);
    $this->assertModelExists($regular);
});
