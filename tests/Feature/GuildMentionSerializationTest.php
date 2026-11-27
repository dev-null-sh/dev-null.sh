<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\RemotesFrom;
use App\Http\Resources\CommitResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\ScheduledCommitResource;
use App\Http\Resources\ShownGuildMentions;
use App\Http\Resources\ShownStreams;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Poll;
use App\Models\ScheduledCommit;
use App\Models\User;
use App\Services\CommitTranslations;
use App\Services\Features;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;

it('batches cards with their loaded quotes replies and translations and gives each body only its own badges', function (): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    request()->setUserResolver(fn (): User => $viewer);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    foreach (['rust', 'php', 'laravel', 'bonjour'] as $handle) {
        Guild::factory()->create(['handle' => $handle]);
    }

    $quote = Commit::factory()->create(['body' => '#php']);
    $root = Commit::factory()->for($quote, 'quotedCommit')->create(['body' => '#rust', 'created_at' => now()->subMinute()]);
    Commit::factory()->for($root, 'parent')->create(['body' => '#laravel']);
    $other = Commit::factory()->create(['body' => 'No hashtags']);
    Cache::put(resolve(CommitTranslations::class)->key($root), [
        'source_language' => 'de', 'translations' => ['en' => '#bonjour `#php`'], 'expires_at' => now()->addWeek()->getTimestamp(),
    ], 604800);
    $commits = Commit::query()->whereKey([$root->id, $other->id])->withCardDataFor($viewer)->withVisibleReplyCount($viewer)->withReplyPreviews($viewer)->get();
    resolve(CommitTranslations::class)->attach($commits);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $shown = collect(ShownStreams::commits($commits))->keyBy('id');

    expect($shown[$root->id]['guild_mentions'])->toHaveKeys(['rust'])->toHaveCount(1)
        ->and($shown[$root->id]['quoted_commit']['guild_mentions'])->toHaveKeys(['php'])->toHaveCount(1)
        ->and($shown[$root->id]['reply_preview']['data'][0]['guild_mentions'])->toHaveKeys(['laravel'])->toHaveCount(1)
        ->and($shown[$root->id]['translation']['translated']['guild_mentions'])->toHaveKeys(['bonjour'])->toHaveCount(1)
        ->and($shown[$other->id]['guild_mentions'])->toBe([])
        ->and(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "guilds"')))->toHaveCount(1);
});

it('batches paginated cards without losing their cursor attributes', function (): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    request()->setUserResolver(fn (): User => $viewer);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Guild::factory()->create(['handle' => 'rust']);
    Commit::factory()->count(3)->create(['body' => '#rust']);
    $page = Commit::query()->withCardDataFor($viewer)->orderBy('id')->cursorPaginate(2);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $shown = ShownStreams::page($page)->toArray();

    expect($shown['data'])->toHaveCount(2)
        ->and($shown['data'][0]['guild_mentions'])->toHaveKey('rust')
        ->and($shown['next_cursor'])->not->toBeNull()
        ->and(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "guilds"')))->toHaveCount(1);

    $next = Commit::query()->withCardDataFor($viewer)->orderBy('id')->cursorPaginate(2, cursor: $page->nextCursor());
    $nextShown = ShownStreams::page($next)->toArray();

    expect($nextShown['data'])->toHaveCount(1)
        ->and($nextShown['data'][0]['guild_mentions'])->toHaveKey('rust')
        ->and($nextShown['data'][0]['id'])->not->toBeIn(array_column($shown['data'], 'id'))
        ->and($nextShown['next_cursor'])->toBeNull();
});

it('does not expose badge metadata from deleted cards or disabled guilds', function (): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    request()->setUserResolver(fn (): User => $viewer);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Guild::factory()->create(['handle' => 'rust']);
    $deleted = Commit::factory()->create(['body' => '#rust']);
    $deleted->delete();

    $visible = Commit::factory()->create(['body' => '#rust']);

    expect(ShownStreams::commit($deleted))->not->toHaveKeys(['body', 'guild_mentions']);
    resolve(Features::class)->update(Feature::Guilds, false);
    expect(ShownStreams::commit($visible)['guild_mentions'])->toBe([]);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    request()->setUserResolver(fn (): null => null);
    expect(ShownStreams::commit($visible)['guild_mentions'])->toBe([]);
});

