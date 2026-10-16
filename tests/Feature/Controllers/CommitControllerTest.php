<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\Log;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('may create a commit', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Hello, devnull!',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following);

    expect($user->commits()->sole()->body)->toBe('Hello, devnull!');
});

it('flashes the created commit', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Initial commit']);

    $commit = $user->commits()->sole();

    $response->assertInertiaFlash('committed', [
        'id' => $commit->id,
        'body' => 'Initial commit',
    ]);
});

it('does not count the authenticated user relations when redirecting after a commit', function (): void {
    $user = User::factory()->create();

    DB::enableQueryLog();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Initial commit',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following);

    expect(array_column(DB::getQueryLog(), 'query'))
        ->not->toBeEmpty()
        ->each->not->toContain('count(*)');
});

it('requires a body', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => '   ',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors('body');

    expect(Commit::query()->count())->toBe(0);
});

it('requires the body to be at least 3 characters', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => '  ab  ',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors('body');

    expect(Commit::query()->count())->toBe(0);
});

it('requires the body to be at most 280 characters', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => str_repeat('a', 281),
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors('body');

    expect(Commit::query()->count())->toBe(0);
});

it('rate limits commit creation', function (): void {
    $user = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($user)
            ->fromRoute('log.show', Log::Following)
            ->post(route('commit.store'), ['body' => "Commit {$i}"])
            ->assertRedirectToRoute('log.show', Log::Following);
    }

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'One too many']);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Slow down: 10 commits a minute. Even CI takes breaks.',
        ]);

    expect($user->commits()->count())->toBe(10);
});

it('requires authentication to create a commit', function (): void {
    $response = $this->post(route('commit.store'), [
        'body' => 'Initial commit',
    ]);

    $response->assertRedirectToRoute('home');

    expect(Commit::query()->count())->toBe(0);
});

it('may delete own commit', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->actingAs($commit->user)
        ->fromRoute('log.show', Log::Following)
        ->delete(route('commit.destroy', $commit));

    $response->assertRedirectToRoute('log.show', Log::Following);

    $this->assertSoftDeleted($commit);
});

it('keeps the replies of others when deleting own commit', function (): void {
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->for($commit, 'parent')->create();

    $this->actingAs($commit->user)
        ->fromRoute('log.show', Log::Following)
        ->delete(route('commit.destroy', $commit));

    $this->assertSoftDeleted($commit);

    $this->assertNotSoftDeleted($reply);
});

it('may not delete a commit that is already deleted', function (): void {
    $commit = Commit::factory()->create();
    $commit->delete();

    $response = $this->actingAs($commit->user)
        ->fromRoute('log.show', Log::Following)
        ->delete(route('commit.destroy', $commit));

    $response->assertNotFound();
});

it('may not delete commits of other users', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->delete(route('commit.destroy', $commit));

    $response->assertForbidden();

    $this->assertNotSoftDeleted($commit);
});

it('may delete own commit from its page', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->actingAs($commit->user)
        ->from(route('commit.show', [$commit->user, $commit]))
        ->delete(route('commit.destroy', $commit));

    $response->assertRedirectToRoute('user.show', $commit->user);

    $this->assertSoftDeleted($commit);
});

it('tells users their commit is pending review', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Initial commit']);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertInertiaFlash('toast.type', 'info');

    expect($user->commits()->sole()->status)->toBe(CommitStatus::Pending);
});

it('publishes commits of admins right away', function (): void {
    config(['app.admins' => ['nunomaduro']]);

    $user = User::factory()->create(['username' => 'nunomaduro']);

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Initial commit']);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertInertiaFlashMissing('toast');

    expect($user->commits()->sole()->status)->toBe(CommitStatus::Approved);
});

it('may reply to a commit', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), [
            'body' => 'Agreed, ship it.',
            'parent_id' => $parent->id,
        ]);

    $response->assertRedirectToRoute('log.show', Log::Everyone);

    expect($user->commits()->sole()->parent_id)->toBe($parent->id);
});

it('may reply to own pending commit', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->for($user)->pending()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Follow-up', 'parent_id' => $parent->id])
        ->assertSessionHasNoErrors();

    expect($parent->replies()->count())->toBe(1);
});

