<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

function embedUrl(Commit $commit, array $parameters = []): string
{
    return url(URL::signedRoute('commit-embed.show', ['commit' => $commit->id, ...$parameters], absolute: false));
}

it('keeps its signature behind a proxy that changes the scheme or the host', function (): void {
    $commit = Commit::factory()->create();
    $path = parse_url(embedUrl($commit, ['replies' => 3]), PHP_URL_PATH).'?'.parse_url(embedUrl($commit, ['replies' => 3]), PHP_URL_QUERY);

    $this->get('http://tunnel.example'.$path)->assertOk();
    $this->get('https://dev-null.sh'.$path)->assertOk();
});

it('shows a signed embed of an approved commit as a guest, in a frame, without cookies', function (): void {
    $commit = Commit::factory()->create(['body' => 'rgt 0.9 is out']);

    $this->actingAs(User::factory()->create());

    $response = $this->get(embedUrl($commit))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('embed/show')
            ->where('auth.user', null)
            ->where('focal.commit.id', $commit->id)
            ->where('focal.commit.body', 'rgt 0.9 is out')
            ->where('focal.tombstone', null)
            ->where('options.replies', 0)
            ->where('options.theme', 'auto')
            ->where('options.maxHeight', 640)
            // In milliseconds, like the app shares it, for the app's components the card renders.
            ->where('now', fn (mixed $now): bool => is_int($now)));

    expect($response->headers->get('X-Frame-Options'))->toBeNull()
        ->and($response->headers->get('Content-Security-Policy'))->toContain('frame-ancestors *')
        ->and($response->headers->getCookies())->toBeEmpty();
});

it('applies a chosen theme from the server, so it holds without JavaScript', function (): void {
    $commit = Commit::factory()->create();

    $this->get(embedUrl($commit, ['theme' => 'dark']))->assertSee('<html lang="en" class="dark"', false);
    $this->get(embedUrl($commit, ['theme' => 'light']))->assertDontSee('class="dark"', false);
    $this->get(embedUrl($commit))->assertDontSee('class="dark"', false);
});

it('opens its links outside the frame, even without JavaScript', function (): void {
    $commit = Commit::factory()->create();

    $this->get(embedUrl($commit))->assertSee('<base target="_blank">', false);
});

it('keeps every other page out of frames', function (): void {
    $this->get(route('home'))->assertHeader('X-Frame-Options', 'DENY');
});

it('refuses unsigned and tampered embeds', function (): void {
    $commit = Commit::factory()->create();

    $this->get(route('commit-embed.show', $commit))->assertForbidden();
    $this->get(embedUrl($commit, ['replies' => 3]).'&depth=99')->assertForbidden();
    $this->get(str_replace('replies=3', 'replies=10', embedUrl($commit, ['replies' => 3])))->assertForbidden();
});

