<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyBranchRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $branch = $this->branch();

        if (! $branch->isMaintainedBy($user) && ! $user->isAdmin()) {
            return Response::deny('Only maintainers can verify this branch.');
        }

        return Conversation::userMayVerifyBranch($user, $branch->name)
            ? Response::allow()
            : Response::deny('This branch matches a Remotes org; only that org\'s maintainers can verify it.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function branch(): Branch
    {
        $branch = $this->route('branch');
        assert($branch instanceof Branch);

        return $branch;
    }
}
