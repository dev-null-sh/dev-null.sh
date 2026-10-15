<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;

it('renders an rss feed of the commits of followed users for the token owner', function (): void {
    $user = User::factory()->create(['username' => 'octocat', 'feed_token' => 'secret-feed-token']);
    $followed = User::factory()->create(['username' => 'hubot', 'name' => 'Hubot']);
    $user->following()->attach($followed);

    $older = Commit::factory()->for($followed)->create(['body' => 'Older commit', 'created_at' => now()->subHour()]);
    $newer = Commit::factory()->for($followed)->create(['body' => 'Newer commit']);
    Commit::factory()->for($user)->create(['body' => 'My own commit']);
    Commit::factory()->create(['body' => 'A stranger commit']);

    $response = $this->get(route('following-feed.show', 'secret-feed-token'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('max-age=300')
        ->and($response->getContent())->toStartWith('<?xml version="1.0" encoding="UTF-8"?>');

    $feed = simplexml_load_string((string) $response->getContent());

    expect($feed)->not->toBeFalse()
        ->and((string) $feed['version'])->toBe('2.0')
        ->and((string) $feed->channel->title)->toBe('Following @octocat on /dev/null')
        ->and((string) $feed->channel->link)->toBe(route('log.show', Log::Following))
        ->and((string) $feed->channel->children('atom', true)->link->attributes()['href'])->toBe(route('following-feed.show', 'secret-feed-token'))
        ->and((string) $feed->channel->lastBuildDate)->toBe($newer->created_at->toRssString())
        ->and($feed->channel->item)->toHaveCount(2);

    $first = $feed->channel->item[0];
    $second = $feed->channel->item[1];

    expect((string) $first->title)->toBe('@hubot: Newer commit')
        ->and((string) $first->link)->toBe(route('commit.show', [$followed, $newer]))
        ->and((string) $first->guid)->toBe(route('commit.show', [$followed, $newer]))
        ->and((string) $first->guid['isPermaLink'])->toBe('true')
        ->and((string) $first->children('dc', true)->creator)->toBe('Hubot (@hubot)')
        ->and((string) $first->pubDate)->toBe($newer->created_at->toRssString())
        ->and((string) $first->description)->toBe('Newer commit')
        ->and((string) $second->title)->toBe('@hubot: Older commit')
        ->and((string) $second->link)->toBe(route('commit.show', [$followed, $older]));
});

it('renders an empty rss feed when the token owner follows nobody', function (): void {
    User::factory()->create(['feed_token' => 'secret-feed-token']);
    Commit::factory()->create();

    $response = $this->get(route('following-feed.show', 'secret-feed-token'));

    $response->assertOk();

    $feed = simplexml_load_string((string) $response->getContent());

    expect($feed)->not->toBeFalse()
        ->and($feed->channel->item)->toHaveCount(0)
        ->and($feed->channel->lastBuildDate)->toHaveCount(0);
});

it('leaves pending, rejected, deleted commits and replies out of the rss feed', function (): void {
    $user = User::factory()->create(['feed_token' => 'secret-feed-token']);
    $followed = User::factory()->create();
    $user->following()->attach($followed);

    $approved = Commit::factory()->for($followed)->create(['body' => 'Approved']);
    Commit::factory()->for($followed)->pending()->create();
    Commit::factory()->for($followed)->rejected()->create();
    Commit::factory()->for($followed)->create()->delete();
    Commit::factory()->for($followed)->create(['parent_id' => $approved->id]);

    $response = $this->get(route('following-feed.show', 'secret-feed-token'));

    $feed = simplexml_load_string((string) $response->getContent());

    expect($feed->channel->item)->toHaveCount(1)
        ->and((string) $feed->channel->item[0]->description)->toBe('Approved');
});

it('escapes commits in the rss feed', function (): void {
    $user = User::factory()->create(['feed_token' => 'secret-feed-token']);
    $followed = User::factory()->create(['name' => 'Tom & "Jerry"', 'username' => 'tom']);
    $user->following()->attach($followed);

    $body = "<script>alert('xss')</script> & <b>bold</b>\n]]> done";
    Commit::factory()->for($followed)->create(['body' => $body]);

    $response = $this->get(route('following-feed.show', 'secret-feed-token'));

    $content = (string) $response->getContent();
    $feed = simplexml_load_string($content);

    expect($content)->not->toContain('<script>')
        ->and($feed)->not->toBeFalse()
        ->and((string) $feed->channel->item[0]->description)->toBe($body)
        ->and((string) $feed->channel->item[0]->title)->toBe("@tom: <script>alert('xss')</script> & <b>bold</b> ]]> done")
        ->and((string) $feed->channel->item[0]->children('dc', true)->creator)->toBe('Tom & "Jerry" (@tom)');
});

it('shortens long commits in rss feed item titles', function (): void {
    $user = User::factory()->create(['feed_token' => 'secret-feed-token']);
    $followed = User::factory()->create(['username' => 'hubot']);
    $user->following()->attach($followed);

    Commit::factory()->for($followed)->create(['body' => str_repeat('abcdefgh ', 20)]);

    $response = $this->get(route('following-feed.show', 'secret-feed-token'));

    $feed = simplexml_load_string((string) $response->getContent());

    expect((string) $feed->channel->item[0]->title)->toBe('@hubot: '.mb_rtrim(str_repeat('abcdefgh ', 9)).'…');
});

it('limits the rss feed to the latest 50 commits', function (): void {
    $user = User::factory()->create(['feed_token' => 'secret-feed-token']);
    $followed = User::factory()->create();
    $user->following()->attach($followed);

    Commit::factory()->for($followed)->count(51)->create();

    $response = $this->get(route('following-feed.show', 'secret-feed-token'));

    $feed = simplexml_load_string((string) $response->getContent());

    expect($feed->channel->item)->toHaveCount(50);
});

it('does not sign the token owner in', function (): void {
    $user = User::factory()->create(['feed_token' => 'secret-feed-token']);

    $this->get(route('following-feed.show', 'secret-feed-token'))->assertOk();

    $this->assertGuest();
    expect($user->refresh()->feed_token)->toBe('secret-feed-token');
});

it('returns not found for unknown feed tokens', function (): void {
    User::factory()->create(['feed_token' => 'secret-feed-token']);

    $this->get(route('following-feed.show', 'wrong-feed-token'))->assertNotFound();
});
