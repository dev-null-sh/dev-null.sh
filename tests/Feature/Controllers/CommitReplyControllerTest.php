<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;

it('lists visible replies oldest first with their own reply counts', function (): void {
    $viewer = User::factory()->create();
    $parent = Commit::factory()->create();

    $second = Commit::factory()->for($parent, 'parent')->create(['body' => 'Second', 'created_at' => now()->subMinute()]);
    Commit::factory()->for($parent, 'parent')->create(['body' => 'First', 'created_at' => now()->subMinutes(2)]);
    Commit::factory()->for($parent, 'parent')->for($viewer)->pending()->create(['body' => 'Mine, pending']);
    Commit::factory()->for($parent, 'parent')->pending()->create(['body' => 'Hidden']);
    Commit::factory()->for($second, 'parent')->count(2)->create();
    Commit::factory()->for($second, 'parent')->pending()->create();

    $response = $this->actingAs($viewer)
        ->getJson(route('commit-reply.index', $parent));

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.body', 'First')
        ->assertJsonPath('data.1.body', 'Second')
        ->assertJsonPath('data.1.replies_count', 2)
        ->assertJsonPath('data.2.body', 'Mine, pending')
        ->assertJsonPath('data.0.user.id', fn (string $id): bool => $id !== '')
        ->assertJsonMissingPath('data.0.user.email')
        ->assertJsonPath('next_cursor', null);
});

it('lists replies 50 at a time with a cursor to the next page', function (): void {
    $viewer = User::factory()->create();
    $parent = Commit::factory()->create();

    Commit::factory()->for($parent, 'parent')->count(52)
        ->state(new Sequence(fn (Sequence $sequence): array => [
            'body' => "Reply {$sequence->index}",
            'created_at' => now()->subMinutes(100 - $sequence->index),
        ]))
        ->create();

    $first = $this->actingAs($viewer)
        ->getJson(route('commit-reply.index', $parent));

    $first->assertOk()
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('data.0.body', 'Reply 0')
        ->assertJsonPath('data.49.body', 'Reply 49')
        ->assertJsonPath('next_cursor', fn (string $cursor): bool => $cursor !== '');

    $second = $this->actingAs($viewer)
        ->getJson(route('commit-reply.index', [$parent, 'cursor' => $first->json('next_cursor')]));

    $second->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.body', 'Reply 50')
        ->assertJsonPath('data.1.body', 'Reply 51')
        ->assertJsonPath('next_cursor', null);
});

it('orders replies created at the same time by id across pages', function (): void {
    $parent = Commit::factory()->create();
    $replies = Commit::factory()->for($parent, 'parent')->count(51)->create(['created_at' => now()->subMinute()]);

    $first = $this->actingAs(User::factory()->create())
        ->getJson(route('commit-reply.index', $parent));

    $second = $this->getJson(route('commit-reply.index', [$parent, 'cursor' => $first->json('next_cursor')]));

    expect([...$first->json('data.*.id'), ...$second->json('data.*.id')])
        ->toBe($replies->pluck('id')->sort()->values()->all());
});

it('only pages through replies visible to the viewer', function (): void {
    $viewer = User::factory()->create();
    $parent = Commit::factory()->create();

    Commit::factory()->for($parent, 'parent')->count(50)
        ->state(new Sequence(fn (Sequence $sequence): array => ['created_at' => now()->subMinutes(100 - $sequence->index)]))
        ->create();
    Commit::factory()->for($parent, 'parent')->pending()->count(3)->create(['created_at' => now()->subMinutes(10)]);
    Commit::factory()->for($parent, 'parent')->rejected()->create(['created_at' => now()->subMinutes(9)]);
    Commit::factory()->for($parent, 'parent')->for($viewer)->pending()->create(['body' => 'Mine, pending', 'created_at' => now()->subMinutes(8)]);

    $first = $this->actingAs($viewer)
        ->getJson(route('commit-reply.index', $parent));

    $first->assertJsonCount(50, 'data')
        ->assertJsonPath('data.*.status', array_fill(0, 50, 'approved'));

    $second = $this->getJson(route('commit-reply.index', [$parent, 'cursor' => $first->json('next_cursor')]));

    $second->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'Mine, pending')
        ->assertJsonPath('next_cursor', null);
});

it('lists deleted replies that still have replies as tombstones', function (): void {
    $viewer = User::factory()->create();
    $parent = Commit::factory()->create();

    $tombstone = Commit::factory()->for($parent, 'parent')->create(['body' => 'Gone', 'created_at' => now()->subMinutes(2)]);
    Commit::factory()->for($tombstone, 'parent')->create();
    $tombstone->delete();
    Commit::factory()->for($parent, 'parent')->create(['created_at' => now()->subMinute()])->delete();
    Commit::factory()->for($parent, 'parent')->create(['body' => 'Kept']);

    $response = $this->actingAs($viewer)
        ->getJson(route('commit-reply.index', $parent));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $tombstone->id)
        ->assertJsonPath('data.0.deleted_at', fn (string $deletedAt): bool => $deletedAt !== '')
        ->assertJsonPath('data.0.replies_count', 1)
        ->assertJsonMissingPath('data.0.body')
        ->assertJsonMissingPath('data.0.user')
        ->assertJsonMissingPath('data.0.user_id')
        ->assertJsonPath('data.1.body', 'Kept');
});

