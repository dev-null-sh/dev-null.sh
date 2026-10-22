<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BroadcastVisibility;
use App\Models\Conversation;

final readonly class UpdateBroadcast
{
    /**
     * The owner names the broadcast and decides whether it is listed and open, or hidden and invite-only. A null
     * leaves each as it is.
     */
    public function handle(Conversation $broadcast, ?string $name, ?BroadcastVisibility $visibility): void
    {
        $broadcast->update([...$name !== null ? ['name' => $name] : [], ...$visibility instanceof BroadcastVisibility ? ['visibility' => $visibility] : []]);
    }
}
