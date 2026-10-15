<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Embeds;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ValidEmbed implements ValidationRule
{
    public function __construct(private Embeds $embeds)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $embed = is_string($value) ? $this->embeds->extract($value) : null;

        if ($embed === null) {
            return;
        }

        if ($embed['url'] === null) {
            $fail(__('Only https:// links to other sites can be embedded.'));
        } elseif ($embed['ratio'] === null) {
            $fail(__('Embed ratios look like 16:9, from 1:4 to 4:1.'));
        }
    }
}
