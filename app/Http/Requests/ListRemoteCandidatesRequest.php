<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ConversationKind;
use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListRemoteCandidatesRequest extends FormRequest
{
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
            'for' => ['nullable', 'in:remote,org,share'],
        ];
    }

    public function isSharing(): bool
    {
        return $this->input('for') === 'share';
    }

    /**
     * The org people are being picked for: an existing one, a new one not created yet, or none when picking someone
     * to message.
     */
    public function org(): ?Conversation
    {
        return match (true) {
            $this->filled('org') => Conversation::query()->whereKey($this->string('org')->value())->firstOrFail(),
            $this->input('for') === 'org' => new Conversation(['kind' => ConversationKind::Org]),
            default => null,
        };
    }
}
