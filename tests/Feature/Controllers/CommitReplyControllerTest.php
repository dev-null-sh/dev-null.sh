<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('includes the link previews of replies', function (): void {
    $parent = Commit::factory()->create();
    Commit::factory()->for($parent, 'parent')->for(LinkPreview::factory()->fetched()->state(['title' => 'Reply link']))->create();

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('commit-reply.index', $parent));

    $response->assertOk()
        ->assertJsonPath('data.0.link_preview.title', 'Reply link')
        ->assertJsonMissingPath('data.0.link_preview.url_hash');
});

it('may reply to a commit', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('commit-reply.store', $parent), ['body' => 'Agreed, ship it.']);

    $reply = $user->commits()->sole();

    $response->assertCreated()
        ->assertExactJson([
            'committed' => ['id' => $reply->id, 'body' => 'Agreed, ship it.'],
            'toast' => [
                'type' => 'info',
                'message' => 'Your commit is pending review. Everyone will see it once it is approved.',
            ],
        ]);

    expect($reply->parent_id)->toBe($parent->id)
        ->and($reply->status)->toBe(CommitStatus::Pending);
});

it('publishes replies of admins right away', function (): void {
    config(['app.admins' => ['nunomaduro']]);

    $user = User::factory()->create(['username' => 'nunomaduro']);
    $parent = Commit::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('commit-reply.store', $parent), ['body' => 'Shipped.']);

    $response->assertCreated()
        ->assertJsonMissingPath('toast');

    expect($user->commits()->sole()->status)->toBe(CommitStatus::Approved);
});

it('may reply to own pending commit', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->for($user)->pending()->create();

    $this->actingAs($user)
        ->postJson(route('commit-reply.store', $parent), ['body' => 'Follow-up'])
        ->assertCreated();

    expect($parent->replies()->count())->toBe(1);
});

it('may not reply to a commit that is not visible', function (): void {
    $parent = Commit::factory()->pending()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-reply.store', $parent), ['body' => 'Hello'])
        ->assertNotFound();

    expect($parent->replies()->count())->toBe(0);
});

it('may not reply to a deleted commit', function (): void {
    $parent = Commit::factory()->create();
    $parent->delete();

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-reply.store', $parent), ['body' => 'Hello'])
        ->assertNotFound();

    expect(Commit::query()->count())->toBe(0);
});

it('may not reply to a commit that does not exist', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-reply.store', '01a13f6e-ebdd-72c7-bf9d-be2a9b2315a3'), ['body' => 'Hello'])
        ->assertNotFound();

    expect(Commit::query()->count())->toBe(0);
});

it('requires a reply body of 3 to 280 characters', function (string $body): void {
    $parent = Commit::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-reply.store', $parent), ['body' => $body])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');

    expect($parent->replies()->count())->toBe(0);
})->with([
    'blank' => '   ',
    'too short' => '  ab  ',
    'too long' => str_repeat('a', 281),
]);

it('may attach images to a reply', function (): void {
    Storage::fake();

    $user = User::factory()->create();
    $parent = Commit::factory()->create();

    $this->actingAs($user)
        ->post(route('commit-reply.store', $parent), [
            'body' => 'Look at this',
            'images' => [
                UploadedFile::fake()->image('first.jpg', 40, 30),
                UploadedFile::fake()->image('second.png', 10, 10),
            ],
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    expect($user->commits()->sole()->images)->toHaveCount(2)
        ->and(Storage::disk()->allFiles('commit-images'))->toHaveCount(2);
});

it('may describe the images attached to a reply', function (): void {
    Storage::fake();

    $user = User::factory()->create();
    $parent = Commit::factory()->create();

    $this->actingAs($user)
        ->post(route('commit-reply.store', $parent), [
            'body' => 'Offsite photo dump',
            'images' => [
                UploadedFile::fake()->image('cabin.jpg', 40, 30),
                UploadedFile::fake()->image('hike.jpg', 40, 30),
            ],
            'image_descriptions' => ['The cabin at sunrise, fog over the lake.', ''],
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    expect($user->commits()->sole()->images->pluck('description')->all())->toBe([
        'The cabin at sunrise, fog over the lake.',
        null,
    ]);
});

it('may not describe an image of a reply in more than a thousand characters', function (): void {
    Storage::fake();

    $user = User::factory()->create();
    $parent = Commit::factory()->create();

    $this->actingAs($user)
        ->post(route('commit-reply.store', $parent), [
            'body' => 'Too long a description',
            'images' => [UploadedFile::fake()->image('photo.jpg', 10, 10)],
            'image_descriptions' => [str_repeat('a', 1001)],
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image_descriptions.0');

    expect($user->commits()->count())->toBe(0);
});

it('may only attach jpeg, png, gif or webp images to a reply', function (): void {
    Storage::fake();

    $user = User::factory()->create();
    $parent = Commit::factory()->create();

    $this->actingAs($user)
        ->post(route('commit-reply.store', $parent), [
            'body' => 'Not an image',
            'images' => [UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('images.0');

    expect($user->commits()->count())->toBe(0);
});

it('rate limits replies', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($user)
            ->postJson(route('commit-reply.store', $parent), ['body' => "Reply {$i}"])
            ->assertCreated();
    }

    $this->actingAs($user)
        ->postJson(route('commit-reply.store', $parent), ['body' => 'One too many'])
        ->assertTooManyRequests()
        ->assertExactJson(['message' => 'Slow down: 10 commits a minute. Even CI takes breaks.']);

    expect($user->commits()->count())->toBe(10);
});

it('requires authentication to reply', function (): void {
    $parent = Commit::factory()->create();

    $this->postJson(route('commit-reply.store', $parent), ['body' => 'Hello'])
        ->assertUnauthorized();

    expect($parent->replies()->count())->toBe(0);
});

it('may delete own reply', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();

    $this->actingAs($reply->user)
        ->deleteJson(route('commit-reply.destroy', [$parent, $reply]))
        ->assertNoContent();

    $this->assertSoftDeleted($reply);
});

it('may delete own reply to a deleted commit', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $parent->delete();

    $this->actingAs($reply->user)
        ->deleteJson(route('commit-reply.destroy', [$parent, $reply]))
        ->assertNoContent();

    $this->assertSoftDeleted($reply);
});

it('may not delete replies of other users', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();

    $this->actingAs(User::factory()->create())
        ->deleteJson(route('commit-reply.destroy', [$parent, $reply]))
        ->assertForbidden();

    $this->assertNotSoftDeleted($reply);
});

it('may not delete a reply that is already deleted', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $reply->delete();

    $this->actingAs($reply->user)
        ->deleteJson(route('commit-reply.destroy', [$parent, $reply]))
        ->assertNotFound();
});

it('may not delete a reply through another commit', function (): void {
    $reply = Commit::factory()->for(Commit::factory(), 'parent')->create();

    $this->actingAs($reply->user)
        ->deleteJson(route('commit-reply.destroy', [Commit::factory()->create(), $reply]))
        ->assertNotFound();

    $this->assertNotSoftDeleted($reply);
});

it('requires authentication to delete a reply', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();

    $this->deleteJson(route('commit-reply.destroy', [$parent, $reply]))
        ->assertUnauthorized();

    $this->assertNotSoftDeleted($reply);
});
