<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MergeConflictSide;
use App\Models\Commit;
use App\Models\MergeConflict;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class CreateMergeConflictVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mergeConflict = $this->commit()->mergeConflict;

        return $this->commit()->isVisibleTo($this->voter())
            && $mergeConflict instanceof MergeConflict
            && $mergeConflict->canBeVotedOnBy($this->voter());
    }

    /**
     * @return array<string, array<int, string|ValidationRule|Enum>>
     */
    public function rules(): array
    {
        return [
            'side' => ['required', Rule::enum(MergeConflictSide::class)],
        ];
    }

    public function side(): MergeConflictSide
    {
        return $this->enum('side', MergeConflictSide::class) ?? MergeConflictSide::Ours;
    }

    public function mergeConflict(): MergeConflict
    {
        $mergeConflict = $this->commit()->mergeConflict;
        assert($mergeConflict instanceof MergeConflict);

        return $mergeConflict;
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
