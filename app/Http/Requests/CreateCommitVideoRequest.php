<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CommitVideo;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCommitVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['size' => ['required', 'integer', 'min:1', 'max:'.CommitVideo::MAX_BYTES]];
    }
}
