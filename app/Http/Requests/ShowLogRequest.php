<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use Illuminate\Foundation\Http\FormRequest;
use Inertia\Inertia;

final class ShowLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'since' => ['nullable', 'uuid'],
        ];
    }

    /**
     * The newest commit the user has already seen, even if it was deleted since.
     */
    public function since(): ?Commit
    {
        return $this->filled('since')
            ? Commit::query()->withTrashed()->find($this->string('since')->value())
            : null;
    }

    /**
     * The commit the user has just committed from the log, if they have.
     */
    public function committed(): ?Commit
    {
        $id = data_get(Inertia::getFlashed($this), 'committed.id');

        return is_string($id) ? Commit::query()->find($id) : null;
    }
}
