<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBranchDescriptionRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $branch = $this->branch();

        return $branch->roleOf($user) !== null
            ? Response::allow()
            : Response::deny('Only branch members can edit the description.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:'.Branch::MAX_DESCRIPTION_LENGTH],
        ];
    }

    public function branch(): Branch
    {
        $branch = $this->route('branch');
        assert($branch instanceof Branch);

        return $branch;
    }
}
