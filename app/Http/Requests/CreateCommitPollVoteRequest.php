<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\PollOption;
use App\Models\User;
use App\Rules\OpenPoll;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class CreateCommitPollVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->commit()->isVisibleTo($this->voter());
    }

    /**
     * @return array<string, array<int, string|Exists|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'option' => [
                'required',
                'uuid',
                Rule::exists(PollOption::class, 'id')->where('poll_id', $this->commit()->poll?->id),
                new OpenPoll($this->commit(), $this->voter(), allowExistingVote: true),
            ],
        ];
    }

    public function option(): PollOption
    {
        return PollOption::query()->findOrFail($this->string('option')->value());
    }

    private function commit(): Commit
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit;
    }

    private function voter(): User
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user;
    }
}
