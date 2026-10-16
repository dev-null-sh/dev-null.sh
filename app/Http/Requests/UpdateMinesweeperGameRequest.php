<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\MinesweeperGame;
use App\Models\User;
use App\Services\Minesweeper;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateMinesweeperGameRequest extends FormRequest
{
    /**
     * Only its player plays a game, and only while they can still see its commit.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        $game = $this->route('minesweeperGame');
        assert($game instanceof MinesweeperGame);

        return $game->user_id === $user->id && $commit->isVisibleTo($user) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * A cell, or the hidden cells around a revealed one when chording.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'cells' => ['required', 'list', 'min:1', 'max:8'],
            'cells.*' => ['required', 'integer', 'distinct', 'between:0,'.(Minesweeper::CELLS - 1)],
        ];
    }

    /**
     * @return list<int>
     */
    public function cells(): array
    {
        /** @var list<int|string> $cells */
        $cells = $this->array('cells');

        return array_map(intval(...), $cells);
    }
}
