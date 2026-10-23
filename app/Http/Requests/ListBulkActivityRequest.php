<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ActivityScale;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListBulkActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->isAdmin();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scale' => ['nullable', Rule::enum(ActivityScale::class)->only([ActivityScale::Day, ActivityScale::Week, ActivityScale::Month])],
        ];
    }

    /**
     * The window counted, the last seven days by default: long enough to catch an account working through every
     * signup, short enough to show who is doing it now.
     */
    public function scale(): ActivityScale
    {
        return $this->enum('scale', ActivityScale::class) ?? ActivityScale::Week;
    }

    /**
     * The days the window spans, which the thresholds scale with.
     */
    public function days(): int
    {
        return match ($this->scale()) {
            ActivityScale::Day => 1,
            ActivityScale::Week => 7,
            default => 30,
        };
    }
}
