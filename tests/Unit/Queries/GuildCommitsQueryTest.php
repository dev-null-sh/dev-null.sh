<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Queries\GuildCommitsQuery;
use App\Services\Features;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

function muteGuildFor(User $user, Guild $guild): void
{
    DB::table('guild_preferences')->insert(['guild_id' => $guild->id, 'user_id' => $user->id, 'muted_at' => now()]);
}

it('rejects the commits posted in a muted guild or tagging it, and keeps the others', function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $viewer = User::factory()->create();
    $muted = Guild::factory()->create(['handle' => 'rust']);
    $other = Guild::factory()->create(['handle' => 'php']);
    muteGuildFor($viewer, $muted);
    $postedInMuted = Commit::factory()->create(['guild_id' => $muted->id, 'body' => 'Board post']);
    $taggingMuted = Commit::factory()->create(['body' => 'Learning #Rust today']);
    $postedInOther = Commit::factory()->create(['guild_id' => $other->id, 'body' => 'About #rust on another board']);
    $inCode = Commit::factory()->create(['body' => 'Only `#rust` in code']);
    $plain = Commit::factory()->create(['body' => 'No tag']);

    $kept = resolve(GuildCommitsQuery::class)->rejectMuted(new Collection([$postedInMuted, $taggingMuted, $postedInOther, $inCode, $plain]), $viewer);

    expect($kept->modelKeys())->toBe([$postedInOther->id, $inCode->id, $plain->id]);
});

it('keeps every commit for a viewer who muted no guild', function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $commit = Commit::factory()->create(['body' => 'Learning #rust today']);

    $kept = resolve(GuildCommitsQuery::class)->rejectMuted(new Collection([$commit]), User::factory()->create());

    expect($kept->modelKeys())->toBe([$commit->id]);
});

it('keeps every commit for a guest', function (): void {
    $commit = Commit::factory()->create(['body' => 'Learning #rust today']);

    $kept = resolve(GuildCommitsQuery::class)->rejectMuted(new Collection([$commit]), null);

    expect($kept->modelKeys())->toBe([$commit->id]);
});

it('keeps every commit while the guilds feature is off', function (): void {
    resolve(Features::class)->update(Feature::Guilds, false);
    $viewer = User::factory()->create();
    $guild = Guild::factory()->create(['handle' => 'rust']);
    muteGuildFor($viewer, $guild);
    $commit = Commit::factory()->create(['guild_id' => $guild->id]);

    $kept = resolve(GuildCommitsQuery::class)->rejectMuted(new Collection([$commit]), $viewer);

    expect($kept->modelKeys())->toBe([$commit->id]);
});

it('reads no preference when there is no commit to check', function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $viewer = User::factory()->create();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $kept = resolve(GuildCommitsQuery::class)->rejectMuted(new Collection, $viewer);

    expect($kept)->toBeEmpty()
        ->and(DB::getQueryLog())->toBeEmpty();

    DB::disableQueryLog();
});

it('reads the preferences once and streams no commit when it rejects muted commits', function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $viewer = User::factory()->create();
    muteGuildFor($viewer, Guild::factory()->create(['handle' => 'rust']));
    $commits = Commit::factory()->count(3)->create(['body' => 'Learning #rust today']);
    resolve(Features::class)->available(Feature::Guilds, $viewer);
    DB::flushQueryLog();
    DB::enableQueryLog();

    $kept = resolve(GuildCommitsQuery::class)->rejectMuted(new Collection($commits->all()), $viewer);

    expect($kept)->toBeEmpty()
        ->and(DB::getQueryLog())->toHaveCount(1);

    DB::disableQueryLog();
});

