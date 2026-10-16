<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitImages;
use App\Models\Commit;
use App\Models\User;
use App\Rules\CommitMedia;
use App\Services\Urls;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Image\Image as ProcessedImage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;

final class CreateCommitReplyRequest extends FormRequest
{
    /** @var array<int, array{file: UploadedFile, width: int, height: int, poster: ProcessedImage}> */
    private array $animations = [];

    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit->isVisibleTo($user) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Images are checked by their contents, not their extension, and SVG is never accepted since it can carry scripts.
     *
     * @return array<string, array<int, string|ValidationRule|File|Dimensions|In>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:3', 'max:280'],
            'link_preview_url' => ['nullable', 'string', Rule::in(resolve(Urls::class)->all($this->string('body')->value()))],
            'images' => ['nullable', 'list', 'max:'.CreateCommitImages::MAX_IMAGES],
            'images.*' => ['required', new CommitMedia(function (int $position, array $animation): void {
                $this->animations[$position] = $animation;
            })],
            'image_posters' => ['nullable', 'array', 'max:'.CreateCommitImages::MAX_IMAGES],
            'image_posters.*' => ['nullable', 'image', File::types(['png', 'webp'])->max('5mb'), Rule::dimensions()->maxWidth(960)->maxHeight(960)],
            'image_descriptions' => ['nullable', 'list', 'max:'.CreateCommitImages::MAX_IMAGES],
            'image_descriptions.*' => ['nullable', 'string', 'max:'.CreateCommitImages::MAX_DESCRIPTION_LENGTH],
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
     * @return array<int, array{file: UploadedFile, width: int, height: int, poster: ProcessedImage}>
     */
    public function animations(): array
    {
        return $this->animations;
    }

    /** @return array<int, UploadedFile> */
    public function images(): array
    {
        $images = $this->file('images');

        return is_array($images) ? array_diff_key($images, $this->animations) : [];
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
}
