<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EventResponse;
use App\Models\Event;
use App\Models\User;
use App\Rules\OpenEvent;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateEventAttendanceRequest extends FormRequest
{
    public function authorize(): Response
    {
        return $this->event()->isVisibleTo($this->user()) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, string|Enum|ValidationRule>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'response' => ['required', Rule::enum(EventResponse::class), new OpenEvent($this->event(), $user)],
        ];
    }

    public function eventResponse(): EventResponse
    {
        return EventResponse::from($this->string('response')->value());
    }

    private function event(): Event
    {
        $event = $this->route('event');
        assert($event instanceof Event);

        return $event;
    }
}