it('reads the root commits of a guild in one query however many commits carry a hashtag', function (): void {
    $guild = Guild::factory()->create(['handle' => 'rust']);
    Commit::factory()->count(105)->create(['body' => 'Reading #php today', 'created_at' => now()]);
    $tagged = Commit::factory()->create(['body' => 'Learning #rust today', 'created_at' => now()->subDay()]);
    DB::flushQueryLog();
    DB::enableQueryLog();

    $ids = resolve(GuildCommitsQuery::class)->rootIds($guild, User::factory()->create())->all();

    $reads = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '"roots"'));

    expect($ids)->toBe([$tagged->id])
        ->and($reads)->toHaveCount(1);

    DB::disableQueryLog();
});

it('finds the root commits that tag a guild in any case, and no other mention of its handle', function (): void {
    $guild = Guild::factory()->create(['handle' => 'rust']);
    $upperCase = Commit::factory()->create(['body' => 'Learning #RUST today', 'created_at' => now()]);
    $posted = Commit::factory()->create(['guild_id' => $guild->id, 'body' => 'Board post', 'created_at' => now()->subMinute()]);
    Commit::factory()->create(['body' => 'Learning #rustacean today']);
    Commit::factory()->create(['body' => 'Only `#rust` in code']);
    Commit::factory()->create(['body' => 'See https://example.com/page#rust']);

    $ids = resolve(GuildCommitsQuery::class)->rootIds($guild, User::factory()->create())->all();

    expect($ids)->toBe([$upperCase->id, $posted->id]);
});

it('finds a root commit that tags a guild in another case of its alphabet', function (string $handle, string $body): void {
    $guild = Guild::factory()->create(['handle' => $handle]);
    $tagged = Commit::factory()->create(['body' => $body]);

    $ids = resolve(GuildCommitsQuery::class)->rootIds($guild, User::factory()->create())->all();

    expect($ids)->toBe([$tagged->id]);
})->with([
    'accent' => ['école', 'Back to #ÉCOLE today'],
    'underscore' => ['café_dev', 'At #CAFÉ_DEV today'],
    'sharp s' => ['straße', 'On #STRAßE today'],
    'final sigma' => ['σας', 'Hello #ΣΑΣ today'],
    'final sigma before a full stop' => ['σας', 'Hello #ΣΑΣ.next'],
    'final sigma before an apostrophe' => ['σας', "Hello #ΣΑΣ's team"],
]);

it('gives a root commit once however many of its replies tag the guild', function (): void {
    $guild = Guild::factory()->create(['handle' => 'rust']);
    $root = Commit::factory()->create(['body' => 'A thread about #rust']);
    Commit::factory()->count(3)->for($root, 'parent')->create(['root_id' => $root->id, 'body' => 'More #rust here']);

    $ids = resolve(GuildCommitsQuery::class)->rootIds($guild, User::factory()->create())->all();

    expect($ids)->toBe([$root->id]);
});

it('keeps the commits of a guild that the viewer joined and did not mute', function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $viewer = User::factory()->create();
    $joined = Guild::factory()->create(['handle' => 'rust']);
    muteGuildFor($viewer, Guild::factory()->create(['handle' => 'php']));
    DB::table('guild_preferences')->insert(['guild_id' => $joined->id, 'user_id' => $viewer->id, 'joined_at' => now()]);
    $posted = Commit::factory()->create(['guild_id' => $joined->id]);
    $tagged = Commit::factory()->create(['body' => 'Learning #rust today']);

    $kept = resolve(GuildCommitsQuery::class)->rejectMuted(new Collection([$posted, $tagged]), $viewer);

    expect($kept->modelKeys())->toBe([$posted->id, $tagged->id]);
});

it('keeps the commits of a guild that only a different person muted', function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $guild = Guild::factory()->create(['handle' => 'rust']);
    muteGuildFor(User::factory()->create(), $guild);
    $commit = Commit::factory()->create(['guild_id' => $guild->id]);

    $kept = resolve(GuildCommitsQuery::class)->rejectMuted(new Collection([$commit]), User::factory()->create());

    expect($kept->modelKeys())->toBe([$commit->id]);
});
