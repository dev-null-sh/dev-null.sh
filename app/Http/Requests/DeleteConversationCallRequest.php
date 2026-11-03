<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgEvent;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteConversationCallRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $partner = $this->route('partner');
        assert($partner instanceof User);

        return $user->canMessage($partner) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'failed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * What the user ending a call they didn't pick up did: declined it, or tried to answer and couldn't.
     */
    public function unanswered(): OrgEvent
    {
        return $this->boolean('failed') ? OrgEvent::CallFailed : OrgEvent::CallDeclined;
    }
}
