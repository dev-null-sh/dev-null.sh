<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\TagRole;
use App\Models\Notification;
use App\Models\Tag;
use App\Models\TagMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class UpdateTagMemberRole
{
    public function handle(Tag $tag, User $actor, User $member, TagRole $role): void
    {
        DB::transaction(function () use ($tag, $actor, $member, $role): void {
            $tag = Tag::query()->whereKey($tag->id)->lockForUpdate()->firstOrFail();
            $row = $tag->members()->where('user_id', $member->id)->first();

            if (! $row instanceof TagMember) {
                throw ValidationException::withMessages(['member' => 'That person is not on this hashtag.']);
            }

            if ($row->role === $role) {
                return;
            }

            if ($row->role === TagRole::Maintainer && $role === TagRole::Contributor) {
                $maintainerCount = $tag->members()->where('role', TagRole::Maintainer)->count();

                if ($maintainerCount <= 1) {
                    throw ValidationException::withMessages(['role' => 'Promote someone else before demoting the last maintainer.']);
                }
            }

            $row->forceFill(['role' => $role])->save();

            Notification::query()->insertOrIgnore([[
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $member->id,
                'actor_id' => $actor->id,
                'type' => NotificationType::TagRoleChanged->value,
                'tag_id' => $tag->id,
                'key' => 'tag-role-changed:'.$tag->id.':'.$member->id.':'.$role->value.':'.now()->getTimestampMs(),
                'reason' => $role->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]]);
        });
    }
}