it('lists the replies of a deleted commit', function (): void {
    $parent = Commit::factory()->create();
    Commit::factory()->for($parent, 'parent')->create(['body' => 'Still here']);
    $parent->delete();

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('commit-reply.index', $parent));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'Still here');
});

it('does not list replies of a commit that is not visible', function (): void {
    $parent = Commit::factory()->pending()->create();

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('commit-reply.index', $parent));

    $response->assertNotFound();
});

it('lists only approved replies to guests', function (): void {
    $parent = Commit::factory()->create();
    Commit::factory()->for($parent, 'parent')->create(['body' => 'Approved']);
    Commit::factory()->for($parent, 'parent')->pending()->create(['body' => 'Pending']);
    Commit::factory()->for($parent, 'parent')->rejected()->create(['body' => 'Rejected']);

    $response = $this->getJson(route('commit-reply.index', $parent));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'Approved')
        ->assertJsonPath('data.0.is_saved', false);
});

it('returns not found to guests for the replies of a commit that is not approved', function (): void {
    $response = $this->getJson(route('commit-reply.index', Commit::factory()->pending()->create()));

    $response->assertNotFound();
});

it('leaves out replies by muted people and across blocks', function (): void {
    $viewer = User::factory()->create();
    $muted = User::factory()->create();
    $blocker = User::factory()->create();
    $parent = Commit::factory()->create();

    $viewer->muting()->attach($muted);
    $blocker->blocking()->attach($viewer);

    $reply = Commit::factory()->for($parent, 'parent')->create();
    Commit::factory()->for($parent, 'parent')->for($muted)->create();
    Commit::factory()->for($parent, 'parent')->for($blocker)->create();

    $response = $this->actingAs($viewer)
        ->getJson(route('commit-reply.index', $parent));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $reply->id);
});

it('returns not found for replies to a commit across a block', function (): void {
    $viewer = User::factory()->create();
    $parent = Commit::factory()->create();
    Commit::factory()->for($parent, 'parent')->create();

    $viewer->blocking()->attach($parent->user);

    $response = $this->actingAs($viewer)
        ->getJson(route('commit-reply.index', $parent));

    $response->assertNotFound();
});

it('includes the link previews of replies', function (): void {
    $parent = Commit::factory()->create();
    Commit::factory()->for($parent, 'parent')->for(LinkPreview::factory()->fetched()->state(['title' => 'Reply link']))->create();

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('commit-reply.index', $parent));

    $response->assertOk()
        ->assertJsonPath('data.0.link_preview.title', 'Reply link')
        ->assertJsonMissingPath('data.0.link_preview.url_hash');
});

it('returns 404 to guests for replies of an unapproved tombstone after account deletion', function (CommitStatus $status): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    $parent = Commit::factory()->for($user)->for($root, 'parent')->create(['status' => $status]);
    Commit::factory()->for($parent, 'parent')->create();
    resolve(DeleteUser::class)->handle($user);

    $response = $this->getJson(route('commit-reply.index', $parent));

    $response->assertNotFound();
})->with([
    'pending' => [CommitStatus::Pending],
    'rejected' => [CommitStatus::Rejected],
]);

it('keeps account deletion tombstones in threads while filtering silenced authors', function (string $relation, bool $viewerActs): void {
    $viewer = User::factory()->create();
    $silenced = User::factory()->create();
    [$actor, $subject] = $viewerActs ? [$viewer, $silenced] : [$silenced, $viewer];
    $actor->{$relation}()->attach($subject);

    $author = User::factory()->create();
    $root = Commit::factory()->create();
    $parent = Commit::factory()->for($author)->for($root, 'parent')->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    Commit::factory()->for($silenced)->for($parent, 'parent')->create();
    $hiddenParent = Commit::factory()->for($author)->for($root, 'parent')->create();
    Commit::factory()->for($silenced)->for($hiddenParent, 'parent')->create();
    resolve(DeleteUser::class)->handle($author);

    $this->actingAs($viewer)->getJson(route('commit-reply.index', $root))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $parent->id)
        ->assertJsonPath('data.0.replies_count', 1)
        ->assertJsonMissingPath('data.0.user')
        ->assertJsonMissingPath('data.0.body');

    $this->getJson(route('commit-reply.index', $parent))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $reply->id);
})->with([
    'muted author' => ['muting', true],
    'blocked author' => ['blocking', true],
    'blocking author' => ['blocking', false],
]);

it('keeps approved account deletion tombstones readable to guests without exposing the deleted author', function (): void {
    $user = User::factory()->create(['username' => 'deletedauthor']);
    $root = Commit::factory()->create();
    $parent = Commit::factory()->for($user)->for($root, 'parent')->create(['body' => 'Deleted body']);
    $reply = Commit::factory()->for($parent, 'parent')->create();
    resolve(DeleteUser::class)->handle($user);

    $response = $this->get(route('commit.show', [$reply->user, $reply]));

    $response->assertOk()
        ->assertSee('<meta property="og:title" content="'.e($reply->user->nameWithHandle()).' on /dev/null">', false)
        ->assertSee('"@type":"SocialMediaPosting"', false)
        ->assertDontSee('"isPartOf":', false)
        ->assertDontSee('deletedauthor')
        ->assertDontSee('Deleted body')
        ->assertInertia(fn ($page) => $page
            ->where('ancestors.1.id', $parent->id)
            ->missing('ancestors.1.body')
            ->missing('ancestors.1.user'));

    $this->getJson(route('commit-reply.index', $parent))
        ->assertOk()
        ->assertJsonPath('data.0.id', $reply->id);
});