it('may not reply to a commit that is not visible', function (): void {
    $parent = Commit::factory()->pending()->create();

    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), ['body' => 'Hello', 'parent_id' => $parent->id]);

    $response->assertSessionHasErrors('parent_id');

    expect($parent->replies()->count())->toBe(0);
});

it('may not reply to a deleted commit', function (): void {
    $parent = Commit::factory()->create();
    $parent->delete();

    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), ['body' => 'Hello', 'parent_id' => $parent->id]);

    $response->assertSessionHasErrors('parent_id');

    expect(Commit::query()->count())->toBe(0);
});

it('may not reply to a commit that does not exist', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), ['body' => 'Hello', 'parent_id' => '01a13f6e-ebdd-72c7-bf9d-be2a9b2315a3']);

    $response->assertSessionHasErrors('parent_id');
});

it('may not reply across a block in either direction', function (bool $replierBlocks): void {
    $replier = User::factory()->create();
    $parent = Commit::factory()->create();

    $replierBlocks ? $replier->blocking()->attach($parent->user) : $parent->user->blocking()->attach($replier);

    $response = $this->actingAs($replier)
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), ['body' => 'Hello', 'parent_id' => $parent->id]);

    $response->assertSessionHasErrors(['parent_id' => 'That commit is no longer available to reply to.']);

    expect($parent->replies()->count())->toBe(0)
        ->and($parent->user->notifications()->exists())->toBeFalse();
})->with([true, false]);

it('may reply to a muted user without them knowing', function (): void {
    $replier = User::factory()->create();
    $parent = Commit::factory()->create();

    $replier->muting()->attach($parent->user);

    $this->actingAs($replier)
        ->post(route('commit.store'), ['body' => 'Hello', 'parent_id' => $parent->id])
        ->assertSessionHasNoErrors();

    expect($parent->replies()->count())->toBeOne();
});

it('does not show a commit across a block in either direction', function (bool $viewerBlocks): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create();

    $viewerBlocks ? $viewer->blocking()->attach($commit->user) : $commit->user->blocking()->attach($viewer);

    $response = $this->actingAs($viewer)
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertNotFound();
})->with([true, false]);

it('shows a muted user commit when opened directly, without their replies', function (): void {
    $viewer = User::factory()->create();
    $muted = User::factory()->create();
    $commit = Commit::factory()->for($muted)->create();
    Commit::factory()->for($commit, 'parent')->for($muted)->create();
    $reply = Commit::factory()->for($commit, 'parent')->create();

    $viewer->muting()->attach($muted);

    $response = $this->actingAs($viewer)
        ->get(route('commit.show', [$muted, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.id', $commit->id)
            ->where('commit.replies_count', 1)
            ->has('replies.data', 1)
            ->where('replies.data.0.id', $reply->id));
});

it('shows a commit with its ancestors and reply count', function (): void {
    $viewer = User::factory()->create();
    $root = Commit::factory()->create();
    $commit = Commit::factory()->for($root, 'parent')->create(['body' => 'Focused']);
    Commit::factory()->for($commit, 'parent')->count(2)->create();
    Commit::factory()->for($commit, 'parent')->pending()->create();

    $response = $this->actingAs($viewer)
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('commit/show')
            ->where('commit.id', $commit->id)
            ->where('commit.body', 'Focused')
            ->where('commit.user.username', $commit->user->username)
            ->where('commit.replies_count', 2)
            ->has('replies.data', 2)
            ->where('replies.data.0.user.username', fn (string $username): bool => $username !== '')
            ->where('replies.next_cursor', null)
            ->has('ancestors', 1)
            ->where('ancestors.0.id', $root->id)
            ->where('ancestors.0.user.username', $root->user->username)
            ->missing('commit.user.email'));
});

it('shows the commit and its replies split into prose and highlighted code', function (): void {
    $commit = Commit::factory()->create(['body' => "Run `make` :fire\n```php\n\$name = '<b>';\n```"]);
    Commit::factory()->for($commit, 'parent')->create(['body' => 'try `make test`']);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.body_segments', [
                ['type' => 'text', 'text' => 'Run '],
                ['type' => 'code', 'code' => 'make'],
                ['type' => 'text', 'text' => ' :fire'],
                ['type' => 'block', 'language' => 'php', 'html' => '<span class="hl-variable">$name</span> = <span class="hl-value">\'&lt;b&gt;\'</span>;'],
            ])
            ->where('replies.data.0.body_segments.1', ['type' => 'code', 'code' => 'make test'])
            ->missing('replies.data.0.body_segments.2'));
});

