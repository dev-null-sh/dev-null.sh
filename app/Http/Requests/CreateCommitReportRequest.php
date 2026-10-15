<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\CommitReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class CreateCommitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return Gate::authorize('create', [CommitReport::class, $commit])->allowed();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(array_keys(CommitReport::REASONS))],
            'details' => ['nullable', 'string', 'max:500'],
        ];
    }
}
