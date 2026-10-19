<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateTagDomainRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $tag = $this->tag();

        return $tag->isMaintainedBy($user) || $user->isAdmin()
            ? Response::allow()
            : Response::deny('Only maintainers can change the domain.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:253'],
        ];
    }

    public function tag(): Tag
    {
        $tag = $this->route('tag');
        assert($tag instanceof Tag);

        return $tag;
    }
}
