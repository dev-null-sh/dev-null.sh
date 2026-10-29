<?php

declare(strict_types=1);

use App\Actions\CreateMergeConflict;
use App\Actions\CreateTomFriendship;
use App\Ai\Agents\ConflictSpotter;
use App\Enums\Feature;
use App\Models\Commit;
use App\Models\MergeConflict;
use App\Models\User;
use App\Services\Features;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * @return list<Commit>
 */
function repliesTo(Commit $commit, string ...$bodies): array
{
    return array_values(array_map(fn (string $body, int $index): Commit => Commit::factory()->create([
        'parent_id' => $commit->id,
        'body' => $body,
        'created_at' => now()->subMinutes(count($bodies) - $index),
    ]), $bodies, array_keys($bodies)));
}

it('opens a merge conflict between two replies that disagree', function (): void {
    ConflictSpotter::fake([['conflict' => true, 'ours' => 1, 'theirs' => 3]])->preventStrayPrompts();
    $commit = Commit::factory()->create(['body' => 'Tabs or spaces?']);
    [$tabs, , $spaces] = repliesTo($commit, 'Tabs, always.', 'Nice question', 'Spaces, tabs are wrong.');

    $mergeConflict = resolve(CreateMergeConflict::class)->handle($commit);

    expect($mergeConflict)
        ->commit_id->toBe($commit->id)
        ->ours_id->toBe($tabs->id)
        ->theirs_id->toBe($spaces->id)
        ->closes_at->toDateTimeString()->toBe(now()->addHours(24)->toDateTimeString());
    ConflictSpotter::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, '"post":"Tabs or spaces?"')
        && str_contains($prompt->prompt, '"number":3')
        && str_contains($prompt->prompt, '"body":"Spaces, tabs are wrong."'));
});

it('puts the earlier reply on our side whichever way Tom names them', function (): void {
    ConflictSpotter::fake([['conflict' => true, 'ours' => 2, 'theirs' => 1]]);
    $commit = Commit::factory()->create();
    [$first, $second] = repliesTo($commit, 'Yes', 'No');

    expect(resolve(CreateMergeConflict::class)->handle($commit))
        ->ours_id->toBe($first->id)
        ->theirs_id->toBe($second->id);
});

it('opens nothing when Tom finds no conflict, or names replies that do not make one', function (array $answer): void {
    ConflictSpotter::fake([$answer]);
    $commit = Commit::factory()->create();
    $author = User::factory()->create();
    repliesTo($commit, 'Yes', 'No');
    Commit::factory()->for($author)->create(['parent_id' => $commit->id, 'created_at' => now()]);
    Commit::factory()->for($author)->create(['parent_id' => $commit->id, 'created_at' => now()->addSecond()]);

    expect(resolve(CreateMergeConflict::class)->handle($commit))->toBeNull();
    $this->assertDatabaseCount('merge_conflicts', 0);
})->with([
    'no conflict' => [['conflict' => false, 'ours' => 0, 'theirs' => 0]],
    'out of range' => [['conflict' => true, 'ours' => 1, 'theirs' => 9]],
    'the same reply twice' => [['conflict' => true, 'ours' => 1, 'theirs' => 1]],
    'one author against themselves' => [['conflict' => true, 'ours' => 3, 'theirs' => 4]],
    'a malformed answer' => [['conflict' => 'yes']],
]);

it('does not ask Tom until two people replied', function (): void {
    ConflictSpotter::fake()->preventStrayPrompts();
    $commit = Commit::factory()->create();
    $author = User::factory()->create();
    Commit::factory()->for($author)->count(2)->create(['parent_id' => $commit->id]);
    Commit::factory()->pending()->create(['parent_id' => $commit->id]);

    expect(resolve(CreateMergeConflict::class)->handle($commit))->toBeNull();
    ConflictSpotter::assertNeverPrompted();
});

it('leaves the replies of Tom out', function (): void {
    ConflictSpotter::fake()->preventStrayPrompts();
    $tom = User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'handle' => CreateTomFriendship::HANDLE]);
    $commit = Commit::factory()->create();
    repliesTo($commit, 'Yes');
    Commit::factory()->for($tom)->create(['parent_id' => $commit->id, 'body' => 'No']);

    expect(resolve(CreateMergeConflict::class)->handle($commit))->toBeNull();
    ConflictSpotter::assertNeverPrompted();
});

it('reads a thread at most once an hour', function (): void {
    ConflictSpotter::fake([
        ['conflict' => false, 'ours' => 0, 'theirs' => 0],
        ['conflict' => true, 'ours' => 1, 'theirs' => 2],
    ]);
    $commit = Commit::factory()->create();
    repliesTo($commit, 'Yes', 'No');

    resolve(CreateMergeConflict::class)->handle($commit);
    resolve(CreateMergeConflict::class)->handle($commit);
    ConflictSpotter::assertPromptedTimes(1);

    $this->travel(61)->minutes();

    expect(resolve(CreateMergeConflict::class)->handle($commit))->toBeInstanceOf(MergeConflict::class);
    ConflictSpotter::assertPromptedTimes(2);
});

it('reads the thread again when Tom failed to answer', function (): void {
    $prompts = 0;
    ConflictSpotter::fake(function () use (&$prompts): array {
        throw_if($prompts++ === 0, RuntimeException::class, 'Tom is down');

        return ['conflict' => true, 'ours' => 1, 'theirs' => 2];
    });
    $commit = Commit::factory()->create();
    repliesTo($commit, 'Yes', 'No');

    expect(fn (): ?MergeConflict => resolve(CreateMergeConflict::class)->handle($commit))->toThrow(RuntimeException::class);

    expect(resolve(CreateMergeConflict::class)->handle($commit))->toBeInstanceOf(MergeConflict::class)
        ->and($prompts)->toBe(2);
});

it('opens one merge conflict per commit', function (): void {
    ConflictSpotter::fake()->preventStrayPrompts();
    $mergeConflict = MergeConflict::factory()->create();
    repliesTo($mergeConflict->commit, 'Yes', 'No');

    expect(resolve(CreateMergeConflict::class)->handle($mergeConflict->commit))->toBeNull();
    ConflictSpotter::assertNeverPrompted();
});

it('reads no thread while merge conflicts are switched off', function (): void {
    ConflictSpotter::fake()->preventStrayPrompts();
    resolve(Features::class)->update(Feature::MergeConflicts, false);
    $commit = Commit::factory()->create();
    repliesTo($commit, 'Yes', 'No');

    expect(resolve(CreateMergeConflict::class)->handle($commit))->toBeNull();
    ConflictSpotter::assertNeverPrompted();
});
