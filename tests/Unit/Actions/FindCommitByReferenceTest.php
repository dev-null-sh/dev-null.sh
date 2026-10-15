<?php

declare(strict_types=1);

use App\Actions\FindCommitByReference;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function shortHash(Commit $commit): string
{
    return mb_substr(str_replace('-', '', $commit->id), -7);
}

it('finds a commit by its hash, id, or link', function (Closure $reference): void {
    $commit = Commit::factory()->create();

    $found = resolve(FindCommitByReference::class)->handle(User::factory()->create(), $reference($commit));

    expect($found->is($commit))->toBeTrue();
})->with([
    'hash' => shortHash(...),
    'uppercase hash' => fn (Commit $commit): string => mb_strtoupper(shortHash($commit)),
    'id' => fn (Commit $commit): string => $commit->id,
    'link' => fn (Commit $commit): string => route('commit.show', [$commit->user, $commit]),
]);

it('only finds commits the user can see', function (): void {
    $pending = Commit::factory()->pending()->create();

    expect(fn (): Commit => resolve(FindCommitByReference::class)->handle(User::factory()->create(), shortHash($pending)))
        ->toThrow(ValidationException::class, 'There is no commit '.shortHash($pending).' to reply to.');

    expect(resolve(FindCommitByReference::class)->handle($pending->user, shortHash($pending))->is($pending))->toBeTrue();
});

it('rejects unknown commits', function (): void {
    expect(fn (): Commit => resolve(FindCommitByReference::class)->handle(User::factory()->create(), '0be0130'))
        ->toThrow(ValidationException::class, 'There is no commit 0be0130 to reply to.');
});

it('rejects ambiguous hashes', function (): void {
    Commit::factory()->create(['id' => '01a14036-b0fc-7085-95c9-6e52b0be0130']);
    Commit::factory()->create(['id' => '01a14036-b0fc-7085-95c9-6e52c0be0130']);

    expect(fn (): Commit => resolve(FindCommitByReference::class)->handle(User::factory()->create(), '0be0130'))
        ->toThrow(ValidationException::class, '0be0130 matches several commits: use the link of the commit instead.');
});

it('rejects references that are not hashes or links', function (string $reference): void {
    expect(fn (): Commit => resolve(FindCommitByReference::class)->handle(User::factory()->create(), $reference))
        ->toThrow(ValidationException::class, "{$reference} is not a commit hash or link.");
})->with(['abc', 'not-a-hash', '%0130']);
