<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\TagRole;
use App\Models\Notification;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CreateTagMembers
{
    /**
     * @param  list<User>  $users
     */
    public function handle(Tag $tag, User $actor, array $users): void
    {
        DB::transaction(function () use ($tag, $actor, $users): void {
            $tag = Tag::query()->whereKey($tag->id)->lockForUpdate()->firstOrFail();
            $existing = $tag->members()->pluck('user_id')->all();
            $remaining = Tag::MAX_MEMBERS - count($existing);

            if ($remaining <= 0) {
                throw ValidationException::withMessages(['members' => 'This hashtag already has '.Tag::MAX_MEMBERS.' people.']);
            }

            $toAdd = collect($users)
                ->unique(fn (User $user): string => $user->id)
                ->reject(fn (User $user): bool => in_array($user->id, $existing, true))
                ->take($remaining)
                ->values();

            if ($toAdd->isEmpty()) {
                return;
            }

            foreach ($toAdd as $user) {
                $tag->members()->create([
                    'user_id' => $user->id,
                    'role' => TagRole::Contributor,
                ]);
            }

            $now = now();

            Notification::query()->insertOrIgnore($toAdd->map(fn (User $user): array => [
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $user->id,
                'actor_id' => $actor->id,
                'type' => NotificationType::TagMemberAdded->value,
                'tag_id' => $tag->id,
                'key' => 'tag-member-added:'.$tag->id.':'.$user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }
}
