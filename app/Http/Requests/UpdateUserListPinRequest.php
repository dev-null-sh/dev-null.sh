<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Models\UserList;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateUserListPinRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $list = $this->route('userList');
        assert($list instanceof UserList);

        return $list->isOwnedBy($user) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $list = $this->route('userList');
                assert($list instanceof UserList);

                if (! $list->isPinned() && $list->user->lists()->whereNotNull('pinned_at')->count() >= UserList::MAX_PINNED) {
                    $validator->errors()->add('list', 'You can pin up to '.UserList::MAX_PINNED.' lists. Unpin one first.');
                }
            },
        ];
    }
}
