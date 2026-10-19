<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateTagDescriptionRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $tag = $this->tag();

        return $tag->isMaintainedBy($user) || $user->isAdmin()
            ? Response::allow()
            : Response::deny('Only maintainers can edit the description.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:'.Tag::MAX_DESCRIPTION_LENGTH],
        ];
    }

    public function tag(): Tag
    {
        $tag = $this->route('tag');
        assert($tag instanceof Tag);

        return $tag;
    }
}