it('falls back to defaults for values outside the allowed ones', function (): void {
    $commit = Commit::factory()->create();

    $this->get(embedUrl($commit, ['replies' => 7, 'depth' => 'deep', 'theme' => 'neon', 'max_height' => 12]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('options.replies', 0)
            ->where('options.depth', 3)
            ->where('options.theme', 'auto')
            ->where('options.maxHeight', 640));
});

it('shows the first replies at each level, down to the chosen depth', function (): void {
    $commit = Commit::factory()->create();
    $first = Commit::factory()->create(['parent_id' => $commit->id, 'created_at' => now()->subMinutes(3)]);
    $second = Commit::factory()->create(['parent_id' => $commit->id, 'created_at' => now()->subMinutes(2)]);
    Commit::factory()->create(['parent_id' => $commit->id, 'created_at' => now()->subMinute()]);
    $nested = Commit::factory()->create(['parent_id' => $first->id]);
    Commit::factory()->create(['parent_id' => $nested->id]);

    $this->get(embedUrl($commit, ['replies' => 1, 'depth' => 2]))
        ->assertInertia(fn ($page) => $page
            ->has('focal.replies', 1)
            ->where('focal.more', 2)
            ->where('focal.replies.0.commit.id', $first->id)
            ->where('focal.replies.0.replies.0.commit.id', $nested->id)
            ->has('focal.replies.0.replies.0.replies', 0)
            ->where('focal.replies.0.replies.0.more', 1));

    $this->get(embedUrl($commit, ['replies' => 3, 'depth' => 1]))
        ->assertInertia(fn ($page) => $page
            ->has('focal.replies', 3)
            ->where('focal.replies.1.commit.id', $second->id)
            ->has('focal.replies.0.replies', 0)
            ->where('focal.replies.0.more', 1));
});

it('leaves tombstones where commits cannot be shown, and keeps what follows them', function (): void {
    $commit = Commit::factory()->create();
    $deleted = Commit::factory()->create(['parent_id' => $commit->id, 'created_at' => now()->subMinutes(2)]);
    $survivor = Commit::factory()->create(['parent_id' => $deleted->id]);
    $deleted->delete();
    $optedOut = Commit::factory()->for(User::factory()->state(['allows_embeds' => false]))->create(['parent_id' => $commit->id, 'body' => 'secret', 'created_at' => now()->subMinute()]);

    $this->get(embedUrl($commit, ['replies' => 3, 'depth' => 2]))
        ->assertInertia(fn ($page) => $page
            ->where('focal.replies.0.tombstone', 'deleted')
            ->where('focal.replies.0.commit', null)
            ->where('focal.replies.0.replies.0.commit.id', $survivor->id)
            ->where('focal.replies.1.tombstone', 'unavailable')
            ->where('focal.replies.1.commit', null)
            ->where('focal.replies.1.id', $optedOut->id));
});

it('shows the embedded commit as a tombstone when it is not public, opted out or deleted', function (string $state, string $tombstone): void {
    $commit = match ($state) {
        'pending' => Commit::factory()->pending()->create(),
        'rejected' => Commit::factory()->rejected()->create(),
        'opted out' => Commit::factory()->for(User::factory()->state(['allows_embeds' => false]))->create(),
        'deleted' => tap(Commit::factory()->create())->delete(),
    };

    $this->get(embedUrl($commit))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('focal.tombstone', $tombstone)
            ->where('focal.commit', null));
})->with([
    ['pending', 'unavailable'],
    ['rejected', 'unavailable'],
    ['opted out', 'unavailable'],
    ['deleted', 'deleted'],
]);

it('shows the commit a commit quotes, like on /dev/null', function (): void {
    $quoted = Commit::factory()->create(['body' => 'the quoted one']);
    $commit = Commit::factory()->create(['quoted_commit_id' => $quoted->id]);

    $this->get(embedUrl($commit))
        ->assertInertia(fn ($page) => $page
            ->where('focal.commit.quoted_commit.id', $quoted->id)
            ->where('focal.commit.quoted_commit.body', 'the quoted one'));
});

it('leaves a quoted commit the card cannot show as unavailable, its content out of the page', function (string $state): void {
    $quoted = match ($state) {
        'opted out' => Commit::factory()->for(User::factory()->state(['allows_embeds' => false]))->create(['body' => 'secret']),
        'pending' => Commit::factory()->pending()->create(['body' => 'secret']),
    };
    $commit = Commit::factory()->create(['quoted_commit_id' => $quoted->id]);

    $this->get(embedUrl($commit))
        ->assertInertia(fn ($page) => $page
            ->where('focal.commit.quoted_commit_id', $quoted->id)
            ->where('focal.commit.quoted_commit', null))
        ->assertDontSee('secret');
})->with(['opted out', 'pending']);

