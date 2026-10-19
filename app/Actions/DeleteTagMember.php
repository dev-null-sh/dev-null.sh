<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TagRole;
use App\Models\Tag;
use App\Models\TagMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteTagMember
{
    public function handle(Tag $tag, User $actor, User $member): void
    {
        DB::transaction(function () use ($tag, $actor, $member): void {
            $tag = Tag::query()->whereKey($tag->id)->lockForUpdate()->firstOrFail();
            $row = $tag->members()->where('user_id', $member->id)->first();

            if (! $row instanceof TagMember) {
                return;
            }

            $leavingSelf = $actor->id === $member->id;

            if (! $leavingSelf && ! $tag->isMaintainedBy($actor) && ! $actor->isAdmin()) {
                throw ValidationException::withMessages(['member' => 'Only maintainers can remove other people.']);
            }

            if ($row->role === TagRole::Maintainer) {
                $maintainerCount = $tag->members()->where('role', TagRole::Maintainer)->count();

                if ($maintainerCount <= 1) {
                    $heir = $tag->members()
                        ->where('role', TagRole::Contributor)
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->first();

                    if ($heir instanceof TagMember) {
                        $heir->forceFill(['role' => TagRole::Maintainer])->save();
                    }
                }
            }

            $row->delete();

            if (! $tag->members()->exists()) {
                $tag->forceFill([
                    'domain' => null,
                    'verification_token' => null,
                    'verified_at' => null,
                    'verified_domain' => null,
                    'description' => null,
                ])->save();
            }
        });
    }
}
