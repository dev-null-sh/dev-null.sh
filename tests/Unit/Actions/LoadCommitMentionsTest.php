<?php

declare(strict_types=1);

use App\Actions\LoadCommitMentions;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('resolves known handles case insensitively in one query for a page', function (): void {
    User::factory()->create(['username' => 'Dev-One']);
    User::factory()->create(['username' => 'dev-two']);
    $commits = Commit::factory()->count(20)->make(['body' => 'Thanks @DEV-ONE and (@dev-two), @unknown.']);

    DB::enableQueryLog();
    resolve(LoadCommitMentions::class)->handle($commits);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1);
    foreach ($commits as $commit) {
        expect($commit->toArray()['mentions'])->toEqualCanonicalizing(['Dev-One', 'dev-two']);
        expect($commit->body)->toBe('Thanks @DEV-ONE and (@dev-two), @unknown.');
    }
});

it('does not resolve email addresses partial handles or deleted commits', function (): void {
    User::factory()->create(['username' => 'dev']);
    $deletedUser = User::factory()->create(['username' => 'gone']);
    $deletedUser->delete();

    $plain = Commit::factory()->make(['body' => 'mail@dev.com /@dev https://example.com/@dev @dev_name @gone']);
    $deleted = Commit::factory()->make(['body' => '@dev', 'deleted_at' => now()]);

    DB::enableQueryLog();
    resolve(LoadCommitMentions::class)->handle([$plain, $deleted]);
    DB::disableQueryLog();

    expect($plain->toArray()['mentions'])->toBe([])
        ->and($deleted->toArray())->not->toHaveKeys(['body', 'mentions', 'user']);
});

it('does no user query when a page has no mentions', function (): void {
    $commits = Commit::factory()->count(20)->make(['body' => 'Shipped a small improvement.']);
    DB::enableQueryLog();
    resolve(LoadCommitMentions::class)->handle($commits);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($queries)->toBe([]);
});
