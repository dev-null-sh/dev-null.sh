<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListConversationCallIceServersRequest extends FormRequest
{
    /**
     * Relay credentials cost money, so they only go to someone about to call, or answer, this partner.
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
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
