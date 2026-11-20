<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PastelVariant;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCommitPastelRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return match (true) {
            ! $user->pasteis_mode => Response::deny(),
            $commit->isOwnedBy($user) => Response::deny('You cannot award your own commit.'),
            $commit->user_id === null, $commit->author_id !== $commit->user_id, ! $commit->isVisibleTo($user) => Response::denyAsNotFound(),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['variant' => ['sometimes', 'string', 'in:original,diamond']];
    }

    public function variant(): PastelVariant
    {
        return PastelVariant::from($this->string('variant', 'original')->value());
    }
}
