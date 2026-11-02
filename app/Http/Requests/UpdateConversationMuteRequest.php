<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ConversationMuteDuration;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateConversationMuteRequest extends FormRequest
{
    /**
     * There is nothing to mute before the conversation starts, and muting never starts one.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $partner = $this->route('partner');
        assert($partner instanceof User);

        return $user->canMessage($partner) && Conversation::between($user, $partner) instanceof Conversation
            ? Response::allow()
            : Response::denyAsNotFound();
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
