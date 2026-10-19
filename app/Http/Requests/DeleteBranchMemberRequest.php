<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteBranchMemberRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $member = $this->member();
        $branch = $this->branch();

        if ($user->id === $member->id) {
            return Response::allow();
        }

        return $branch->isMaintainedBy($user) || $user->isAdmin()
            ? Response::allow()
            : Response::deny('Only maintainers can remove other people.');
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

    public function member(): User
    {
        $member = $this->route('member');
        assert($member instanceof User);

        return $member;
    }
}
