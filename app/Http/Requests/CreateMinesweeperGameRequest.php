<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use App\Services\Minesweeper;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateMinesweeperGameRequest extends FormRequest
{
    /**
     * Anyone who can see a /minesweeper commit can play it. Any other commit has no games.
     */
    public function authorize(Minesweeper $minesweeper): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit->isVisibleTo($user) && $minesweeper->matches($commit->body) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'cell' => ['required', 'integer', 'between:0,'.(Minesweeper::CELLS - 1)],
        ];
    }

    public function cell(): int
    {
        return $this->integer('cell');
    }
}
