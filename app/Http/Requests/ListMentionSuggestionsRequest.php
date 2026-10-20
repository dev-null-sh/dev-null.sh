<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Models\Commit;
use App\Models\Conversation;
use App\Models\User;
use App\Rules\VisibleCommit;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ListMentionSuggestionsRequest extends FormRequest
{
    /**
     * Suggesting an org's members is only for its members, so it doesn't reveal who is in an org to anyone else.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $org = $this->org();

        return ! $org instanceof Conversation || $org->roleOf($user) instanceof OrgRole ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'q' => ['nullable', 'string', 'max:100'],
            'org' => ['nullable', 'uuid', 'prohibits:reply,quote'],
            'reply' => ['bail', 'nullable', 'uuid', 'prohibits:quote', new VisibleCommit($user)],
            'quote' => ['bail', 'nullable', 'uuid', new VisibleCommit($user)],
        ];
    }

    public function reply(): ?Commit
    {
        return $this->filled('reply') ? Commit::query()->find($this->string('reply')->value()) : null;
    }

    public function quote(): ?Commit
    {
        return $this->filled('quote') ? Commit::query()->find($this->string('quote')->value()) : null;
    }

    public function org(): ?Conversation
    {
        return $this->filled('org') && is_string($this->query('org'))
            ? Conversation::query()->find($this->query('org'))
            : null;
    }
}
