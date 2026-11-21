<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Commit;
use App\Services\Urls;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A commit's text, its body or a poll option, measured with each link counting as at most 23 characters.
 */
final readonly class CommitBodyLength implements ValidationRule
{
    public const int MAX_RAW_LENGTH = 40000;

    public function __construct(private int $max = Commit::MAX_BODY_LENGTH)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        assert(is_string($value));

        $urls = resolve(Urls::class);

        if ($urls->hasOversizedUrl($value)) {
            $fail('Each link must not be greater than '.Urls::MAX_LENGTH.' characters.');

            return;
        }

        if ($urls->characterCount($value) > $this->max) {
            $fail('validation.max.string')->translate(['max' => $this->max]);
        }
    }
}
