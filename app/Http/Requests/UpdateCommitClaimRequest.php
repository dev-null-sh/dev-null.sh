<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Rules\Unclaimed;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\Rule;

final class UpdateCommitClaimRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        if (! $user->isAdmin()) {
            return Response::deny();
        }

        return $commit->status === CommitStatus::Pending ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Another admin's claim is only taken over when asked to.
     *
     * @return array<string, array<int, string|ValidationRule|ConditionalRules>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'take_over' => ['boolean'],
            'commit' => [Rule::when(! $this->boolean('take_over'), [new Unclaimed($user)])],
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function validationData(): array
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return [...$this->all(), 'commit' => $commit->id];
    }
}
