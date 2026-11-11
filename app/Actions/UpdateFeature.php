<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Feature;
use App\Services\Features;

final readonly class UpdateFeature
{
    public function __construct(private Features $features)
    {
        //
    }

    public function handle(Feature $feature, bool $enabled): void
    {
        $this->features->update($feature, $enabled);
    }
}
