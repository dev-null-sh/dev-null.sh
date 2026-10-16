<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteEventAttendanceRequest extends FormRequest
{
    /**
     * Leaving is always allowed on a visible event, whatever its state.
     */
    public function authorize(): Response
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        $user = $this->user();
        assert($user instanceof User);

        if (! $commit->event instanceof Event) {
            return Response::denyAsNotFound();
        }

        return $commit->isVisibleTo($user) ? Response::allow() : Response::deny();
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
