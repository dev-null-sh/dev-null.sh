<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class PushEndpoint implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('This browser’s push service is not supported.');

            return;
        }

        $url = parse_url($value);

        if ($url === false || ($url['scheme'] ?? '') !== 'https'
            || (! in_array($url['host'] ?? '', ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'], true)
                && ! str_ends_with($url['host'] ?? '', '.notify.windows.com'))
            || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])
            || (isset($url['port']) && $url['port'] !== 443)
            || ($url['path'] ?? '/') === '/') {
            $fail('This browser’s push service is not supported.');
        }
    }
}
