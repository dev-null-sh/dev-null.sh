<?php

declare(strict_types=1);

return [

    'min_account_age_days' => (int) env('SIGNUP_MIN_ACCOUNT_AGE_DAYS', 7),

    'require_verified_email' => (bool) env('SIGNUP_REQUIRE_VERIFIED_EMAIL', true),

];
