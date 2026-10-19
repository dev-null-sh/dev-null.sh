<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TagRole;
use App\Models\Conversation;
use App\Models\Tag;
use App\Models\User;
use App\Services\TagDnsVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ClaimTag
{
    public function __construct(
        private UpsertTags $upsertTags,
        private TagDnsVerification $verification,
    ) {
        //
    }

    public function handle(User $user, string $name, string $domain): Tag
    {
        $domain = $this->verification->normalizeDomain($domain);

        if (! $this->verification->isValidDomain($domain)) {
            throw ValidationException::withMessages(['domain' => 'Enter a valid domain, like laravel.com.']);
        }

        return DB::transaction(function () use ($user, $name, $domain): Tag {
            $this->upsertTags->handle([$name]);

            $tag = Tag::query()->where('name', $name)->lockForUpdate()->firstOrFail();

            if ($tag->isOwned()) {
                throw ValidationException::withMessages(['tag' => 'This hashtag already has maintainers.']);
            }

            if (! Conversation::userMayVerifyBranch($user, $name)) {
                throw ValidationException::withMessages([
                    'tag' => 'This branch matches a Remotes org; only that org\'s maintainers can claim it.',
                ]);
            }

            $tag->forceFill([
                'domain' => $domain,
                'verification_token' => $this->verification->newToken(),
                'verification_requested_at' => null,
                'verified_at' => null,
                'verified_domain' => null,
                'domain_description' => null,
                'og_image_url' => null,
                'brand_color' => null,
            ])->save();

            $tag->members()->create([
                'user_id' => $user->id,
                'role' => TagRole::Maintainer,
            ]);

            return $tag->refresh();
        });
    }
}
