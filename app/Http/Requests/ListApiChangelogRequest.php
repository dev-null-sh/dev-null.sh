<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ListApiChangelogRequest extends FormRequest
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
     * The time zone that decides where one day of merges ends: the one asked for, else the signed user's.
     */
    public function timeZone(): string
    {
        $user = $this->user();
        $saved = $user instanceof User ? $user->timezone ?? null : null;

        return $this->string('timezone')->value() ?: $saved ?? config()->string('app.timezone');
    }

    /**
     * Where the reader got to, to list the days merged before it.
     */
    public function before(): ?CarbonImmutable
    {
        return $this->date('before')?->toImmutable();
    }
}
