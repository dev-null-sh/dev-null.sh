<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitImages;
use App\Dto\Gif;
use App\Dto\NewPoll;
use App\Enums\GifProvider;
use App\Enums\PollDuration;
use App\Models\Commit;
use App\Models\User;
use App\Rules\CommitBodyLength;
use App\Rules\GifMediaUrl;
use App\Rules\GifPageUrl;
use App\Rules\VisibleCommit;
use App\Services\CommitBodies;
use App\Services\Urls;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;

final class CreateCommitRequest extends FormRequest
{
    /**
     * Images make text optional. Bound the raw body before parsing links to limit storage and processing costs.
     *
     * @return list<string|ValidationRule>
     */
    public static function bodyRules(bool $hasImages = false): array
    {
        return ['bail', $hasImages ? 'nullable' : 'required', 'string', 'min:'.Commit::MIN_BODY_LENGTH, 'max:'.CommitBodyLength::MAX_RAW_LENGTH, new CommitBodyLength];
    }

    /**
     * Blank lines are collapsed before the body is measured, by every request that takes a commit body.
     */
    public static function normalizeBody(FormRequest $request): void
    {
        if (is_string($request->input('body'))) {
            $request->merge(['body' => resolve(CommitBodies::class)->normalize($request->string('body')->value())]);
        }
    }

    /**
     * Images are checked by their contents, not their extension, and SVG is never accepted since it can carry scripts.
     *
     * @return array<string, array<int, string|ValidationRule|File|Dimensions|Enum>>
     */
    public static function attachmentRules(GifProvider $provider = GifProvider::Giphy): array
    {
        return [
            'poll' => ['nullable', 'array:options,duration', 'prohibits:parent_id,images'],
            'poll.options' => ['required_with:poll', 'list', 'min:'.NewPoll::MinOptions, 'max:'.NewPoll::MaxOptions],
            'poll.options.*' => ['required', 'string', 'max:'.NewPoll::MaxOptionLength, 'distinct:ignore_case'],
            'poll.duration' => ['required_with:poll', Rule::enum(PollDuration::class)],
            'images' => ['nullable', 'list', 'max:'.CreateCommitImages::MAX_IMAGES],
            'images.*' => [
                'required',
                'image',
                File::types(['image/jpeg', 'image/png', 'image/webp'])->max(CreateCommitImages::MAX_UPLOAD_SIZE),
                Rule::dimensions()
                    ->maxWidth(CreateCommitImages::MAX_SOURCE_DIMENSION)
                    ->maxHeight(CreateCommitImages::MAX_SOURCE_DIMENSION),
            ],
            'image_descriptions' => ['nullable', 'list', 'max:'.CreateCommitImages::MAX_IMAGES],
            'image_descriptions.*' => ['nullable', 'string', 'max:'.CreateCommitImages::MAX_DESCRIPTION_LENGTH],
            ...self::gifRules($provider),
        ];
    }

    /**
     * Shared by top-level commits and replies; replies have no poll field, but gif_id still prohibits one.
     *
     * @return array<string, array<int, string|ValidationRule|Enum>>
     */
    public static function gifRules(GifProvider $provider = GifProvider::Giphy): array
    {
        return [
            'gif_provider' => ['nullable', 'string', Rule::enum(GifProvider::class), 'required_with:gif_id'],
            'gif_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/', 'prohibits:images,poll', 'required_with:gif_url'],
            'gif_url' => ['nullable', 'string', 'max:2048', 'required_with:gif_id', new GifMediaUrl($provider)],
            'gif_page_url' => ['nullable', 'string', 'max:2048', 'required_with:gif_id', new GifPageUrl($provider)],
            'gif_title' => ['nullable', 'string', 'max:300'],
            'gif_username' => ['nullable', 'string', 'max:100'],
            'gif_width' => ['nullable', 'integer', 'min:1', 'max:10000', 'required_with:gif_id'],
            'gif_height' => ['nullable', 'integer', 'min:1', 'max:10000', 'required_with:gif_id'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|ValidationRule|File|Dimensions|Enum|In>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);
        $body = $this->input('body');
        $rawProvider = $this->input('gif_provider');
        $provider = is_string($rawProvider) ? (GifProvider::tryFrom($rawProvider) ?? GifProvider::Giphy) : GifProvider::Giphy;

        $attachmentRules = self::attachmentRules($provider);
        $attachmentRules['poll'][] = 'prohibits:quoted_commit_id';

        return [
            'body' => self::bodyRules($this->hasFile('images')),
            'quoted_commit_id' => ['nullable', 'uuid', 'prohibits:parent_id', new VisibleCommit($user, 'That commit is no longer available to quote.')],
            'link_preview_url' => ['nullable', 'string', Rule::in(is_string($body) ? resolve(Urls::class)->all($body) : [])],
            ...$attachmentRules,
        ];
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
            'link_preview_url.in' => 'The link card must be one of the links in the body.',
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

    public function gif(): ?Gif
    {
        if (! $this->filled('gif_id')) {
            return null;
        }

        return new Gif(
            GifProvider::from($this->string('gif_provider')->value()),
            $this->string('gif_id')->value(),
            $this->string('gif_url')->value(),
            $this->string('gif_page_url')->value(),
            $this->filled('gif_title') ? $this->string('gif_title')->value() : null,
            $this->filled('gif_username') ? $this->string('gif_username')->value() : null,
            $this->integer('gif_width'),
            $this->integer('gif_height'),
        );
    }

    public function prepareForValidation(): void
    {
        self::normalizeBody($this);
    }
}
