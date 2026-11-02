<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ConversationMuteDuration;
use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateApiOrgMuteRequest extends FormRequest
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
     * @return array<string, array<int, string|Enum>>
     */
    public function rules(): array
    {
        return [
            'duration' => ['required', 'string', Rule::enum(ConversationMuteDuration::class)],
        ];
    }

    public function duration(): ConversationMuteDuration
    {
        return ConversationMuteDuration::from($this->string('duration')->value());
    }
}
