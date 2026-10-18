<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Models\UserList;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteUserListRequest extends FormRequest
{
    /**
     * Lists are private, so someone else's list does not exist as far as the user can tell.
     */
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
}
