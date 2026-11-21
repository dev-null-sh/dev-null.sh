<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ListReportedCommitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->isAdmin();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'since' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * When the queue last read what it shows, in milliseconds, never later than now.
     */
    public function since(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampMs(min($this->integer('since'), now()->getTimestampMs()));
    }
}
