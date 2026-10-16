<?php

declare(strict_types=1);

use App\Enums\MinesweeperGameStatus;
use App\Models\Commit;
use App\Models\MinesweeperGame;
use App\Models\User;
use App\Services\Minesweeper;

function minesweeperCommit(): Commit
{
    return Commit::factory()->create(['body' => '/minesweeper']);
}

it('lists the scores of a minesweeper commit', function (): void {
    $user = User::factory()->create();
    $commit = minesweeperCommit();
    $game = MinesweeperGame::factory()->for($commit)->for($user)->won(12_300)->create();

    $this->actingAs($user)->getJson(route('minesweeper-game.index', $commit))
        ->assertOk()
        ->assertJsonPath('game.id', $game->id)
        ->assertJsonPath('game.status', 'won')
        ->assertJsonPath('game.mines', range(96, 111))
        ->assertJsonPath('best.duration_ms', 12_300)
        ->assertJsonPath('best.user.username', $user->username)
        ->assertJsonPath('personal_best', 12_300)
        ->assertJsonPath('played', 1);
});

it('starts a game on the first cell revealed, without giving away the mines', function (): void {
    $user = User::factory()->create();
    $commit = minesweeperCommit();

    $response = $this->actingAs($user)->postJson(route('minesweeper-game.store', $commit), ['cell' => 56])
        ->assertOk()
        ->assertJsonPath('game.status', 'playing')
        ->assertJsonPath('game.cells.56', 0)
        ->assertJsonPath('game.mines', null)
        ->assertJsonPath('played', 0);

    $game = MinesweeperGame::query()->sole();

    expect($game->user_id)->toBe($user->id)
        ->and($game->commit_id)->toBe($commit->id)
        ->and($response->json('game.id'))->toBe($game->id);
});

it('plays a game to the end', function (): void {
    $user = User::factory()->create();
    $commit = minesweeperCommit();
    $game = MinesweeperGame::factory()->for($commit)->for($user)->create();

    $this->travel(9)->seconds();

    $this->actingAs($user)->putJson(route('minesweeper-game.update', [$commit, $game]), ['cells' => [0]])
        ->assertOk()
        ->assertJsonPath('game.status', 'won')
        ->assertJsonPath('game.duration_ms', 9_000)
        ->assertJsonPath('personal_best', 9_000)
        ->assertJsonPath('best.duration_ms', 9_000)
        ->assertJsonPath('played', 1);

    expect($game->refresh()->status)->toBe(MinesweeperGameStatus::Won);
});

it('loses a game on a mine', function (): void {
    $user = User::factory()->create();
    $commit = minesweeperCommit();
    $game = MinesweeperGame::factory()->for($commit)->for($user)->create(['revealed' => [0]]);

    $this->actingAs($user)->putJson(route('minesweeper-game.update', [$commit, $game]), ['cells' => [80, 96]])
        ->assertOk()
        ->assertJsonPath('game.status', 'lost')
        ->assertJsonPath('game.exploded_cell', 96)
        ->assertJsonPath('personal_best', null)
        ->assertJsonPath('played', 1);
});

it('has no games for other commits', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create(['body' => 'not a game']);

    $this->actingAs($user)->getJson(route('minesweeper-game.index', $commit))->assertNotFound();
    $this->postJson(route('minesweeper-game.store', $commit), ['cell' => 0])->assertNotFound();

    expect(MinesweeperGame::query()->count())->toBe(0);
});

it('hides games of commits the player cannot see', function (string $reason): void {
    $user = User::factory()->create();
    $commit = $reason === 'pending' ? Commit::factory()->pending()->create(['body' => '/minesweeper']) : minesweeperCommit();

    if ($reason === 'blocked') {
        $user->blocking()->attach($commit->user_id);
    }

    $this->actingAs($user)->getJson(route('minesweeper-game.index', $commit))->assertNotFound();
    $this->postJson(route('minesweeper-game.store', $commit), ['cell' => 0])->assertNotFound();
})->with(['pending', 'blocked']);

it('lets authors play their own pending minesweeper commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->pending()->create(['body' => '/minesweeper']);

    $this->actingAs($user)->postJson(route('minesweeper-game.store', $commit), ['cell' => 0])->assertOk();
});

it('only lets its player play a game', function (): void {
    $commit = minesweeperCommit();
    $game = MinesweeperGame::factory()->for($commit)->create();

    $this->actingAs(User::factory()->create())->putJson(route('minesweeper-game.update', [$commit, $game]), ['cells' => [0]])
        ->assertNotFound();

    expect($game->refresh()->status)->toBe(MinesweeperGameStatus::Playing);
});

it('only plays a game under its own commit', function (): void {
    $user = User::factory()->create();
    $game = MinesweeperGame::factory()->for($user)->create();

    $this->actingAs($user)->putJson(route('minesweeper-game.update', [minesweeperCommit(), $game]), ['cells' => [0]])
        ->assertNotFound();
});

it('stops a game once its commit is no longer visible', function (): void {
    $user = User::factory()->create();
    $commit = minesweeperCommit();
    $game = MinesweeperGame::factory()->for($commit)->for($user)->create();
    $user->blocking()->attach($commit->user_id);

    $this->actingAs($user)->putJson(route('minesweeper-game.update', [$commit, $game]), ['cells' => [0]])
        ->assertNotFound();
});

it('validates the cell a game starts on', function (mixed $cell): void {
    $this->actingAs(User::factory()->create())->postJson(route('minesweeper-game.store', minesweeperCommit()), ['cell' => $cell])
        ->assertJsonValidationErrors('cell');
})->with([
    'missing' => null,
    'negative' => -1,
    'off the board' => Minesweeper::CELLS,
    'not a number' => 'a1',
]);

it('validates the cells revealed', function (mixed $cells): void {
    $user = User::factory()->create();
    $commit = minesweeperCommit();
    $game = MinesweeperGame::factory()->for($commit)->for($user)->create();

    $this->actingAs($user)->putJson(route('minesweeper-game.update', [$commit, $game]), ['cells' => $cells])
        ->assertUnprocessable();

    expect($game->refresh()->revealed)->toBe([]);
})->with([
    'missing' => null,
    'empty' => [[]],
    'too many' => [range(0, 8)],
    'twice' => [[1, 1]],
    'off the board' => [[Minesweeper::CELLS]],
    'not a list' => [['a' => 1]],
]);

it('keeps guests out', function (): void {
    $commit = minesweeperCommit();
    $game = MinesweeperGame::factory()->for($commit)->create();

    $this->getJson(route('minesweeper-game.index', $commit))->assertUnauthorized();
    $this->postJson(route('minesweeper-game.store', $commit), ['cell' => 0])->assertUnauthorized();
    $this->putJson(route('minesweeper-game.update', [$commit, $game]), ['cells' => [0]])->assertUnauthorized();
});
