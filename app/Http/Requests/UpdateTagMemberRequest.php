<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TagRole;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTagMemberRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->tag()->isMaintainedBy($user) || $user->isAdmin()
            ? Response::allow()
            : Response::deny('Only maintainers can change roles.');
    }

    /**
     * @return array<string, list<\Illuminate\Validation\Rules\Enum|string>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::enum(TagRole::class)],
        ];
    }

    public function tag(): Tag
    {
        $tag = $this->route('tag');
        assert($tag instanceof Tag);

        return $tag;
    }
}
