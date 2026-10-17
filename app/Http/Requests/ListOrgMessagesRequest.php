<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListOrgMessagesRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $org = $this->route('org');
        assert($org instanceof Conversation);

        return $user->remotes_enabled && $org->roleOf($user) instanceof OrgRole ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'after' => ['nullable', 'integer', 'min:0'],
            'before' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function afterId(): ?int
    {
        return $this->filled('after') ? $this->integer('after') : null;
    }

    public function beforeId(): ?int
    {
        return $this->filled('before') ? $this->integer('before') : null;
    }
}
