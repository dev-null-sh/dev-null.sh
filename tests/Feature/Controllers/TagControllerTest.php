<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Tag;
use App\Models\User;

it('renders the commits with the tag newest first', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'laravel']);

    $older = Commit::factory()->create(['body' => 'Older #laravel', 'created_at' => now()->subMinute()]);
    $newer = Commit::factory()->create(['body' => 'Newer #laravel']);
    Commit::factory()->create(['body' => 'Untagged']);
    $tag->commits()->attach([$older->id, $newer->id]);

    $response = $this->actingAs($user)
        ->get('/tags/laravel');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tag/show')
            ->where('tag', ['name' => 'laravel'])
            ->where('commitsCount', 2)
            ->where('followersCount', 0)
            ->where('isFollowing', false)
            ->has('commits.data', 2)
            ->where('commits.data.0.body', 'Newer #laravel')
            ->where('commits.data.0.stars_count', 0)
            ->where('commits.data.0.replies_count', 0)
            ->missing('commits.data.0.user.email')
            ->where('commits.data.1.body', 'Older #laravel'));
});

it('lists replies with their parent', function (): void {
    $tag = Tag::factory()->create();
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $tag->commits()->attach($reply);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('tag.show', $tag));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $reply->id)
            ->where('commits.data.0.parent.user.username', $parent->user->username));
});

it('counts only the commits the user can see', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $tag->commits()->attach([
        Commit::factory()->create()->id,
        Commit::factory()->for($user)->pending()->create()->id,
        Commit::factory()->pending()->create()->id,
        Commit::factory()->rejected()->create()->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('tag.show', $tag));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commitsCount', 2)
            ->has('commits.data', 2));
});

it('knows if the user follows the tag', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();
    $user->followedTags()->attach($tag);
    User::factory()->create()->followedTags()->attach($tag);

    $response = $this->actingAs($user)
        ->get(route('tag.show', $tag));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isFollowing', true)
            ->where('followersCount', 2));
});

it('paginates the commits', function (): void {
    $tag = Tag::factory()->create();
    $tag->commits()->attach(Commit::factory()->count(25)->create());

    $response = $this->actingAs(User::factory()->create())
        ->get(route('tag.show', $tag));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 20)
            ->whereNot('commits.next_cursor', null));
});

it('returns not found for unknown tags', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get('/tags/unknown');

    $response->assertNotFound();
});

it('requires authentication', function (): void {
    $tag = Tag::factory()->create();

    $response = $this->get(route('tag.show', $tag));

    $response->assertRedirectToRoute('home');
});
