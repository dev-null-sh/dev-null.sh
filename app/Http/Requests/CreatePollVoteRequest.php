<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\PollOption;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class CreatePollVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->commit()->isVisibleTo($user);
    }

    /**
     * @return array<string, array<int, string|Exists>>
     */
    public function rules(): array
    {
        return [
            'option' => ['required', 'uuid', Rule::exists(PollOption::class, 'id')->where('poll_id', $this->commit()->poll?->id)],
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
}
