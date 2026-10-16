<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateEventAttendanceRequest extends FormRequest
{
    /**
     * People go to approved, open events hosted by someone else.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        $event = $commit->event;

        if (! $event instanceof Event) {
            return Response::denyAsNotFound();
        }

        if (! $commit->isVisibleTo($user)) {
            return Response::deny();
        }

        if ($commit->status !== CommitStatus::Approved) {
            return Response::deny(__('This event opens once approved.'));
        }

        if ($commit->user_id === $user->id) {
            return Response::deny(__("You're hosting this event."));
        }

        $closedReason = $event->closedReason();

        return $closedReason === null ? Response::allow() : Response::deny($closedReason);
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    public function event(): Event
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit && $commit->event instanceof Event);

        return $commit->event;
    }
}
