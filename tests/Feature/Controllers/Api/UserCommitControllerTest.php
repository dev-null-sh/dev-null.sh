<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists the public top-level commits of a user, newest first', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);

    $older = Commit::factory()->for($user)->create(['created_at' => now()->subDay()]);
    $newer = Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->for($older, 'parent')->create();
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();
    Commit::factory()->for($user)->create()->delete();
    Commit::factory()->create();

    $response = $this->getJson('/api/v1/users/octocat/commits');

    $response->assertOk()
        ->assertJsonPath('data.*.id', [$newer->id, $older->id])
        ->assertJsonPath('data.0.author.username', 'octocat')
        ->assertJsonPath('data.0.html_url', route('commit.show', [$user, $newer]))
        ->assertJsonPath('meta.next_cursor', null)
        ->assertJsonPath('links.next', null);
});

it('lists the public replies of a user', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);
    $parent = Commit::factory()->create();

    Commit::factory()->for($user)->create();
    $reply = Commit::factory()->for($user)->for($parent, 'parent')->create();
    Commit::factory()->for($user)->for($parent, 'parent')->pending()->create();
    Commit::factory()->for($user)->for($parent, 'parent')->create()->delete();

    $this->getJson('/api/v1/users/octocat/commits?replies=1')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$reply->id])
        ->assertJsonPath('data.0.parent_id', $parent->id);
});

it('pages through commits with a cursor', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);

    $commits = Commit::factory()->for($user)->count(25)->sequence(
        fn ($sequence): array => ['created_at' => now()->subMinutes($sequence->index)],
    )->create();

    $first = $this->getJson('/api/v1/users/octocat/commits?replies=0')
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.per_page', 20);

    $next = $first->json('links.next');

    expect($next)->toContain('replies=0')
        ->and($first->json('meta.next_cursor'))->toBeString();

    $this->getJson($next)
        ->assertOk()
        ->assertJsonPath('data.*.id', $commits->slice(20)->pluck('id')->values()->all())
        ->assertJsonPath('meta.next_cursor', null);
});

it('validates the replies filter', function (): void {
    User::factory()->create(['username' => 'octocat']);

    $this->getJson('/api/v1/users/octocat/commits?replies=maybe')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('replies');
});

it('returns not found for unknown usernames', function (): void {
    $this->getJson('/api/v1/users/missing/commits')->assertNotFound();
});

it('loads a page of commits without a query per commit', function (string $replies): void {
    $user = User::factory()->create(['username' => 'octocat']);

    $create = fn (int $count) => Commit::factory()->for($user)->count($count)
        ->for(LinkPreview::factory()->fetched())
        ->has(CommitImage::factory()->count(2)->sequence(['position' => 0], ['position' => 1]), 'images')
        ->state(fn (): array => ['parent_id' => $replies === '1' ? Commit::factory()->create()->id : null])
        ->create();

    $queries = function () use ($replies): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson("/api/v1/users/octocat/commits?replies={$replies}")->assertOk();

        return count(DB::getQueryLog());
    };

    $create(1);
    $withOne = $queries();

    $create(5);

    expect($queries())->toBe($withOne);
})->with(['commits' => '0', 'replies' => '1']);
