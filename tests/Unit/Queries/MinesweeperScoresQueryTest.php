<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\MinesweeperGame;
use App\Models\User;
use App\Queries\MinesweeperScoresQuery;

it('has no scores before anyone played', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create(['body' => '/minesweeper']);

    expect(resolve(MinesweeperScoresQuery::class)->get($commit, $user))->toBe([
        'game' => null,
        'best' => null,
        'personal_best' => null,
        'played' => 0,
    ]);
});

it('scores the player and the commit', function (): void {
    $user = User::factory()->create();
    $fastest = User::factory()->create(['username' => 'taylorotwell', 'name' => 'Taylor Otwell']);
    $blocked = User::factory()->create();
    $user->blocking()->attach($blocked);
    $commit = Commit::factory()->create(['body' => '/minesweeper']);

    MinesweeperGame::factory()->for($commit)->for($fastest)->won(20_000)->create();
    MinesweeperGame::factory()->for($commit)->for($blocked)->won(5_000)->create();
    MinesweeperGame::factory()->for($commit)->for($user)->won(30_000)->create();
    MinesweeperGame::factory()->for($user)->won(25_000)->create();
    MinesweeperGame::factory()->for($commit)->for($user)->lost(1_000)->create();
    MinesweeperGame::factory()->for($commit)->create();

    $this->travel(1)->second();
    $latest = MinesweeperGame::factory()->for($commit)->for($user)->create();

    $scores = resolve(MinesweeperScoresQuery::class)->get($commit, $user);

    expect($scores['game']?->is($latest))->toBeTrue()
        ->and($scores['best'])->toBe([
            'duration_ms' => 20_000,
            'user' => ['username' => 'taylorotwell', 'name' => 'Taylor Otwell'],
        ])
        ->and($scores['personal_best'])->toBe(25_000)
        ->and($scores['played'])->toBe(4);
});
