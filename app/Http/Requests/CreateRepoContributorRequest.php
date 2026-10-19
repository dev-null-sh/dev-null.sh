<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Repo;
use App\Models\User;
use App\Queries\RepoContributorCandidatesQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

final class CreateRepoContributorRequest extends FormRequest
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
            'contributors' => ['required', 'array', 'min:1', 'max:20'],
            'contributors.*' => ['required', 'string', 'distinct', 'exists:users,username'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            assert($user instanceof User);

            $reasons = resolve(RepoContributorCandidatesQuery::class)->reasons($user, $this->contributors(), $this->repo());

            foreach ($this->contributors() as $index => $contributor) {
                if ($reasons[$contributor->id] !== null) {
                    $validator->errors()->add("contributors.{$index}", "@{$contributor->username} {$reasons[$contributor->id]}.");
                }
            }
        }];
    }

    public function repo(): Repo
    {
        $repo = $this->route('repo');
        assert($repo instanceof Repo);

        return $repo;
    }

    /**
     * @return Collection<int, User>
     */
    public function contributors(): Collection
    {
        /** @var list<string> $usernames */
        $usernames = $this->array('contributors');

        return User::query()->whereIn('username', $usernames)->get()->sortBy(fn (User $user): int|false => array_search($user->username, $usernames, true))->values()->toBase();
    }
}