it('shows the star count and whether the viewer starred the commit and its ancestors', function (): void {
    $viewer = User::factory()->create();
    $root = Commit::factory()->create();
    $commit = Commit::factory()->for($root, 'parent')->create();

    $viewer->starredCommits()->attach($root);
    User::factory()->create()->starredCommits()->attach([$root->id, $commit->id]);

    $response = $this->actingAs($viewer)
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.stars_count', 1)
            ->where('commit.starred', false)
            ->where('ancestors.0.stars_count', 2)
            ->where('ancestors.0.starred', true));
});

it('shows the first 50 replies of a commit with a cursor to the rest', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create();

    Commit::factory()->for($commit, 'parent')->count(51)
        ->state(new Sequence(fn (Sequence $sequence): array => [
            'body' => "Reply {$sequence->index}",
            'created_at' => now()->subMinutes(100 - $sequence->index),
        ]))
        ->create();
    Commit::factory()->for($commit, 'parent')->pending()->create(['created_at' => now()->subMinutes(99)]);

    $response = $this->actingAs($viewer)
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.replies_count', 51)
            ->has('replies.data', 50)
            ->where('replies.data.0.body', 'Reply 0')
            ->where('replies.data.1.body', 'Reply 1')
            ->where('replies.data.49.body', 'Reply 49')
            ->where('replies.next_cursor', fn (string $cursor): bool => $cursor !== ''));
});

it('shows own pending commit', function (): void {
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($commit->user)
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('commit.status', 'pending'));
});

it('does not show a commit that is not visible', function (): void {
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertNotFound();
});

it('does not show a deleted commit', function (): void {
    $commit = Commit::factory()->create();
    Commit::factory()->for($commit, 'parent')->create();
    $commit->delete();

    $response = $this->actingAs($commit->user)
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertNotFound();
});

it('shows deleted ancestors and replies as tombstones', function (): void {
    $root = Commit::factory()->create();
    $deletedAncestor = Commit::factory()->for($root, 'parent')->create();
    $commit = Commit::factory()->for($deletedAncestor, 'parent')->create();
    $deletedReply = Commit::factory()->for($commit, 'parent')->create();
    Commit::factory()->for($deletedReply, 'parent')->create();
    Commit::factory()->for($commit, 'parent')->create()->delete();
    $deletedAncestor->delete();
    $deletedReply->delete();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.replies_count', 1)
            ->has('ancestors', 2)
            ->where('ancestors.0.id', $root->id)
            ->where('ancestors.1.id', $deletedAncestor->id)
            ->missing('ancestors.1.body')
            ->missing('ancestors.1.body_segments')
            ->missing('ancestors.1.user')
            ->has('replies.data', 1)
            ->where('replies.data.0.id', $deletedReply->id)
            ->where('replies.data.0.replies_count', 1)
            ->missing('replies.data.0.body'));
});

it('does not show a commit under another username', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [User::factory()->create(), $commit]));

    $response->assertNotFound();
});

