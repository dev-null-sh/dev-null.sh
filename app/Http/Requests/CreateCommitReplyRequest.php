<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitImages;
use App\Models\Commit;
use App\Models\User;
use App\Queries\MentionedUsersQuery;
use App\Services\Urls;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Validator;

final class CreateCommitReplyRequest extends FormRequest
{
    public const int MAX_REPLY_RECIPIENTS = 100;

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
     * @return array<string, array<int, string|ValidationRule|File|Dimensions|In>>
     */
    public function rules(): array
    {
        $body = $this->input('body');
        $parent = $this->route('commit');
        assert($parent instanceof Commit);

        return [
            'reply_recipients' => ['sometimes', 'list', 'max:'.self::MAX_REPLY_RECIPIENTS],
            'reply_recipients.*' => ['required', 'string', 'distinct', Rule::in(array_values($parent->mentions ?? []))],
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
        ];
    }

    /**
     * Bound the combined inherited and explicit recipients so reply chains cannot grow notification fanout indefinitely.
     *
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $recipients = [...$this->replyRecipients(), ...resolve(MentionedUsersQuery::class)->get($this->string('body')->value())];

            if (count(array_unique($recipients)) > self::MAX_REPLY_RECIPIENTS) {
                $validator->errors()->add('reply_recipients', 'A reply may include at most '.self::MAX_REPLY_RECIPIENTS.' participants across selected recipients and explicit mentions.');
            }
        }];
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
     * Only people already addressed by the parent may be included without an explicit body mention.
     *
     * @return array<string, string>
     */
    public function replyRecipients(): array
    {
        $parent = $this->route('commit');
        assert($parent instanceof Commit);

        /** @var list<string> $recipients */
        $recipients = $this->validated('reply_recipients', []);

        return array_intersect($parent->mentions ?? [], $recipients);
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
