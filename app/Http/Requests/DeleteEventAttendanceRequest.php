<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteEventAttendanceRequest extends FormRequest
{
    /**
     * Taking a response back is always allowed, even once the event is over, as long as the event can be seen.
     */
    public function authorize(): Response
    {
        $event = $this->route('event');
        assert($event instanceof Event);

        return $event->isVisibleTo($this->user()) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