it('shows guests the commit with its approved replies and a link preview', function (): void {
    $user = User::factory()->create(['username' => 'octocat', 'name' => 'The Octocat']);
    $commit = Commit::factory()->for($user)->create(['body' => "Shipped the new parser.\n\nIt is twice as fast."]);
    $commit->stars()->attach(User::factory()->create());
    Commit::factory()->for($commit, 'parent')->create(['body' => 'A reply everyone may read']);
    Commit::factory()->for($commit, 'parent')->pending()->create(['body' => 'A pending reply only its author may read']);
    Commit::factory()->for($user)->create(['body' => 'Another commit, not part of this thread']);

    $response = $this->get(route('commit.show', [$user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('commit/show')
            ->where('commit.id', $commit->id)
            ->where('commit.replies_count', 1)
            ->where('commit.stars_count', 1)
            ->where('commit.starred', false)
            ->where('commit.is_saved', false)
            ->has('ancestors', 0)
            ->has('replies.data', 1)
            ->where('replies.data.0.body', 'A reply everyone may read'))
        ->assertSee('<title>The Octocat (@octocat) on /dev/null</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('commit.show', [$user, $commit]).'">', false)
        ->assertSee('<meta name="twitter:title" content="The Octocat (@octocat) on /dev/null">', false)
        ->assertSee('<meta property="article:published_time" content="'.$commit->created_at->toIso8601String().'">', false)
        ->assertSee('"@type":"SocialMediaPosting"', false)
        ->assertSee('"author":{"@type":"Person","name":"The Octocat","alternateName":"@octocat","url":"'.route('user.show', $user).'"}', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="og:title" content="The Octocat (@octocat) on /dev/null">', false)
        ->assertSee('<meta property="og:description" content="Shipped the new parser. It is twice as fast.">', false)
        ->assertSee('<meta property="og:url" content="'.route('commit.show', [$user, $commit]).'">', false)
        ->assertSee('<meta property="og:image" content="'.route('commit-open-graph-image.show', [$user, $commit]).'?v=', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertDontSee(url('/og.png'))
        ->assertDontSee('A pending reply only its author may read')
        ->assertDontSee('Another commit, not part of this thread');

    $this->assertGuest();

    expect(session('url.intended'))->toBe(route('commit.show', [$user, $commit]));
});

it('trims long commits in the link preview description', function (): void {
    $commit = Commit::factory()->create(['body' => str_repeat('Writing PHP on purpose. ', 9).'The end is not described.']);

    $response = $this->get(route('commit.show', [$commit->user, $commit]));

    $description = Str::betweenFirst((string) $response->getContent(), '<meta property="og:description" content="', '"');

    expect($description)->toEndWith('…')
        ->and(mb_strlen($description))->toBeLessThanOrEqual(201)
        ->and($description)->not->toContain('The end');
});

it('shortens long urls in the link preview description', function (): void {
    $commit = Commit::factory()->create(['body' => 'A lot came! https://github.com/nunomaduro/dev-null.sh/pulls?q=sort%3Aupdated-desc+is%3Apr']);

    $this->get(route('commit.show', [$commit->user, $commit]))
        ->assertOk()
        ->assertSee('<meta property="og:description" content="A lot came! github.com/nunomaduro/dev-nul…">', false);
});

it('escapes the commit in the link preview', function (): void {
    $user = User::factory()->create(['username' => 'octocat', 'name' => '"><script>alert(1)</script>']);
    $commit = Commit::factory()->for($user)->create(['body' => '<img src=x onerror=alert(1)>']);

    $this->get(route('commit.show', [$user, $commit]))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<img src=x', false)
        ->assertSee('<meta property="og:description" content="&lt;img src=x onerror=alert(1)&gt;">', false)
        ->assertSee('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt; (@octocat) on /dev/null', false);
});

it('mentions the approved parent of a reply in the link preview', function (): void {
    $parent = Commit::factory()->for(User::factory()->state(['username' => 'taylorotwell']))->create(['body' => 'The parent commit']);
    $reply = Commit::factory()->for($parent, 'parent')->for(User::factory()->state(['username' => 'octocat', 'name' => 'octocat']))->create();

    $this->get(route('commit.show', [$reply->user, $reply]))
        ->assertOk()
        ->assertSee('<meta property="og:title" content="@octocat replying to @taylorotwell on /dev/null">', false)
        ->assertSee('"isPartOf":"'.route('commit.show', [$parent->user, $parent]).'"', false)
        ->assertInertia(fn ($page) => $page->where('ancestors.0.body', 'The parent commit'));
});

it('does not mention an unavailable parent in the link preview', function (Closure $state): void {
    $parent = Commit::factory()->for(User::factory()->state(['username' => 'taylorotwell']))->create(['body' => 'The parent commit']);
    $state($parent);
    $reply = Commit::factory()->for($parent, 'parent')->for(User::factory()->state(['username' => 'octocat', 'name' => 'octocat']))->create();

    $this->get(route('commit.show', [$reply->user, $reply]))
        ->assertOk()
        ->assertSee('<meta property="og:title" content="@octocat on /dev/null">', false)
        ->assertDontSee('replying to')
        ->assertDontSee('taylorotwell')
        ->assertDontSee('The parent commit');
})->with([
    'pending' => [fn (Commit $parent): bool => $parent->update(['status' => CommitStatus::Pending])],
    'rejected' => [fn (Commit $parent): bool => $parent->update(['status' => CommitStatus::Rejected])],
    'deleted' => [fn (Commit $parent): ?bool => $parent->delete()],
]);

it('escapes the commit in the structured data', function (): void {
    $commit = Commit::factory()->create(['body' => '</script><script>alert(1)</script>']);

    $this->get(route('commit.show', [$commit->user, $commit]))
        ->assertOk()
        ->assertDontSee('</script><script>alert(1)', false)
        ->assertSee('"text":"\u003C/script\u003E\u003Cscript\u003Ealert(1)\u003C/script\u003E"', false);
});

it('returns not found to guests for deleted commits', function (): void {
    $commit = Commit::factory()->create();
    $commit->delete();

    $this->get(route('commit.show', [$commit->user, $commit]))->assertNotFound();
});

it('changes the link preview image version only when the public commit changes', function (): void {
    $commit = Commit::factory()->create();

    $version = fn (): string => Str::betweenFirst((string) $this->get(route('commit.show', [$commit->user, $commit]))->getContent(), 'og.png?v=', '"');

    $initial = $version();

    Commit::factory()->for($commit, 'parent')->pending()->create();
    Commit::factory()->for($commit, 'parent')->rejected()->create();

    expect($version())->toBe($initial);

    Commit::factory()->for($commit, 'parent')->create();

    expect($version())->not->toBe($initial);
});

it('returns not found to guests for commits that are not approved', function (Closure $factory): void {
    $commit = $factory();

    $this->get(route('commit.show', [$commit->user, $commit]))->assertNotFound();

    expect(session('url.intended'))->toBeNull();
})->with([
    'pending' => [fn (): Commit => Commit::factory()->pending()->create()],
    'rejected' => [fn (): Commit => Commit::factory()->rejected()->create()],
]);

it('returns not found to guests for a commit under another username', function (): void {
    $commit = Commit::factory()->create();

    $this->get(route('commit.show', [User::factory()->create(), $commit]))->assertNotFound();
});

it('returns not found to guests for unknown commits', function (): void {
    $user = User::factory()->create();

    $this->get("/@{$user->username}/commits/01a13f6e-ebdd-72c7-bf9d-be2a9b2315a3")->assertNotFound();
    $this->get('/@missing/commits/01a13f6e-ebdd-72c7-bf9d-be2a9b2315a3')->assertNotFound();
});

it('keeps the default link preview for signed-in users on a commit', function (): void {
    $commit = Commit::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$commit->user, $commit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('commit/show'))
        ->assertSee('<meta property="og:image" content="'.url('/og.png').'">', false)
        ->assertDontSee('og:url', false);
});

it('returns to the intended commit after local sign in', function (): void {
    app()->instance('env', 'local');
    config()->set('app.auto_login', true);

    $commit = Commit::factory()->create();

    $this->get(route('commit.show', [$commit->user, $commit]))->assertRedirectToRoute('home');

    $this->get(route('home'))->assertRedirect(route('commit.show', [$commit->user, $commit]));
});

it('queues a link preview for a commit with a link', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Read all about it at https://example.com/link-previews.',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following);

    expect($user->commits()->sole()->linkPreview->url)->toBe('https://example.com/link-previews');

    Queue::assertPushed(UnfurlLink::class);
});

it('queues a link preview for the link the author chose', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Shipped https://example.com/first, docs at laravel.com',
            'link_preview_url' => 'https://laravel.com',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following);

    expect($user->commits()->sole()->linkPreview->url)->toBe('https://laravel.com');

    Queue::assertPushed(UnfurlLink::class);
});

it('attaches no link preview when the author chose none', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Shipped https://example.com/first',
            'link_preview_url' => '',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following);

    expect($user->commits()->sole()->link_preview_id)->toBeNull();

    Queue::assertNothingPushed();
});

