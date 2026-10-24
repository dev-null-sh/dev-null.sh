<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Models\UserList;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CreateUserListMemberRequest extends FormRequest
{
    /**
     * A list holds other developers, never across a block.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $list = $this->route('userList');
        assert($list instanceof UserList);

        $member = $this->route('user');
        assert($member instanceof User);

        if (! $list->isOwnedBy($user)) {
            return Response::denyAsNotFound();
        }

        return $user->isNot($member) && ! $user->hasBlockWith($member) ? Response::allow() : Response::deny();
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

                if ($list->members()->count() >= UserList::MAX_MEMBERS) {
                    $validator->errors()->add('list', 'A list holds up to '.UserList::MAX_MEMBERS.' developers.');
                }
            },
        ];
    }
}
