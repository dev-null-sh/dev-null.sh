<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;

final class CreateCommitReportRequest extends FormRequest
{
    /**
     * Only published commits can be reported, so pending and rejected ones look like they do not exist, even to their authors and moderators.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        if ($commit->trashed() || $commit->status !== CommitStatus::Approved || ! $commit->isVisibleTo($user)) {
            return Response::denyAsNotFound();
        }

        return $commit->user_id !== $user->id
            ? Response::allow()
            : Response::deny('You cannot report your own commit.');
    }

    /**
     * @return array<string, array<int, string|In|ValidationRule|Dimensions|Enum|File>>
     */
    public function rules(): array
    {
        return [
            ...array_intersect_key(CreateCommitRequest::attachmentRules(), array_flip(['images', 'images.*', 'image_descriptions', 'image_descriptions.*'])),
            'reason' => ['required', 'string', Rule::in(array_keys(CommitReport::REASONS))],
            'details' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return list<UploadedFile> */
    public function images(): array
    {
        $images = $this->file('images');

        return is_array($images) ? array_values($images) : [];
    }

    /** @return list<string|null> */
    public function imageDescriptions(): array
    {
        /** @var list<string|null> $descriptions */
        $descriptions = $this->array('image_descriptions');

        return $descriptions;
    }
}