it('may not choose a link preview for a url that is not in the body', function (string $url): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Shipped https://example.com/first and `https://example.com/code`',
            'link_preview_url' => $url,
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors(['link_preview_url' => 'The link card must be one of the links in the body.']);

    expect(Commit::query()->count())->toBe(0);
})->with([
    'another url' => 'https://evil.example.com',
    'a url in code' => 'https://example.com/code',
    'part of a url' => 'https://example.com',
]);

it('includes the link previews of a commit, its ancestors and its replies', function (): void {
    $root = Commit::factory()->for(LinkPreview::factory()->fetched()->state(['title' => 'Root link']))->create();
    $commit = Commit::factory()->for($root, 'parent')->for(LinkPreview::factory()->fetched()->state(['title' => 'Focused link']))->create();
    Commit::factory()->for($commit, 'parent')->for(LinkPreview::factory()->fetched()->state(['title' => 'Reply link']))->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.link_preview.title', 'Focused link')
            ->where('ancestors.0.link_preview.title', 'Root link')
            ->where('replies.data.0.link_preview.title', 'Reply link'));
});

it('may attach images to a commit', function (): void {
    Storage::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Look at this',
            'images' => [
                UploadedFile::fake()->image('first.jpg', 40, 30),
                UploadedFile::fake()->image('second.gif', 10, 10),
                UploadedFile::fake()->image('third.webp', 10, 10),
            ],
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasNoErrors();

    $commit = $user->commits()->sole();

    expect($commit->status)->toBe(CommitStatus::Pending)
        ->and($commit->images)->toHaveCount(3)
        ->and(Storage::disk()->allFiles('commit-images'))->toHaveCount(3);
});

