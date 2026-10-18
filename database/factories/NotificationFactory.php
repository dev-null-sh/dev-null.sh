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
                'key' => match ($notification->type) {
                    NotificationType::Starred => 'starred:'.$notification->commit_id.':'.$notification->actor_id,
                    NotificationType::LeveledUp => 'leveled_up:'.$notification->level,
                    default => $notification->type->value.':'.($notification->commit_id ?? $notification->actor_id),
                },
            ]))
            ->afterCreating(function (Notification $notification): void {
                if ($notification->type === NotificationType::Followed) {
                    $notification->actor()->firstOrFail()->following()->syncWithoutDetaching([$notification->recipient_id]);
                }

                if ($notification->type === NotificationType::Starred) {
                    $notification->actor()->firstOrFail()->starredCommits()->syncWithoutDetaching([$notification->commit_id]);
                }
            });
    }

    /**
     * The recipient reaching the given level, as their own actor.
     */
    public function leveledUp(int $level): self
    {
        return $this->state(fn (array $attributes): array => [
            'actor_id' => $attributes['recipient_id'],
            'type' => NotificationType::LeveledUp,
            'level' => $level,
        ]);
    }

    /**
     * An approved reply from the actor to one of the recipient's commits, or to the given one.
     */
    public function replied(?Commit $parent = null): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => NotificationType::Replied,
            'commit_id' => fn (array $attributes): string => Commit::factory()
                ->for(User::query()->whereKey($attributes['actor_id'])->sole())
                ->for($parent ?? Commit::factory()->for(User::query()->whereKey($attributes['recipient_id'])->sole()), 'parent')
                ->create()
                ->id,
            'subject_id' => fn (array $attributes): ?string => Commit::query()->whereKey($attributes['commit_id'])->sole()->parent_id,
        ]);
    }

    /**
     * A star from the actor on one of the recipient's approved commits, or on the given one.
     */
    public function starred(?Commit $commit = null): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => NotificationType::Starred,
            'commit_id' => fn (array $attributes): string => $commit->id ?? Commit::factory()
                ->for(User::query()->whereKey($attributes['recipient_id'])->sole())
                ->create()
                ->id,
            'subject_id' => fn (array $attributes): string => Commit::query()->whereKey($attributes['commit_id'])->sole()->id,
        ]);
    }

    /**
     * An approved quote from the actor of one of the recipient's commits.
     */
    public function quoted(): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => NotificationType::Quoted,
            'commit_id' => fn (array $attributes): string => Commit::factory()
                ->for(User::query()->whereKey($attributes['actor_id'])->sole())
                ->for(Commit::factory()->for(User::query()->whereKey($attributes['recipient_id'])->sole()), 'quotedCommit')
                ->create()
                ->id,
        ]);
    }

    /**
     * An approved commit of the actor that mentions the recipient.
     */
    public function mentioned(): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => NotificationType::Mentioned,
            'commit_id' => fn (array $attributes): string => Commit::factory()
                ->for(User::query()->whereKey($attributes['actor_id'])->sole())
                ->create(['body' => 'Thanks @'.User::query()->whereKey($attributes['recipient_id'])->sole()->username])
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
