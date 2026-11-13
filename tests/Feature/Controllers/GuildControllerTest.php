<?php

declare(strict_types=1);

use App\Actions\CreateGuild;
use App\Actions\CreateGuildReport;
use App\Actions\DeleteUser;
use App\Contracts\Services\ModerationProvider;
use App\Enums\CommitStatus;
use App\Enums\Feature;
use App\Enums\Log;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Exceptions\GuildModeratorRemoved;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\GuildReport;
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

it('adds the bulletin board hashtag after validating the full writing budget', function (string $body, string $saved): void {
    $user = User::factory()->create(['handle' => 'admin']);
    Guild::factory()->create(['handle' => 'rust']);
    $payload = ['body' => $body, 'guild_handle' => 'rust'];

    $preview = $this->actingAs($user)->postJson(route('commit-preview.store'), $payload)->assertOk();
    $this->post(route('commit.store'), $payload)->assertSessionHasNoErrors();
    expect($user->commits()->sole()->body)->toBe($saved);
    $preview->assertJsonPath('segments', $user->commits()->sole()->toArray()['body_segments']);

    $this->post(route('scheduled-commit.store'), [...$payload, 'scheduled_at' => now()->addHour()->toIso8601String()])->assertSessionHasNoErrors();
    expect(ScheduledCommit::query()->sole()->body)->toBe($saved);
})->with([
    'full budget' => [str_repeat('x', 4000), str_repeat('x', 4000)."\n\n#rust"],
    'already present' => ['Learning #RUST!', 'Learning #RUST!'],
    'only inside code or a link' => ['`#rust` https://example.com/#rust', "`#rust` https://example.com/#rust\n\n#rust"],
]);

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
        ->component('guild/show')->where('canModerate', true)->where('guilds.0.name', 'Jo bs')
        ->where('guilds.0.icon_url', null)->missing('guilds.0.user_id')->missing('guilds.0.icon_path'));
    $this->actingAs(User::factory()->create(['handle' => 'other-admin']))
        ->get(route('guild.show', 'jobs'))->assertOk()->assertInertia(fn ($page) => $page->where('canModerate', false));
});

it('hides guild data and every guild endpoint from ordinary members', function (): void {
    $user = User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get('/search?q=%23jobs')->assertOk()->assertInertia(fn ($page) => $page->where('guilds', null));
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
    $this->get('/')->assertInertia(fn ($page) => $page->where('guilds', null));
    $this->get(route('guild.show', $guild->handle))->assertRedirectToRoute('home');
    $this->getJson(route('guild-icon.show', $guild->handle))->assertUnauthorized();
    $this->postJson(route('guild.store'), [])->assertUnauthorized();
    $this->putJson(route('guild.update', $guild->handle), [])->assertUnauthorized();
});

it('switches off the entire preview even for its admin creator', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['user_id' => $admin->id]);
    $this->actingAs($admin)->put(route('feature.update', Feature::Guilds), ['enabled' => false])->assertRedirect();

    $this->actingAs($admin)->get('/search?q=%23jobs')->assertInertia(fn ($page) => $page->where('guilds', null));
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

