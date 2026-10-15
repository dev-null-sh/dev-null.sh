<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitSeries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\File;

final class CreateCommitSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Images go with the first part of the series.
     *
     * @return array<string, array<int, string|File|Dimensions>>
     */
    public function rules(): array
    {
        return [
            'bodies' => ['required', 'list', 'min:2', 'max:'.CreateCommitSeries::MAX_PARTS],
            'bodies.*' => ['required', 'string', 'min:3', 'max:280'],
            ...CreateCommitRequest::imageRules(),
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

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        $images = $this->file('images');

        return is_array($images) ? array_values($images) : [];
    }
}
