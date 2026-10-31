<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\NewPoll;
use App\Enums\PollDuration;
use App\Models\Commit;
use App\Models\User;
use App\Queries\MentionedUsersQuery;
use App\Rules\VisibleCommit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Validator;

/**
 * A commit from the mobile app, sent as JSON, or with images as multipart (see SignedRequests). It may reply to a commit
 * or quote one, as on the web, but not both. The images are checked as the web checks them. A reply may also go to
 * anyone its parent was addressed to, as the web's Replying to picks them.
 */
final class CreateApiCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Blank lines are collapsed before the body is measured, as on the web.
     */
    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }

    /**
     * @return array<string, array<int, string|ValidationRule|File|Dimensions|Enum|In>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);
        $attachmentRules = CreateCommitRequest::attachmentRules();

        return [
            'body' => CreateCommitRequest::bodyRules($this->hasFile('images')),
            'parent_id' => ['bail', 'nullable', 'uuid', new VisibleCommit($user)],
            'reply_recipients' => ['sometimes', 'list', 'max:'.CreateCommitReplyRequest::MAX_REPLY_RECIPIENTS],
            'reply_recipients.*' => ['required', 'string', 'distinct', Rule::in(array_values($this->parentMentions()))],
            'quoted_commit_id' => ['bail', 'nullable', 'uuid', 'prohibits:parent_id', new VisibleCommit($user, __('That commit is no longer available to quote.'))],
            'poll' => [...$attachmentRules['poll'], 'prohibits:quoted_commit_id'],
            'poll.options' => $attachmentRules['poll.options'],
            'poll.options.*' => $attachmentRules['poll.options.*'],
            'poll.duration' => $attachmentRules['poll.duration'],
            'images' => $attachmentRules['images'],
            'images.*' => $attachmentRules['images.*'],
            'image_descriptions' => $attachmentRules['image_descriptions'],
            'image_descriptions.*' => $attachmentRules['image_descriptions.*'],
        ];
    }

    /**
     * Bound the combined inherited and explicit recipients, as the web does, so reply chains cannot grow notification
     * fanout indefinitely.
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

            if (count(array_unique($recipients)) > CreateCommitReplyRequest::MAX_REPLY_RECIPIENTS) {
                $validator->errors()->add('reply_recipients', 'A reply may include at most '.CreateCommitReplyRequest::MAX_REPLY_RECIPIENTS.' participants across selected recipients and explicit mentions.');
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
     * A parent that went away since it was validated is a 404, so a reply never turns into a top-level commit.
     */
    public function parent(): ?Commit
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->filled('parent_id')
            ? Commit::query()->visibleTo($user)->findOrFail($this->string('parent_id')->value())
            : null;
    }

    public function quotedCommit(): ?Commit
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->filled('quoted_commit_id')
            ? Commit::query()->visibleTo($user)->find($this->string('quoted_commit_id')->value())
            : null;
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

    /**
     * @return list<string|null>
     */
    public function imageDescriptions(): array
    {
        /** @var list<string|null> $descriptions */
        $descriptions = $this->array('image_descriptions');

        return $descriptions;
    }

    /**
     * Only people already addressed by the parent may be included without an explicit body mention.
     *
     * @return array<string, string>
     */
    public function replyRecipients(): array
    {
        /** @var list<string> $recipients */
        $recipients = $this->validated('reply_recipients', []);

        return array_intersect($this->parentMentions(), $recipients);
    }

    /**
     * Who the parent was addressed to, by handle, before the parent itself is validated, so a recipient without one
     * never passes. Only a parent the user can see counts, so the answer never tells who a hidden commit mentions.
     *
     * @return array<string, string>
     */
    private function parentMentions(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        $parentId = $this->input('parent_id');

        return is_string($parentId) && Str::isUuid($parentId)
            ? Commit::query()->visibleTo($user)->find($parentId)->mentions ?? []
            : [];
    }
}
