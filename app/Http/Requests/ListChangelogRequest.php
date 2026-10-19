<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ListChangelogRequest extends FormRequest
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
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'before' => ['nullable', 'date'],
        ];
    }

    /**
     * The reader's time zone, which decides where one day of merges ends and the next begins.
     */
    public function timeZone(): string
    {
        return $this->string('timezone')->value() ?: config()->string('app.timezone');
    }

    /**
     * Where the reader got to, to list the days merged before it.
     */
    public function before(): ?CarbonImmutable
    {
        return $this->date('before')?->toImmutable();
    }
}
