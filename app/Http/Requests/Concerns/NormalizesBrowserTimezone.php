<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use DateTimeZone;
use IntlException;
use IntlTimeZone;

trait NormalizesBrowserTimezone
{
    protected function prepareForValidation(): void
    {
        $timeZone = $this->input('timezone');

        if (! is_string($timeZone)) {
            return;
        }

        try {
            // getIanaID is available only when PHP is built with ICU 74 or newer.
            $iana = method_exists(IntlTimeZone::class, 'getIanaID')
                ? IntlTimeZone::getIanaID($timeZone)
                : ([
                    'Asia/Calcutta' => 'Asia/Kolkata',
                    'Asia/Katmandu' => 'Asia/Kathmandu',
                    'US/Eastern' => 'America/New_York',
                    'Europe/Kiev' => 'Europe/Kyiv',
                ][$timeZone] ?? IntlTimeZone::getCanonicalID($timeZone));
        } catch (IntlException) {
            return;
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
