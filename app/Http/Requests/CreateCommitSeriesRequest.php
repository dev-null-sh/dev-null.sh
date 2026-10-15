<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitSeries;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCommitSeriesRequest extends FormRequest
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
            'bodies' => ['required', 'list', 'min:2', 'max:'.CreateCommitSeries::MAX_PARTS],
            'bodies.*' => ['required', 'string', 'min:3', 'max:280'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'bodies.*' => 'commit',
        ];
    }

    /**
     * @return list<string>
     */
    public function bodies(): array
    {
        /** @var list<string> $bodies */
        $bodies = $this->validated('bodies');

        return $bodies;
    }
}
