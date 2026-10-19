<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Repo;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteRepoContributorRequest extends FormRequest
{
    /**
     * Anyone can leave; only maintainers can remove someone else.
     */
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
            ! $user->is($contributor) && ! $repo->isMaintainedBy($user) => Response::deny('Only maintainers can remove contributors.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