it('does not disable badge enrichment on reused resources', function (bool $collection): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    request()->setUserResolver(fn (): User => $viewer);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Guild::factory()->create(['handle' => 'rust']);
    $message = Message::factory()->create(['body' => '#rust']);
    $resource = MessageResource::make($message);
    $batch = MessageResource::collection(collect([$resource]));

    $shown = ShownGuildMentions::of(['message' => $collection ? $batch : $resource], $viewer);

    expect($shown['message'][$collection ? 0 : 'guild_mentions'])->not->toBeEmpty()
        ->and($resource->withGuildMentions)->toBeTrue()
        ->and($batch->withGuildMentions)->toBeTrue()
        ->and($resource->resolve()['guild_mentions'])->toHaveKey('rust');
})->with(['single' => false, 'collection' => true]);

it('omits unloaded notification actors when serialized standalone', function (): void {
    $notification = Notification::factory()->create();

    $shown = NotificationResource::make($notification)->resolve();

    expect($shown)->not->toHaveKey('actors')
        ->and($shown['id'])->toBe($notification->id);
});

it('keeps a successful vote response when optional badge lookup fails', function (): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    $poll = Poll::factory()->withOptions('#rust', 'Other')->create();
    Exceptions::fake();
    $failing = true;
    DB::connection()->beforeExecuting(function (string $query, array $bindings) use (&$failing): void {
        throw_if($failing && str_contains($query, 'from "guilds"'), QueryException::class, 'pgsql', $query, $bindings, new PDOException('Injected badge lookup failure'));
    });

    try {
        $this->postJson(route('commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id])
            ->assertOk()->assertJsonPath('poll.total_votes', 1)->assertJsonPath('poll.options.0.guild_mentions', []);
    } finally {
        $failing = false;
    }

    $this->assertDatabaseHas('poll_votes', ['user_id' => $viewer->id, 'poll_option_id' => $poll->options[0]->id]);
    Exceptions::assertReported(QueryException::class);
});

it('returns the first private message even when its optional badge lookup fails', function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
    $viewer = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes(RemotesFrom::Following)->create();
    $this->actingAs($viewer);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    $payload = ['body' => '#rust', 'client_id' => Str::uuid()->toString()];
    Exceptions::fake();
    $failing = true;
    DB::connection()->beforeExecuting(function (string $query, array $bindings) use (&$failing): void {
        throw_if($failing && str_contains($query, 'from "guilds"'), QueryException::class, 'pgsql', $query, $bindings, new PDOException('Injected badge lookup failure'));
    });

    try {
        $this->postJson(route('conversation-message.store', $partner), $payload)
            ->assertCreated()->assertJsonPath('message.body', '#rust')->assertJsonPath('message.guild_mentions', []);
    } finally {
        $failing = false;
    }

    expect(Message::query()->sole()->body)->toBe('#rust');
    Exceptions::assertReported(QueryException::class);
    $this->postJson(route('conversation-message.store', $partner), $payload)->assertForbidden();
});

it('does not swallow programming errors in optional badge enrichment', function (): void {
    $viewer = User::factory()->create();
    $texts = (function (): Generator {
        yield '#rust';
        throw new LogicException('Not a database failure');
    })();

    expect(fn (): array => ShownGuildMentions::get($texts, $viewer))->toThrow(LogicException::class, 'Not a database failure');
});

