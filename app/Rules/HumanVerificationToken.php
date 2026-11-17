<?php

declare(strict_types=1);

namespace App\Rules;

use App\Contracts\Services\HumanVerificationProvider;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class HumanVerificationToken implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! resolve(HumanVerificationProvider::class)->isHuman($value)) {
            $fail('Cloudflare could not confirm that you are human. Try again.');
        }
    }
}
