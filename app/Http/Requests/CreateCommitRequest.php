<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitImages;
use App\Dto\NewPoll;
use App\Enums\PollDuration;
use App\Models\Commit;
use App\Models\User;
use App\Rules\VisibleCommit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;

final class CreateCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Images are checked by their contents, not their extension, and SVG is never accepted since it can carry scripts.
     *
     * @return array<string, array<int, string|ValidationRule|File|Dimensions|Enum>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'body' => ['required', 'string', 'min:3', 'max:280'],
            'parent_id' => ['nullable', 'uuid', new VisibleCommit($user)],
            'poll' => ['nullable', 'array:options,duration', 'prohibits:parent_id,images'],
            'poll.options' => ['required_with:poll', 'list', 'min:'.NewPoll::MinOptions, 'max:'.NewPoll::MaxOptions],
            'poll.options.*' => ['required', 'string', 'max:'.NewPoll::MaxOptionLength, 'distinct:ignore_case'],
            'poll.duration' => ['required_with:poll', Rule::enum(PollDuration::class)],
            'images' => ['nullable', 'list', 'max:'.CreateCommitImages::MAX_IMAGES],
            'images.*' => [
                'required',
                'image',
                File::types(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])->max(CreateCommitImages::MAX_UPLOAD_SIZE),
                Rule::dimensions()
                    ->maxWidth(CreateCommitImages::MAX_SOURCE_DIMENSION)
                    ->maxHeight(CreateCommitImages::MAX_SOURCE_DIMENSION),
            ],
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
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        $images = $this->file('images');

        return is_array($images) ? array_values($images) : [];
    }

    public function parent(): ?Commit
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->filled('parent_id')
            ? Commit::query()->visibleTo($user)->find($this->string('parent_id')->value())
            : null;
    }
}