it('returns a newly scheduled API commit even when its optional badge lookup fails', function (): void {
    $viewer = User::factory()->create();
    $keyPair = apiKeyPairFor($viewer);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Exceptions::fake();
    $failing = true;
    DB::connection()->beforeExecuting(function (string $query, array $bindings) use (&$failing): void {
        throw_if($failing && str_contains($query, 'from "guilds"'), QueryException::class, 'pgsql', $query, $bindings, new PDOException('Injected badge lookup failure'));
    });

    try {
        signedApiRequest($keyPair, 'POST', route('api-scheduled-commit.store'), [
            'body' => '#rust', 'scheduled_at' => now()->addHour()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.body', '#rust')->assertJsonPath('data.guild_mentions', []);
    } finally {
        $failing = false;
    }

    expect(ScheduledCommit::query()->sole()->body)->toBe('#rust');
    Exceptions::assertReported(QueryException::class);
});

it('keeps database failures visible for authoritative guild lookups', function (string $url): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Exceptions::fake();
    $failing = true;
    DB::connection()->beforeExecuting(function (string $query, array $bindings) use (&$failing): void {
        throw_if($failing && str_contains($query, 'from "guilds"'), QueryException::class, 'pgsql', $query, $bindings, new PDOException('Injected exact lookup failure'));
    });

    try {
        $this->getJson($url)->assertInternalServerError();
    } finally {
        $failing = false;
    }

    Exceptions::assertReported(QueryException::class);
})->with(['/search/guilds?handles[]=rust', '/search?q=%23rust']);

it('does not turn mutation query failures into successful vote responses', function (): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    $poll = Poll::factory()->withOptions('#rust', 'Other')->create();
    Exceptions::fake();
    $failing = true;
    DB::connection()->beforeExecuting(function (string $query, array $bindings) use (&$failing): void {
        throw_if($failing && str_contains($query, 'insert into "poll_votes"'), QueryException::class, 'pgsql', $query, $bindings, new PDOException('Injected vote write failure'));
    });

    try {
        $this->postJson(route('commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id])->assertInternalServerError();
    } finally {
        $failing = false;
    }

    $this->assertDatabaseEmpty('poll_votes');
    Exceptions::assertReported(QueryException::class);
});

it('batches message and reply ghost badges without altering encrypted stored bodies', function (): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    request()->setUserResolver(fn (): User => $viewer);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Guild::factory()->create(['handle' => 'rust']);
    Guild::factory()->create(['handle' => 'php']);
    $reply = Message::factory()->create(['body' => '#php']);
    $messages = Message::factory()->count(3)->for($reply, 'replyTo')->create(['body' => '#rust']);
    $messages->load(Message::SHOWN_WITH);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $shown = MessageResource::collection($messages)->resolve();

    expect($shown[0]['guild_mentions'])->toHaveKeys(['rust'])->toHaveCount(1)
        ->and($shown[0]['reply_to']['guild_mentions'])->toHaveKeys(['php'])->toHaveCount(1)
        ->and(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "guilds"')))->toHaveCount(1)
        ->and($messages->first()->getRawOriginal('body'))->not->toContain('#rust');
});

it('batches scheduled bodies but never reads badges from submitted bodies', function (): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    request()->setUserResolver(fn (): User => $viewer);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Guild::factory()->create(['handle' => 'rust']);
    Guild::factory()->create(['handle' => 'hidden']);
    $waiting = ScheduledCommit::factory()->count(3)->for($viewer)->create(['body' => '#rust']);
    $submitted = ScheduledCommit::factory()->for($viewer)->create(['body' => '#hidden', 'submitted_at' => now()]);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $shown = ScheduledCommitResource::collection($waiting->push($submitted))->resolve();

    expect($shown[0]['guild_mentions'])->toHaveKey('rust')
        ->and($shown[3]['body'])->toBeNull()
        ->and($shown[3])->not->toHaveKey('guild_mentions');
    $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "guilds"'));
    expect($queries)->toHaveCount(1)->and($queries->sole()['bindings'])->toBe(['rust']);
});

it('batches signed API commit resources including loaded quotes', function (): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    request()->setUserResolver(fn (): User => $viewer);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Guild::factory()->create(['handle' => 'rust']);
    Guild::factory()->create(['handle' => 'php']);
    $quote = Commit::factory()->create(['body' => '#php']);
    $commits = Commit::factory()->count(3)->for($quote, 'quotedCommit')->create(['body' => '#rust']);
    $commits->load(['author', 'quotedCommit.author']);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $shown = CommitResource::collection($commits)->resolve();

    expect($shown[0]['guild_mentions'])->toHaveKeys(['rust'])->toHaveCount(1)
        ->and($shown[0]['quoted_commit']['guild_mentions'])->toHaveKeys(['php'])->toHaveCount(1)
        ->and(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "guilds"')))->toHaveCount(1);
});

it('returns guild badges for preview and translated text at their HTTP boundaries', function (): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Guild::factory()->create(['handle' => 'rust']);
    $commit = Commit::factory()->create(['body' => 'Guten Morgen']);
    Cache::put(resolve(CommitTranslations::class)->key($commit), [
        'source_language' => 'de', 'translations' => ['en' => '#RUST `#unknown`'], 'expires_at' => now()->addWeek()->getTimestamp(),
    ], 604800);

    $this->postJson(route('commit-preview.store'), ['body' => '#RUST `#unknown`'])
        ->assertOk()->assertJsonPath('guild_mentions.rust.handle', 'rust')->assertJsonCount(1, 'guild_mentions');
    $this->getJson(route('commit-translation.show', [$commit, 'language' => 'en']))
        ->assertOk()->assertJsonPath('translation.translated.guild_mentions.rust.handle', 'rust')->assertJsonCount(1, 'translation.translated.guild_mentions');
});

