<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PastelVariant;
use Illuminate\Support\Lottery;

final readonly class PastelSeason
{
    public function variant(): PastelVariant
    {
        return today()->format('m-d') === config()->string('pasteis.frosted_day')
            && Lottery::odds(config()->integer('pasteis.frosted_chance'), 100)->choose()
                ? PastelVariant::Frosted
                : PastelVariant::Original;
    }
}