it('lets only the creator edit the icon and description and never the name handle or owner', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $other = User::factory()->create(['handle' => 'other-admin']);
    $guild = Guild::factory()->create(['user_id' => $admin->id, 'name' => 'Job Board', 'handle' => 'jobs']);
    $payload = ['description' => 'Remote jobs.', 'emoji' => '🌍', 'remove_icon' => false, 'name' => 'Changed', 'handle' => 'changed', 'user_id' => $other->id];

    $this->actingAs($other)->put(route('guild.update', 'jobs'), $payload)->assertNotFound();
    expect($guild->fresh()->description)->toBe($guild->description);
    $this->actingAs($admin)->put(route('guild.update', 'jobs'), $payload)->assertRedirectToRoute('guild.show', 'jobs');
    expect($guild->fresh())->description->toBe('Remote jobs.')->emoji->toBe('🌍')->name->toBe('Job Board')->handle->toBe('jobs')->user_id->toBe($admin->id);
    $this->putJson(route('guild.update', 'jobs'), ['description' => '', 'emoji' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['description', 'emoji', 'remove_icon']);
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
    $this->get(route('guild.show', 'jobs'))->assertInertia(fn ($page) => $page->where('guilds.0.icon_url', route('guild-icon.show', ['guild' => 'jobs', 'v' => $guild->updated_at->getTimestampMs()])));

    $this->put(route('guild.update', 'jobs'), ['description' => 'Hiring again.', 'emoji' => '💼', 'remove_icon' => false, 'images' => [UploadedFile::fake()->image('next.jpg')]])->assertRedirect();
    expect($guild->fresh()->icon_path)->not->toBe($original);
    $this->put(route('guild.update', 'jobs'), ['description' => 'Hiring again.', 'emoji' => '💻', 'remove_icon' => true])->assertRedirect();
    expect($guild->fresh()->icon_path)->toBeNull();
    $this->get(route('guild-icon.show', 'jobs'))->assertNotFound();
});

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

it('paginates matching hashtags without gaps caused by nonmatching candidates', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    Guild::factory()->create(['handle' => 'café_dev']);
    Commit::factory()->count(21)->create(['body' => '#CAFÉ_DEV']);
    Commit::factory()->count(22)->create(['body' => '`#café_dev` #café_developers']);
    $this->actingAs($admin)->get(route('guild.show', 'café_dev'))->assertOk()->assertInertia(fn ($page) => $page->has('commits', 20)->where('hasMore', true)->where('page', 1));
    $this->get(route('guild.show', ['guild' => 'café_dev', 'page' => 2]))->assertOk()->assertInertia(fn ($page) => $page->has('commits', 1)->where('hasMore', false)->where('page', 2));
    $this->getJson(route('guild.show', ['guild' => 'café_dev', 'page' => 0]))->assertUnprocessable()->assertJsonValidationErrors('page');
});

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
    $this->get(route('guild.show', $guild->handle))->assertInertia(fn ($page) => $page->missing('reports')->missing('guilds.0.details'));
    $this->get(route('guild-report.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('reports.data', 1)->where('reports.data.0.details', $report->details)->missing('reports.data.0.reporter_id'));
    $this->delete(route('guild-report.destroy', $report))->assertRedirect();
    expect($report->fresh()->dismissed_at)->not->toBeNull();
    $this->get(route('guild-report.index'))->assertInertia(fn ($page) => $page->has('reports.data', 0));
    $this->post(route('guild-report.store', $guild), ['details' => 'The updated description has a new issue.'])->assertRedirect();
    expect(GuildReport::query()->sole()->details)->toBe('The updated description has a new issue.')
        ->and($report->fresh()->dismissed_at)->toBeNull();
});

it('lets a moderator list and dismiss guild reports without touching guild deletion', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $this->actingAs($admin)->put(route('feature.update', Feature::Guilds), ['enabled' => true, 'admin_only' => false])->assertRedirect();

    config(['app.admins' => [], 'app.moderators' => ['maude']]);

    $owner = User::factory()->create();
    $moderator = User::factory()->create(['handle' => 'maude']);
    $guild = Guild::factory()->create(['user_id' => $owner->id]);
    $report = GuildReport::factory()->create(['guild_id' => $guild->id]);

    $this->actingAs($moderator)->get(route('guild-report.index'))->assertOk();
    $this->actingAs($moderator)->delete(route('guild-report.destroy', $report))->assertRedirect();
    expect($report->fresh()->dismissed_at)->not->toBeNull();

    $other = GuildReport::factory()->create(['guild_id' => $guild->id]);
    $this->actingAs($owner)->get(route('guild-report.index'))->assertNotFound();
    $this->actingAs($owner)->delete(route('guild-report.destroy', $other))->assertNotFound();
    expect($other->fresh()->dismissed_at)->toBeNull();
});

