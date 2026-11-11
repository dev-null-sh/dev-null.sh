<?php

declare(strict_types=1);

use App\Enums\TopicType;
use App\Models\Commit;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('links the commits written before the developers had a page, reading the commits once', function (): void {
    $octocat = Profile::factory()->create(['handle' => 'octocat']);
    $monalisa = Profile::factory()->create(['handle' => 'MonaLisa']);
    $claimed = User::factory()->create(['handle' => 'hubot']);
    $both = Commit::factory()->create(['body' => 'Thanks @OctoCat and @monalisa', 'mentions' => []]);
    $deleted = tap(Commit::factory()->create(['body' => 'Hi @octocat', 'mentions' => []]))->delete();
    Commit::factory()->create(['body' => '`@octocat` in code', 'mentions' => []]);
    Commit::factory()->create(['body' => 'Hi @hubot', 'mentions' => []]);
    Commit::factory()->create(['body' => 'No mention']);

    DB::enableQueryLog();

    $this->artisan('commits:link-developers')
        ->expectsOutputToContain('Linked 2 commits to the developers they mention.')
        ->assertSuccessful();

    $scans = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "commits" where "body"'));
    DB::disableQueryLog();

    expect($both->refresh()->developer_mentions)->toEqualCanonicalizing(['octocat' => $octocat->id, 'monalisa' => $monalisa->id])
        ->and(Commit::withTrashed()->findOrFail($deleted->id)->developer_mentions)->toBe(['octocat' => $octocat->id])
        ->and(DB::table('commit_topics')->where('topic_type', TopicType::Developer)->count())->toBe(3)
        ->and(DB::table('commit_topics')->where('topic_id', $claimed->id)->exists())->toBeFalse()
        ->and($scans)->toHaveCount(1);

    $this->artisan('commits:link-developers')->assertSuccessful();

    expect(DB::table('commit_topics')->where('topic_type', TopicType::Developer)->count())->toBe(3);
});
