<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
final class NotificationFactory extends Factory
{
    /**
     * Defaults to a follow that still holds.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient_id' => User::factory(),
            'actor_id' => User::factory(),
            'type' => NotificationType::Followed,
            'commit_id' => null,
        ];
    }

    public function configure(): self
    {
        return $this
            ->afterMaking(fn (Notification $notification): Notification => $notification->forceFill([
                'key' => $notification->type->value.':'.($notification->commit_id ?? $notification->actor_id),
            ]))
            ->afterCreating(function (Notification $notification): void {
                if ($notification->type === NotificationType::Followed) {
                    $notification->actor()->firstOrFail()->following()->syncWithoutDetaching([$notification->recipient_id]);
                }
            });
    }

    /**
     * An approved reply from the actor to one of the recipient's commits.
     */
    public function replied(): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => NotificationType::Replied,
            'commit_id' => fn (array $attributes): string => Commit::factory()
                ->for(User::query()->whereKey($attributes['actor_id'])->sole())
                ->for(Commit::factory()->for(User::query()->whereKey($attributes['recipient_id'])->sole()), 'parent')
                ->create()
                ->id,
        ]);
    }

    public function read(): self
    {
        return $this->state(fn (array $attributes): array => [
            'read_at' => now(),
        ]);
    }
}
