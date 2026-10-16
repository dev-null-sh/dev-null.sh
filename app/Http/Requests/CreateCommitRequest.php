<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitImages;
use App\Dto\EventDetails;
use App\Dto\NewEvent;
use App\Dto\NewPoll;
use App\Enums\PollDuration;
use App\Models\Commit;
use App\Models\User;
use App\Rules\VisibleCommit;
use App\Services\Images;
use App\Services\Urls;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class CreateCommitRequest extends FormRequest
{
    public const array BODY_RULES = ['required', 'string', 'min:3', 'max:280'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'body' => self::BODY_RULES,
            'quoted_commit_id' => ['nullable', 'uuid', 'prohibits:parent_id', new VisibleCommit($user, __('That commit is no longer available to quote.'))],
            'link_preview_url' => ['nullable', 'string', Rule::in(resolve(Urls::class)->all($this->string('body')->value()))],
            'poll' => ['nullable', 'array:options,duration', 'prohibits:parent_id,images'],
            'poll.options' => ['required_with:poll', 'list', 'min:'.NewPoll::MinOptions, 'max:'.NewPoll::MaxOptions],
            'poll.options.*' => ['required', 'string', 'max:'.NewPoll::MaxOptionLength, 'distinct:ignore_case'],
            'poll.duration' => ['required_with:poll', Rule::enum(PollDuration::class)],
            'images' => ['nullable', 'list', 'max:'.CreateCommitImages::MAX_IMAGES],
            'images.*' => ['required', ...Images::uploadRules()],
            'image_descriptions' => ['nullable', 'list', 'max:'.CreateCommitImages::MAX_IMAGES],
            'image_descriptions.*' => ['nullable', 'string', 'max:'.CreateCommitImages::MAX_DESCRIPTION_LENGTH],
            'event' => ['nullable', 'array:title,starts_at,ends_at,timezone,format,venue,address,platform,join_url,cover', 'prohibits:parent_id,images,poll'],
            'event.title' => ['required_with:event', 'string', 'max:120'],
            ...EventDetails::rules(),
            'event.cover' => ['nullable', ...Images::uploadRules()],
        ];
    }

    public function event(): ?NewEvent
    {
        if (blank($this->input('event'))) {
            return null;
        }

        $cover = $this->file('event.cover');

        return new NewEvent(
            title: $this->string('event.title')->trim()->value(),
            details: EventDetails::fromInput((array) $this->validated('event')),
            cover: $cover instanceof UploadedFile ? $cover : null,
        );
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'image_descriptions.*' => 'image description',
        ];
    }

    public function poll(): ?NewPoll
    {
        if (! $this->filled('poll')) {
            return null;
        }

        /** @var list<string> $options */
        $options = $this->array('poll.options');

        return new NewPoll($options, PollDuration::from($this->integer('poll.duration')));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'link_preview_url.in' => __('The link card must be one of the links in the body.'),
        ];
    }

    /**
     * The first link in the body gets the link preview, unless the author chose another link or none at all.
     */
    public function linkPreview(): bool|string
    {
        if (! $this->has('link_preview_url')) {
            return true;
        }

        return $this->filled('link_preview_url') ? $this->string('link_preview_url')->value() : false;
    }

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        $images = $this->file('images');

        return is_array($images) ? array_values($images) : [];
    }

    /**
     * @return list<string|null>
     */
    public function imageDescriptions(): array
    {
        /** @var list<string|null> $descriptions */
        $descriptions = $this->array('image_descriptions');

        return $descriptions;
    }

    public function quotedCommit(): ?Commit
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->filled('quoted_commit_id')
            ? Commit::query()->visibleTo($user)->find($this->string('quoted_commit_id')->value())
            : null;
    }
}
