<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowEventCalendarRequest extends FormRequest
{
    public function authorize(): Response
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        $user = $this->user();
        assert($user instanceof User);

        return $commit->isVisibleTo($user) && $commit->event instanceof Event
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

    public function event(): Event
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit && $commit->event instanceof Event);

        return $commit->event;
    }
}
