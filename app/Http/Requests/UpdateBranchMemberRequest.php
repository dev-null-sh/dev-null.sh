<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBranchMemberRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->branch()->isMaintainedBy($user) || $user->isAdmin()
            ? Response::allow()
            : Response::deny('Only maintainers can change roles.');
    }

    /**
     * @return array<string, list<\Illuminate\Validation\Rules\Enum|string>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::enum(BranchRole::class)],
        ];
    }

    public function branch(): Branch
    {
        $branch = $this->route('branch');
        assert($branch instanceof Branch);

        return $branch;
    }
}
