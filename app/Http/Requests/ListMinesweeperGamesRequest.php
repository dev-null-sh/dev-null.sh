<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use App\Services\Minesweeper;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListMinesweeperGamesRequest extends FormRequest
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
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
