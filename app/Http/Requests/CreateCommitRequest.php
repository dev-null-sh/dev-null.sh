<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitImages;
use App\Models\Commit;
use App\Models\User;
use App\Rules\VisibleCommit;
use App\Services\Urls;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;

final class CreateCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Images are checked by their contents, not their extension, and SVG is never accepted since it can carry scripts.
     *
     * @return array<string, array<int, string|ValidationRule|File|Dimensions|In>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'body' => ['required', 'string', 'min:3', 'max:280'],
            'parent_id' => ['nullable', 'uuid', new VisibleCommit($user)],
            'link_preview_url' => ['nullable', 'string', Rule::in(resolve(Urls::class)->all($this->string('body')->value()))],
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

    public function parent(): ?Commit
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->filled('parent_id')
            ? Commit::query()->visibleTo($user)->find($this->string('parent_id')->value())
            : null;
    }
}
