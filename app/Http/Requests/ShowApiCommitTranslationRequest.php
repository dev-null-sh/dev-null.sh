<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Services\CommitTranslations;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowApiCommitTranslationRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return (($commit->status === CommitStatus::Approved && $commit->isVisibleTo($user))
            || ($commit->status === CommitStatus::Pending && $user->isAdmin()))
            && $commit->body !== '' && resolve(CommitTranslations::class)->enabled()
            ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'language' => ['required', 'string', Rule::in(resolve(CommitTranslations::class)->languages())],
        ];
    }
}