it('leaves a deleted quoted commit as deleted', function (): void {
    $quoted = Commit::factory()->create(['body' => 'secret']);
    $commit = Commit::factory()->create(['quoted_commit_id' => $quoted->id]);
    $quoted->delete();

    $this->get(embedUrl($commit))
        ->assertInertia(fn ($page) => $page->whereNot('focal.commit.quoted_commit.deleted_at', null))
        ->assertDontSee('secret');
});

it('names no author in the links of a card whose commit is a tombstone', function (): void {
    $commit = Commit::factory()->for(User::factory()->state(['handle' => 'mira']))->create();
    $commit->delete();

    $this->get(embedUrl($commit))
        ->assertInertia(fn ($page) => $page->where('commitUrl', url('/')))
        ->assertDontSee('@mira', false);
});

it('shows the rest of the self-thread when asked', function (): void {
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->create();
    $next = Commit::factory()->for($author)->create(['parent_id' => $commit->id]);

    $this->get(embedUrl($commit, ['thread' => 1]))
        ->assertInertia(fn ($page) => $page
            ->where('options.thread', true)
            ->where('thread.0.commit.id', $next->id));

    $this->get(embedUrl($commit))->assertInertia(fn ($page) => $page->has('thread', 0));
});

it('tells the card the commit starts a thread, for its Show thread link', function (): void {
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->create();
    $next = Commit::factory()->for($author)->create(['parent_id' => $commit->id]);

    $this->get(embedUrl($commit))
        ->assertInertia(fn ($page) => $page
            ->has('thread', 0)
            ->where('focal.commit.continuation_id', $next->id));
});

it('leaves the next commit of the thread out of the replies when the thread is shown', function (): void {
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->create();
    $next = Commit::factory()->for($author)->create(['parent_id' => $commit->id, 'created_at' => now()->subMinutes(2)]);
    $reply = Commit::factory()->create(['parent_id' => $commit->id, 'created_at' => now()->subMinute()]);

    $this->get(embedUrl($commit, ['thread' => 1, 'replies' => 3]))
        ->assertInertia(fn ($page) => $page
            ->where('thread.0.commit.id', $next->id)
            ->has('focal.replies', 1)
            ->where('focal.replies.0.commit.id', $reply->id)
            ->where('focal.more', 0));

    $this->get(embedUrl($commit, ['replies' => 3]))
        ->assertInertia(fn ($page) => $page->has('focal.replies', 2));
});

it('shows every ancestor of an embedded reply, from the root', function (): void {
    $root = Commit::factory()->create();
    $middle = Commit::factory()->create(['parent_id' => $root->id]);
    $reply = Commit::factory()->create(['parent_id' => $middle->id]);

    $this->get(embedUrl($reply))
        ->assertInertia(fn ($page) => $page
            ->where('ancestors.0.commit.id', $root->id)
            ->where('ancestors.1.commit.id', $middle->id)
            ->where('focal.commit.id', $reply->id));
});

it('caps how many commits a card holds', function (): void {
    $commit = Commit::factory()->create();
    $parent = $commit;

    foreach (range(1, 60) as $level) {
        $parent = Commit::factory()->create(['parent_id' => $parent->id]);
    }

    $this->get(embedUrl($commit, ['replies' => 1, 'depth' => 'all']))
        ->assertInertia(function ($page): void {
            $count = 0;
            $node = $page->toArray()['props']['focal'];

            while ($node['replies'] !== []) {
                $count++;
                $node = $node['replies'][0];
            }

            expect($count)->toBeLessThan(50)
                ->and($node['more'])->toBe(1);
        });
});

it('rejects ids that are not uuids before any lookup', function (): void {
    $this->get('/embed/not-a-uuid')->assertNotFound();
});

it('limits how often one address loads embeds', function (): void {
    $commit = Commit::factory()->create();
    $url = embedUrl($commit);

    RateLimiter::for('embeds', fn () => Illuminate\Cache\RateLimiting\Limit::perMinute(2)->by('test'));

    $this->get($url)->assertOk();
    $this->get($url)->assertOk();
    $this->get($url)->assertTooManyRequests();
});
