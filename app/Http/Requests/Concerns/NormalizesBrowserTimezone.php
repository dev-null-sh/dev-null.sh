<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use DateTimeZone;
use IntlTimeZone;
use IntlException;

trait NormalizesBrowserTimezone
{
    protected function prepareForValidation(): void
    {
        $timeZone = $this->input('timezone');

        if (! is_string($timeZone)) {
            return;
        }

        // These older browser spellings can survive canonicalization on older ICU versions.
        $iana = match ($timeZone) {
            'Asia/Calcutta' => 'Asia/Kolkata',
            'Asia/Katmandu' => 'Asia/Kathmandu',
            'Europe/Kiev' => 'Europe/Kyiv',
            'US/Eastern' => 'America/New_York',
            default => null,
        };

        if ($iana === null) {
            try {
                $iana = method_exists(IntlTimeZone::class, 'getIanaID')
                    ? IntlTimeZone::getIanaID($timeZone)
                    : IntlTimeZone::getCanonicalID($timeZone);
            } catch (IntlException) {
                return;
            }
        }

        if (! is_string($iana) || ! in_array($iana, DateTimeZone::listIdentifiers(), true)) {
            return;
        }

        if ($this->query->has('timezone') && ! $this->request->has('timezone') && ! $this->json()->has('timezone')) {
            $this->query->set('timezone', $iana);

            return;
        }

        $this->merge(['timezone' => $iana]);
    }
}
