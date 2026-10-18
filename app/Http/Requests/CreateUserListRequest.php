<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Models\UserList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CreateUserListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.UserList::MAX_NAME_LENGTH],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                assert($user instanceof User);

                if ($user->lists()->count() >= UserList::MAX_PER_USER) {
                    $validator->errors()->add('name', 'You can have up to '.UserList::MAX_PER_USER.' lists. Delete one to make room.');
                }
            },
        ];
    }

    public function name(): string
    {
        return $this->string('name')->squish()->value();
    }
}
