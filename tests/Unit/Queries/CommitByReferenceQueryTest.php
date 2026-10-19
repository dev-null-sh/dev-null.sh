<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitByReferenceQuery;

function shortHash(Commit $commit): string
{
    return mb_substr(str_replace('-', '', $commit->id), -7);
}

it('finds a commit by its hash, id, or link', function (Closure $reference): void {
    $commit = Commit::factory()->create();

    $found = resolve(CommitByReferenceQuery::class)->get(User::factory()->create(), $reference($commit));

    expect($found)->toHaveCount(1)
        ->and($found->sole()->is($commit))->toBeTrue();
})->with([
    'hash' => shortHash(...),
    'uppercase hash' => fn (Commit $commit): string => mb_strtoupper(shortHash($commit)),
    'id' => fn (Commit $commit): string => $commit->id,
    'link' => fn (Commit $commit): string => route('commit.show', [$commit->user, $commit]),
]);

it('only finds commits the user can see', function (): void {
    $pending = Commit::factory()->pending()->create();

    expect(resolve(CommitByReferenceQuery::class)->get(User::factory()->create(), shortHash($pending)))->toBeEmpty()
        ->and(resolve(CommitByReferenceQuery::class)->get($pending->user, shortHash($pending))->sole()->is($pending))->toBeTrue();
});

it('finds nothing for unknown commits', function (): void {
    expect(resolve(CommitByReferenceQuery::class)->get(User::factory()->create(), '0be0130'))->toBeEmpty();
});

it('finds at most two commits for ambiguous hashes', function (): void {
    Commit::factory()->create(['id' => '01a14036-b0fc-7085-95c9-6e52b0be0130']);
    Commit::factory()->create(['id' => '01a14036-b0fc-7085-95c9-6e52c0be0130']);
    Commit::factory()->create(['id' => '01a14036-b0fc-7085-95c9-6e52d0be0130']);

    expect(resolve(CommitByReferenceQuery::class)->get(User::factory()->create(), '0be0130'))->toHaveCount(2);
});

it('rejects UUIDs embedded in longer identifiers', function (string $reference): void {
    Commit::factory()->create(['id' => '01a14036-b0fc-7085-95c9-6e52b0be0130']);

    expect(resolve(CommitByReferenceQuery::class)->get(User::factory()->create(), $reference))->toBeNull();
})->with([
    'leading character' => 'f01a14036-b0fc-7085-95c9-6e52b0be0130',
    'trailing character' => '01a14036-b0fc-7085-95c9-6e52b0be0130f',
    'trailing hyphen' => '01a14036-b0fc-7085-95c9-6e52b0be0130-extra',
]);

it('returns null for references that are not hashes or links', function (string $reference): void {
    expect(resolve(CommitByReferenceQuery::class)->get(User::factory()->create(), $reference))->toBeNull();
})->with(['abc', 'not-a-hash', '%0130']);