it('may not attach more than three images to a commit', function (): void {
    Storage::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('commit.store'), [
        'body' => 'Too many',
        'images' => array_map(fn (int $index): UploadedFile => UploadedFile::fake()->image("{$index}.png", 10, 10), range(1, 4)),
    ]);

    $response->assertSessionHasErrors('images');

    expect($user->commits()->count())->toBe(0);
});

it('may only attach jpeg, png, gif or webp images to a commit', function (UploadedFile $file): void {
    Storage::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('commit.store'), [
        'body' => 'Not an image',
        'images' => [$file],
    ]);

    $response->assertSessionHasErrors('images.0');

    expect($user->commits()->count())->toBe(0)
        ->and(Storage::disk()->allFiles())->toBe([]);
})->with([
    'svg' => fn (): UploadedFile => UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'html disguised as png' => fn (): UploadedFile => UploadedFile::fake()->createWithContent('image.png', '<html><script>alert(1)</script></html>'),
    'bmp' => fn (): UploadedFile => UploadedFile::fake()->image('image.bmp', 10, 10),
    'pdf' => fn (): UploadedFile => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
]);

it('may not attach images larger than ten megabytes to a commit', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('commit.store'), [
        'body' => 'Too big',
        'images' => [UploadedFile::fake()->image('huge.jpg', 10, 10)->size(10241)],
    ]);

    $response->assertSessionHasErrors('images.0');
});

it('may not attach images with too many pixels to a commit', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('commit.store'), [
        'body' => 'Too wide',
        'images' => [UploadedFile::fake()->image('wide.png', 6001, 1)],
    ]);

    $response->assertSessionHasErrors('images.0');
});

it('includes the images of a commit, its ancestors and its replies', function (): void {
    $root = Commit::factory()->create();
    $commit = Commit::factory()->for($root, 'parent')->create();
    Commit::factory()->for($commit, 'parent')->create();

    Commit::query()->each(fn (Commit $commit): CommitImage => CommitImage::factory()->for($commit)->create());

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.images.0.url', route('commit-image.show', $commit->images()->sole()))
            ->has('ancestors.0.images', 1)
            ->has('replies.data.0.images', 1)
            ->missing('commit.images.0.path'));
});
