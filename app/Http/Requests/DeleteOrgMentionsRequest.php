<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteOrgMentionsRequest extends FormRequest
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
            'message_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The one mention to clear, or none to clear them all.
     */
    public function messageId(): ?int
    {
        return $this->filled('message_id') ? $this->integer('message_id') : null;
    }
}
