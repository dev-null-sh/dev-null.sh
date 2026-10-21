<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Exceptions\ApiException;
use App\Models\Commit;
use App\Models\User;
use App\Services\CommitTranslations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateApiCommitTranslationRequest extends FormRequest
{
    /**
     * As on the web, only a published commit the user may see can be translated. Pending, rejected, deleted, empty,
     * and commits across a block look the same, and so does a translation while the feature is off. Checked before
     * the provider runs, so a hidden post cannot fill the shared translation cache.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        throw_if($commit->trashed() || $commit->status !== CommitStatus::Approved || ! $commit->isVisibleTo($user) || $commit->body === '' || ! resolve(CommitTranslations::class)->enabled(), fn (): ApiException => ApiException::commitUnavailable());

        return true;
    }

    /**
     * @return array{language: array<int, string|In>}
     */
    public function rules(): array
    {
        return [
            'language' => ['required', 'string', Rule::in(resolve(CommitTranslations::class)->languages())],
        ];
    }
}
