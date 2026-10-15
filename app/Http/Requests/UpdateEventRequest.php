<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\EventDetails;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\Event;
use App\Models\User;
use App\Services\Images;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class UpdateEventRequest extends FormRequest
{
    /**
     * Only the host changes an event, and only while it is open.
     */
    public function authorize(): Response
    {
        $commit = $this->commit();
        $user = $this->user();
        assert($user instanceof User);

        $event = $commit->event;

        if (! $event instanceof Event) {
            return Response::denyAsNotFound();
        }

        if ($commit->user_id !== $user->id) {
            return Response::deny();
        }

        $closedReason = $event->closedReason();

        return $closedReason === null ? Response::allow() : Response::deny($closedReason);
    }

    /**
     * The title is not accepted at all: it stays as it was committed.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'array:starts_at,ends_at,timezone,format,venue,address,platform,join_url,cover'],
            ...EventDetails::rules($this->event()->starts_at),
            'event.cover' => [
                Rule::prohibitedIf($this->commit()->status !== CommitStatus::Pending),
                'nullable',
                ...Images::uploadRules(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['event.cover.prohibited' => __("The cover can't change after approval.")];
    }

    public function details(): EventDetails
    {
        return EventDetails::fromInput((array) $this->validated('event'));
    }

    public function cover(): ?UploadedFile
    {
        $cover = $this->file('event.cover');

        return $cover instanceof UploadedFile ? $cover : null;
    }

    public function event(): Event
    {
        $event = $this->commit()->event;
        assert($event instanceof Event);

        return $event;
    }

    private function commit(): Commit
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit;
    }
}
