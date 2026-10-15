<?php

declare(strict_types=1);

use App\Enums\Achievement;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\SshKey;
use App\Models\User;
use App\Queries\UserAchievementsQuery;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
});

it('calculates initial rank and zero progress for a new user without activity', function (): void {
    $user = User::factory()->create();

    $result = resolve(UserAchievementsQuery::class)->get($user, $user);

    expect($result['level'])->toBe(1)
        ->and($result['rank'])->toBe('user')
        ->and($result['xp'])->toBe(0)
        ->and($result['next_level_xp'])->toBe(50)
        ->and($result['level_progress'])->toBe(0)
        ->and($result['ranks'])->toHaveCount(6)
        ->and($result['xp_breakdown']['commits']['xp'])->toBe(0)
        ->and($result['badges'])->toHaveCount(12);

    foreach ($result['badges'] as $badge) {
        expect($badge['unlocked'])->toBeFalse()
            ->and($badge['progress'])->toBe(0);
    }
});

it('unlocks init badge upon first commit and awards xp', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create();

    $result = resolve(UserAchievementsQuery::class)->get($user, $user);

    $initBadge = collect($result['badges'])->firstWhere('id', Achievement::Init->value);

    expect($initBadge)->not->toBeNull()
        ->and($initBadge['unlocked'])->toBeTrue()
        ->and($initBadge['progress'])->toBe(1)
        // XP: 10 (commit) + 15 (1-day streak) = 25
        ->and($result['xp'])->toBe(25);
});

it('unlocks max_payload badge for a 280-character commit', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create(['body' => str_repeat('x', 280)]);

    $result = resolve(UserAchievementsQuery::class)->get($user, $user);

    $badge = collect($result['badges'])->firstWhere('id', Achievement::MaxPayload->value);

    expect($badge)->not->toBeNull()
        ->and($badge['unlocked'])->toBeTrue()
        ->and($badge['progress'])->toBe(1);
});

it('unlocks cli_native badge when user has registered ssh keys', function (): void {
    $user = User::factory()->create();

    SshKey::factory()->for($user)->create();

    $result = resolve(UserAchievementsQuery::class)->get($user, $user);

    $badge = collect($result['badges'])->firstWhere('id', Achievement::CliNative->value);

    expect($badge)->not->toBeNull()
        ->and($badge['unlocked'])->toBeTrue()
        ->and($badge['progress'])->toBe(1);
});

it('tracks stars given and received for stargazer and superstar badges', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $userCommit = Commit::factory()->for($user)->create();
    $otherCommit = Commit::factory()->for($other)->create();

    // User stars other's commit
    $user->starredCommits()->attach($otherCommit);

    // Other stars user's commit
    $other->starredCommits()->attach($userCommit);

    $result = resolve(UserAchievementsQuery::class)->get($user, $user);

    $stargazer = collect($result['badges'])->firstWhere('id', Achievement::Stargazer->value);
    $superstar = collect($result['badges'])->firstWhere('id', Achievement::Superstar->value);

    expect($stargazer['progress'])->toBe(1)
        ->and($stargazer['unlocked'])->toBeFalse()
        ->and($superstar['progress'])->toBe(1)
        ->and($superstar['unlocked'])->toBeFalse()
        // XP: 10 (commit) + 15 (1-day streak) + 5 (star received) + 2 (star given) = 32
        ->and($result['xp'])->toBe(32);
});

it('unlocks pipe badge with replies', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();

    Commit::factory()->for($user)->for($parent, 'parent')->count(5)->create();

    $result = resolve(UserAchievementsQuery::class)->get($user, $user);

    $pipeBadge = collect($result['badges'])->firstWhere('id', Achievement::Pipe->value);

    expect($pipeBadge['unlocked'])->toBeTrue()
        ->and($pipeBadge['progress'])->toBe(5);
});

it('unlocks uptime badge for 7-day streak and calculates streak xp', function (): void {
    $user = User::factory()->create();

    foreach (range(0, 6) as $day) {
        Commit::factory()->for($user)->create(['created_at' => now()->subDays($day)]);
    }

    $result = resolve(UserAchievementsQuery::class)->get($user, $user);

    $uptime = collect($result['badges'])->firstWhere('id', Achievement::Uptime->value);

    expect($uptime['unlocked'])->toBeTrue()
        ->and($uptime['progress'])->toBe(7)
        // 7 commits * 10 = 70 XP, 7 longest streak * 15 = 105 XP -> Total 175 XP -> Level 3 Operator
        ->and($result['xp'])->toBe(175)
        ->and($result['level'])->toBe(3)
        ->and($result['rank'])->toBe('operator');
});

it('resolves correct unix ranks as xp accumulates', function (): void {
    $user = User::factory()->create();

    // 5 commits = 50 XP -> Level 2 daemon
    Commit::factory()->for($user)->count(5)->create(['created_at' => now()->subDays(10)]);

    $query = resolve(UserAchievementsQuery::class);
    $result = $query->get($user, $user);

    expect($result['level'])->toBe(2)
        ->and($result['rank'])->toBe('daemon')
        ->and($result['next_level_xp'])->toBe(150);
});

it('respects visibility scope of viewer for unapproved commits', function (): void {
    $user = User::factory()->create();
    $viewer = User::factory()->create();

    Commit::factory()->for($user)->create(['status' => CommitStatus::Pending]);

    $query = resolve(UserAchievementsQuery::class);

    // Author sees their own pending commit
    $authorResult = $query->get($user, $user);
    expect($authorResult['xp'])->toBe(25);

    // Another viewer does not see pending commit
    $viewerResult = $query->get($user, $viewer);
    expect($viewerResult['xp'])->toBe(0);
});

it('unlocks syntax badge when a commit contains code blocks', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create([
        'body' => "Check out this code:\n```php\necho 'hello world';\n```",
    ]);

    $result = resolve(UserAchievementsQuery::class)->get($user, $user);

    $syntaxBadge = collect($result['badges'])->firstWhere('id', Achievement::Syntax->value);

    expect($syntaxBadge)->not->toBeNull()
        ->and($syntaxBadge['unlocked'])->toBeTrue()
        ->and($syntaxBadge['progress'])->toBe(1);
});

it('tracks sysadmin badge progress up to 100-day streak', function (): void {
    $user = User::factory()->create();

    foreach (range(0, 10) as $day) {
        Commit::factory()->for($user)->create(['created_at' => now()->subDays($day)]);
    }

    $result = resolve(UserAchievementsQuery::class)->get($user, $user);

    $sysadminBadge = collect($result['badges'])->firstWhere('id', Achievement::Sysadmin->value);

    expect($sysadminBadge)->not->toBeNull()
        ->and($sysadminBadge['unlocked'])->toBeFalse()
        ->and($sysadminBadge['progress'])->toBe(11)
        ->and($sysadminBadge['target'])->toBe(100);
});

it('calculates level progress percentage and rank roadmap accurately', function (): void {
    $user = User::factory()->create();

    // 2 commits = 20 XP + 15 (1-day streak) = 35 XP (user rank: 0 to 50 XP, 35/50 = 70%)
    Commit::factory()->for($user)->count(2)->create();

    $result = resolve(UserAchievementsQuery::class)->get($user, $user);

    expect($result['level'])->toBe(1)
        ->and($result['rank'])->toBe('user')
        ->and($result['xp'])->toBe(35)
        ->and($result['level_progress'])->toBe(70)
        ->and($result['ranks'][0]['achieved'])->toBeTrue()
        ->and($result['ranks'][1]['achieved'])->toBeFalse();
});
