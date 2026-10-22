<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\NewEvent;
use App\Models\Event;
use App\Rules\EventDuration;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class CreateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The description is the body of the commit announcing the event, so it follows the same rules as any commit.
     *
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'body' => CreateCommitRequest::bodyRules(),
            'title' => ['required', 'string', 'max:'.Event::MAX_TITLE_LENGTH],
            'starts_at' => ['required', 'date', 'after:now', 'before:+1 year'],
            'ends_at' => ['required', 'date', 'after:starts_at', new EventDuration($this->input('starts_at'))],
            'location' => ['nullable', 'string', 'max:'.Event::MAX_LOCATION_LENGTH, 'required_without:url'],
            'url' => ['nullable', 'string', 'url:http,https', 'max:'.Event::MAX_URL_LENGTH, 'required_without:location'],
            'agenda' => ['nullable', 'list', 'max:'.Event::MAX_AGENDA_ITEMS],
            'agenda.*' => ['array:label,starts_at'],
            'agenda.*.label' => ['required', 'string', 'max:'.Event::MAX_AGENDA_LABEL_LENGTH],
            'agenda.*.starts_at' => ['required', 'date', 'after_or_equal:starts_at', 'before:ends_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => 'description',
            'starts_at' => 'start',
            'ends_at' => 'end',
            'url' => 'link',
            'agenda.*.label' => 'agenda item',
            'agenda.*.starts_at' => 'agenda time',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location.required_without' => 'Add a place, a link, or both.',
            'url.required_without' => 'Add a place, a link, or both.',
            'agenda.*.starts_at.after_or_equal' => 'Agenda items happen during the event.',
            'agenda.*.starts_at.before' => 'Agenda items happen during the event.',
        ];
    }

    public function newEvent(): NewEvent
    {
        /** @var list<array{label: string, starts_at: string}> $agenda */
        $agenda = $this->array('agenda');

        return new NewEvent(
            title: $this->string('title')->trim()->value(),
            startsAt: CarbonImmutable::parse($this->string('starts_at')->value()),
            endsAt: CarbonImmutable::parse($this->string('ends_at')->value()),
            location: $this->filled('location') ? $this->string('location')->trim()->value() : null,
            url: $this->filled('url') ? $this->string('url')->value() : null,
            agenda: array_map(fn (array $item): array => [
                'label' => mb_trim($item['label']),
                'starts_at' => CarbonImmutable::parse($item['starts_at']),
            ], $agenda),
        );
    }

    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }
}
