<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitImages;
use App\Dto\Gif;
use App\Enums\GifProvider;
use App\Models\Commit;
use App\Models\User;
use App\Services\Urls;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;

final class CreateCommitReplyRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit->isVisibleTo($user) ? Response::allow() : Response::denyAsNotFound();
    }

    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }

    /**
     * Images are checked by their contents, not their extension, and SVG is never accepted since it can carry scripts.
     *
     * @return array<string, array<int, string|ValidationRule|File|Dimensions|Enum|In>>
     */
    public function rules(): array
    {
        $body = $this->input('body');
        $rawProvider = $this->input('gif_provider');
        $provider = is_string($rawProvider) ? (GifProvider::tryFrom($rawProvider) ?? GifProvider::Giphy) : GifProvider::Giphy;

        return [
            'body' => CreateCommitRequest::bodyRules($this->hasFile('images')),
            'link_preview_url' => ['nullable', 'string', Rule::in(is_string($body) ? resolve(Urls::class)->all($body) : [])],
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
            ...CreateCommitRequest::gifRules($provider),
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
}