it('removes the moderator and guild but not commits and forbids every removed moderator from reclaiming it', function (): void {
    Storage::fake();
    $owner = User::factory()->create(['handle' => 'admin']);
    $other = User::factory()->create(['handle' => 'other-admin']);
    $guild = Guild::factory()->create(['user_id' => $owner->id, 'handle' => 'jobs', 'icon_path' => 'guild-icons/removed.webp']);
    $guild->members()->attach($owner, ['joined_at' => now(), 'muted_at' => now()]);
    $guild->muters()->attach($other, ['muted_at' => now()]);
    Storage::put($guild->icon_path, 'image');
    $commit = Commit::factory()->create(['body' => '#jobs opportunity']);
    resolve(CreateGuildReport::class)->handle($other, $guild, 'Unrelated description.');
    $this->actingAs($other)->delete(route('guild.destroy', $guild))->assertRedirectToRoute('search.index', ['q' => '#jobs']);
    $this->assertModelMissing($guild);
    $this->assertModelExists($commit);
    $this->assertDatabaseCount('guild_reports', 0);
    $this->assertDatabaseCount('guild_preferences', 0);
    Storage::assertMissing('guild-icons/removed.webp');
    $this->get(route('guild.show', 'jobs'))->assertNotFound();
    $this->get('/search?q=%23jobs', ['X-Inertia-Except-Once-Props' => 'guilds'])->assertInertia(fn ($page) => $page->has('guilds', 0));
    $payload = ['handle' => 'JOBS', 'name' => 'Jobs', 'description' => 'Real jobs.', 'emoji' => '💼'];
    $this->actingAs($owner)->postJson(route('guild.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('handle');
    $this->postJson(route('guild.store'), array_diff_key($payload, ['handle' => true]))->assertUnprocessable()->assertJsonValidationErrors('handle');
    expect(fn () => resolve(CreateGuild::class)->handle($owner, 'JOBS', 'Jobs', 'Real jobs.', '💼', null))->toThrow(GuildModeratorRemoved::class);
    $this->actingAs($other)->post(route('guild.store'), $payload)->assertRedirectToRoute('guild.show', 'jobs');
    $replacement = Guild::query()->sole();
    expect($replacement->user_id)->toBe($other->id)->and($replacement->id)->not->toBe($guild->id);
    $this->delete(route('guild.destroy', $guild))->assertNotFound();
    $this->delete(route('guild.destroy', $replacement))->assertRedirect();
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
    $this->delete(route('guild.destroy', $guild))->assertNotFound();
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
            if (! $changed && $query->connectionName === 'guild_reader' && str_starts_with($query->sql, 'select "id", "body"')) {
                $changed = true;
                DB::connection('guild_writer')->table('commits')->where('id', $commits->last()->id)->delete();
                Commit::factory()->connection('guild_writer')->for($user)->create(['body' => 'A concurrent #rust commit']);
            }
        });

        $page = resolve(LogCommitsQuery::class)->paginate(Log::Following, $user);

        expect($changed)->toBeTrue()->and($page->getCollection()->modelKeys())->toBe([$plain->id]);
        expect(DB::transactionLevel())->toBe(0);
    } finally {
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
    $this->get(route('guild.show', 'rust'))->assertInertia(fn ($page) => $page->where('commits.0.id', $reply->id));
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
        ->component('guild/index')->where('guilds.0.handle', 'rust')->where('guilds.0.member_count', 1)
        ->where('guilds.1.is_joined', false)->where('guilds.1.is_muted', false));
    $this->put(route('guild-membership.update', $jobs), ['joined' => true])->assertRedirect();
    $this->put(route('guild-membership.update', $jobs), ['joined' => true])->assertRedirect();
    $this->put(route('guild-mute.update', $jobs), ['muted' => true])->assertRedirect();
    $this->get(route('guild.index'))->assertInertia(fn ($page) => $page
        ->where('guilds.0.handle', 'jobs')->where('guilds.0.member_count', 1)
        ->where('guilds.0.is_joined', true)->where('guilds.0.is_muted', true));
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

it('announces a membership only to the members of the guild and to the person who changed it', function (bool $joined): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $member = User::factory()->create();
    User::factory()->create();
    $guild = Guild::factory()->create();
    $guild->members()->attach($member, ['joined_at' => now()]);
    Event::fake([UsersUpdated::class]);

    $this->actingAs($admin)->put(route('guild-membership.update', $guild), ['joined' => $joined])->assertRedirect();

    Event::assertDispatchedTimes(UsersUpdated::class, 2);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds && $event->userIds === [$member->id, $admin->id]);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Relationships && $event->userIds === [$admin->id]);
})->with([true, false]);

