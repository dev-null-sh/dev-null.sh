<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EventState;
use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateEventCancellationRequest extends FormRequest
{
    /**
     * Only the host cancels, and only an event that hasn't ended or been cancelled already.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $event = $this->route('event');
        assert($event instanceof Event);

        if (! $event->isVisibleTo($user) || ! $event->isHostedBy($user)) {
            return Response::denyAsNotFound();
        }

        return in_array($event->state(), [EventState::Upcoming, EventState::Live], true)
            ? Response::allow()
            : Response::deny('This event is already over.');
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
