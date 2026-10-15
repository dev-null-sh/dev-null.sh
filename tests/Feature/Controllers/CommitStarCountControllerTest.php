<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Str;

it('returns only the requested star counts and the viewers starred state', function (): void {
    $viewer = User::factory()->create();
    $starred = Commit::factory()->create();
    $plain = Commit::factory()->create();
    Commit::factory()->create();
    $viewer->starredCommits()->attach($starred);
    User::factory()->create()->starredCommits()->attach($starred);

    $response = $this->actingAs($viewer)->getJson(route('commit-star-count.index', [
        'ids' => [$starred->id, $plain->id],
    ]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

    expect($response->json('commits'))->toEqualCanonicalizing([
        ['id' => $starred->id, 'stars_count' => 2, 'starred' => true],
        ['id' => $plain->id, 'stars_count' => 0, 'starred' => false],
    ]);
});

it('reflects stars added and removed between polls', function (): void {
    $viewer = User::factory()->create();
    $other = User::factory()->create();
    $commit = Commit::factory()->create();
    $url = route('commit-star-count.index', ['ids' => [$commit->id]]);

    $this->actingAs($viewer)->getJson($url)->assertJsonPath('commits.0.stars_count', 0);

    $other->starredCommits()->attach($commit);
    $viewer->starredCommits()->attach($commit);
    $this->getJson($url)->assertJsonPath('commits.0.stars_count', 2)->assertJsonPath('commits.0.starred', true);

    $viewer->starredCommits()->detach($commit);
    $other->starredCommits()->detach($commit);
    $this->getJson($url)->assertJsonPath('commits.0.stars_count', 0)->assertJsonPath('commits.0.starred', false);
});

it('omits hidden deleted and missing commits while including the authors own unapproved commits', function (): void {
    $viewer = User::factory()->create();
    $pending = Commit::factory()->for($viewer)->pending()->create();
    $rejected = Commit::factory()->for($viewer)->rejected()->create();
    $hiddenPending = Commit::factory()->pending()->create();
    $hiddenRejected = Commit::factory()->rejected()->create();
    $deleted = Commit::factory()->for($viewer)->create();
    $deleted->delete();

    $response = $this->actingAs($viewer)->getJson(route('commit-star-count.index', [
        'ids' => [$pending->id, $rejected->id, $hiddenPending->id, $hiddenRejected->id, $deleted->id, (string) Str::uuid()],
    ]))->assertOk();

    expect($response->json('commits'))->toEqualCanonicalizing([
        ['id' => $pending->id, 'stars_count' => 0, 'starred' => false],
        ['id' => $rejected->id, 'stars_count' => 0, 'starred' => false],
    ]);
});

it('requires authentication', function (): void {
    $this->getJson(route('commit-star-count.index', ['ids' => [(string) Str::uuid()]]))->assertUnauthorized();
});

it('rejects invalid polling batches', function (array $data, string $error): void {
    $this->actingAs(User::factory()->create())
        ->getJson(route('commit-star-count.index', $data))
        ->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'missing' => [[], 'ids'],
    'empty' => [['ids' => []], 'ids'],
    'scalar' => [['ids' => 'invalid'], 'ids'],
    'associative' => [['ids' => ['post' => 'invalid']], 'ids'],
    'invalid uuid' => [['ids' => ['invalid']], 'ids.0'],
    'duplicates' => [['ids' => ['00000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000000001']], 'ids.0'],
]);

it('bounds polling batches to one hundred commits', function (): void {
    $ids = array_map(fn (): string => (string) Str::uuid(), range(1, 100));

    $this->actingAs(User::factory()->create())
        ->getJson(route('commit-star-count.index', ['ids' => $ids]))
        ->assertOk()->assertExactJson(['commits' => []]);

    $ids[] = (string) Str::uuid();
    $this->getJson(route('commit-star-count.index', ['ids' => $ids]))
        ->assertUnprocessable()->assertJsonValidationErrors('ids');
});