it('announces an edit of a guild only to its members and to its creator', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $member = User::factory()->create();
    User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $admin->id]);
    $guild->members()->attach($member, ['joined_at' => now()]);
    Event::fake([UsersUpdated::class]);

    $this->actingAs($admin)->put(route('guild.update', $guild->handle), ['description' => 'Remote jobs.', 'emoji' => '💼', 'remove_icon' => false])->assertRedirect();

    Event::assertDispatchedTimes(UsersUpdated::class, 1);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds && $event->userIds === [$member->id, $admin->id]);
});

it('lets a person create one hundred guilds and refuses the next one', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    Guild::factory()->count(99)->create(['user_id' => $admin->id]);
    Guild::factory()->count(3)->create();
    $payload = ['description' => 'A public group.', 'emoji' => '💻'];

    $this->actingAs($admin)->postJson(route('guild.store'), ['name' => 'Hundredth', ...$payload])->assertRedirectToRoute('guild.show', 'hundredth');
    $this->postJson(route('guild.store'), ['name' => 'Next', ...$payload])->assertUnprocessable()->assertJsonValidationErrors(['handle' => 'You can create up to 100 guilds.']);

    $this->assertDatabaseMissing('guilds', ['handle' => 'next']);
});

it('refuses a guild whose name description or icon the moderation flags', function (array $scores, bool $harmful): void {
    Storage::fake();
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->once()
        ->withArgs(fn (string $text, array $icons): bool => $text === "Jobs\n\nHiring." && count($icons) === 1 && str_starts_with($icons[0], 'data:image/webp;base64,'))
        ->andReturn($scores);
    $moderation->shouldReceive('isHarmful')->with("Jobs\n\nHiring.")->andReturn($harmful);

    $this->actingAs(User::factory()->create(['handle' => 'admin']))->postJson(route('guild.store'), [
        'name' => 'Jobs', 'description' => 'Hiring.', 'emoji' => '💼', 'images' => [UploadedFile::fake()->image('icon.png', 80, 60)],
    ])->assertUnprocessable()->assertJsonValidationErrors(['description' => 'This guild cannot be published. Change its name, its description or its icon.']);

    $this->assertDatabaseCount('guilds', 0);
    expect(Storage::allFiles())->toBe([]);
})->with([
    'a flagged category' => [['sexual' => 0.4], false],
    'harmful text' => [[], true],
]);

it('refuses an edit of a guild that the moderation flags and keeps the guild as it was', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['user_id' => $admin->id, 'handle' => 'jobs', 'name' => 'Jobs', 'description' => 'Hiring.']);
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->once()->with("Jobs\n\nFlagged words.", [])->andReturn(['harassment' => 0.7]);

    $this->actingAs($admin)->putJson(route('guild.update', 'jobs'), ['description' => 'Flagged words.', 'emoji' => '💼', 'remove_icon' => false])
        ->assertUnprocessable()->assertJsonValidationErrors('description');

    expect($guild->fresh()->description)->toBe('Hiring.');
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

it('announces the removal of a guild only to its members and to the admins', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $member = User::factory()->create();
    $stranger = User::factory()->create();
    $guild = Guild::factory()->create();
    $guild->members()->attach($member, ['joined_at' => now()]);
    Event::fake([UsersUpdated::class]);

    $this->actingAs($admin)->delete(route('guild.destroy', $guild))->assertRedirect();

    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds && $event->userIds === [$member->id]);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Relationships && $event->userIds === [$member->id]);
    Event::assertNotDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => in_array($stranger->id, $event->userIds, true));
});
