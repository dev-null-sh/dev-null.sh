<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\VoiceNotes;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final readonly class VoiceNote implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || resolve(VoiceNotes::class)->rebuild((string) $value->get()) === null) {
            $fail('Voice notes must be Opus WebM or 16 kHz mono WAV recordings between half a second and a minute long.');
        }
    }
}
