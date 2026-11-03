<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\Call;
use App\Models\User;
use App\Queries\CallQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateConversationCallRequest extends FormRequest
{
    /**
     * Only a call the partner is ringing the user with can be answered, and only once.
     */
    public function authorize(CallQuery $calls): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $partner = $this->route('partner');
        assert($partner instanceof User);

        if (! $user->canMessage($partner)) {
            return Response::denyAsNotFound();
        }

        return ($calls->incoming($user)['partner']->id ?? null) === $partner->id
            ? Response::allow()
            : Response::deny('This call has ended.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sdp' => ['required', 'string', 'starts_with:v=0', 'max:10000'],
        ];
    }

    /**
     * The call being answered.
     */
    public function call(): Call
    {
        $user = $this->user();
        assert($user instanceof User);

        $call = resolve(CallQuery::class)->get($user);
        assert($call !== null);

        return $call;
    }
}
