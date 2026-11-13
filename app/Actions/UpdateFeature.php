<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Feature;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;
use App\Services\Features;

final readonly class UpdateFeature
{
    public function __construct(private Features $features)
    {
        //
    }

    public function handle(Feature $feature, bool $enabled, ?bool $adminOnly = null): void
    {
        $this->features->update($feature, $enabled, $adminOnly);

        if ($feature === Feature::Guilds) {
            $viewers = User::query()->pluck('id');
            event(new UsersUpdated($viewers, UserUpdate::Guilds));
            event(new UsersUpdated($viewers, UserUpdate::Relationships));
        }
    }
}
