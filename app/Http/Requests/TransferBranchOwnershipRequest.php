<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class TransferBranchOwnershipRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $branch = $this->branch();

        return $branch->isMaintainedBy($user) || $user->isAdmin()
            ? Response::allow()
            : Response::deny('Only maintainers can transfer ownership.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'exists:users,username'],
        ];
    }

    public function branch(): Branch
    {
        $branch = $this->route('branch');
        assert($branch instanceof Branch);

        return $branch;
    }

    public function target(): User
    {
        return User::query()->where('username', $this->string('username')->toString())->firstOrFail();
    }
}