it('batches poll option labels with card bodies and preserves hidden vote counts', function (bool $api, int $count): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    request()->setUserResolver(fn (): User => $viewer);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    foreach (['rust', 'php', 'code'] as $handle) {
        Guild::factory()->create(['handle' => $handle]);
    }

    $commits = Commit::factory()->count($count)->create(['body' => '#rust']);
    $commits->each(fn (Commit $commit): Poll => Poll::factory()->for($commit)->withOptions('#PHP', '`#code` and #unknown')->create());

    $cards = Commit::query()->whereKey($commits->modelKeys())->withCardDataFor($viewer)->get();
    DB::enableQueryLog();
    DB::flushQueryLog();

    $shown = $api ? CommitResource::collection($cards)->resolve() : ShownStreams::commits($cards);

    expect($shown)->toHaveCount($count)
        ->and($shown[0]['guild_mentions'])->toHaveKeys(['rust'])->toHaveCount(1)
        ->and($shown[0]['poll']['options'][0]['guild_mentions'])->toHaveKeys(['php'])->toHaveCount(1)
        ->and($shown[0]['poll']['options'][1]['guild_mentions'])->toBe([])
        ->and($shown[0]['poll']['total_votes'])->toBeNull()
        ->and($shown[0]['poll']['options'][0]['votes_count'])->toBeNull();
    $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "guilds"'));
    expect($queries)->toHaveCount(1)
        ->and($queries->sole()['bindings'])->toBe(['rust', 'php', 'unknown']);
})->with(['web' => false, 'API' => true])->with([2, 8]);

it('keeps poll option badges when voting or refreshing replaces the poll', function (bool $vote): void {
    $viewer = User::factory()->create();
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Guild::factory()->create(['handle' => 'php']);
    $poll = Poll::factory()->withOptions('#PHP', 'Something else')->create();
    $this->actingAs($viewer);

    $response = $vote
        ? $this->postJson(route('commit-poll-vote.store', $poll->commit), ['option' => $poll->options[0]->id])
        : $this->getJson(route('commit-poll.show', $poll->commit));

    $response->assertOk()->assertJsonPath('poll.options.0.guild_mentions.php.handle', 'php')
        ->assertJsonPath('poll.options.1.guild_mentions', [])
        ->assertJsonPath('poll.total_votes', $vote ? 1 : null);
})->with(['vote' => true, 'refresh' => false]);

it('puts private message badges in the first thread and inbox paint', function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
    $viewer = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Guild::factory()->create(['handle' => 'rust']);
    sendChatMessage($partner, $viewer, '#RUST');

    $this->actingAs($viewer)->get(route('conversation.show', $partner))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('thread.messages.0.guild_mentions.rust.handle', 'rust')
            ->where('inbox.conversations.0.last_message.guild_mentions.rust.handle', 'rust'));
});
