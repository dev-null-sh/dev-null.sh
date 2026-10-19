<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StarFlavor;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateCommitStarRequest extends FormRequest
{
    /**
     * Stars tell authors that others liked their commit, so authors may not star their own.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return match (true) {
            $commit->user_id === $user->id => Response::deny('You cannot star your own commit.'),
            $commit->isVisibleTo($user) => Response::allow(),
            default => Response::deny(),
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'flavor' => ['nullable', Rule::enum(StarFlavor::class)],
        ];
    }

    public function flavor(): ?StarFlavor
    {
        return $this->enum('flavor', StarFlavor::class);
    }
}
