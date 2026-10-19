<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Repo;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListRepoContributorCandidatesRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return match (true) {
            ! $this->repo()->hasContributor($user) => Response::denyAsNotFound(),
            ! $this->repo()->isMaintainedBy($user) => Response::deny('Only maintainers can add contributors.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function repo(): Repo
    {
        $repo = $this->route('repo');
        assert($repo instanceof Repo);

        return $repo;
    }
}
