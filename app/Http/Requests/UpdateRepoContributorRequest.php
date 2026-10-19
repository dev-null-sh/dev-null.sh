<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RepoRole;
use App\Models\Repo;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

final class UpdateRepoContributorRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $repo = $this->route('repo');
        assert($repo instanceof Repo);

        $contributor = $this->route('contributor');
        assert($contributor instanceof User);

        return match (true) {
            ! $repo->hasContributor($user) || ! $repo->hasContributor($contributor) => Response::denyAsNotFound(),
            ! $repo->isMaintainedBy($user) => Response::deny('Only maintainers can change roles.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(RepoRole::class)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $repo = $this->route('repo');
            assert($repo instanceof Repo);

            $contributor = $this->route('contributor');
            assert($contributor instanceof User);

            if ($this->role() === RepoRole::Contributor && $repo->isLastMaintainer($contributor)) {
                $validator->errors()->add('role', 'A repo needs at least one maintainer.');
            }
        }];
    }

    public function role(): ?RepoRole
    {
        return $this->enum('role', RepoRole::class);
    }
}
