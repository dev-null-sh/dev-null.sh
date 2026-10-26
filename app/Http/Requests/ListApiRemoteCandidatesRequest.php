<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ConversationKind;
use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class ListApiRemoteCandidatesRequest extends FormRequest
{
    /**
     * As on the web, people can only be picked for an org the user is in.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $org = $this->org();

        return $user->remotes_enabled && (! $org instanceof Conversation || ! $org->exists || $org->roleOf($user) instanceof OrgRole)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'org' => ['nullable', 'uuid'],
            'for' => ['nullable', 'in:remote,org'],
        ];
    }

    /**
     * The org people are being picked for: an existing one, a new one not created yet, or none when picking someone
     * to message. An id that isn't a UUID is left for validation to refuse, rather than looked up.
     */
    public function org(): ?Conversation
    {
        return match (true) {
            Str::isUuid($this->input('org')) => Conversation::query()->whereKey($this->string('org')->value())->firstOrFail(),
            $this->input('for') === 'org' => new Conversation(['kind' => ConversationKind::Org]),
            default => null,
        };
    }
}
