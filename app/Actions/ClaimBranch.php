<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BranchRole;
use App\Models\Conversation;
use App\Models\Branch;
use App\Models\User;
use App\Services\BranchDnsVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ClaimBranch
{
    public function __construct(
        private UpsertBranches $upsertBranches,
        private BranchDnsVerification $verification,
    ) {
        //
    }

    public function handle(User $user, string $name, string $domain): Branch
    {
        $domain = $this->verification->normalizeDomain($domain);

        if (! $this->verification->isValidDomain($domain)) {
            throw ValidationException::withMessages(['domain' => 'Enter a valid domain, like laravel.com.']);
        }

        return DB::transaction(function () use ($user, $name, $domain): Branch {
            $this->upsertBranches->handle([$name]);

            $branch = Branch::query()->where('name', $name)->lockForUpdate()->firstOrFail();

            if ($branch->isOwned()) {
                throw ValidationException::withMessages(['branch' => 'This branch already has maintainers.']);
            }

            if (! Conversation::userMayVerifyBranch($user, $name)) {
                throw ValidationException::withMessages([
                    'branch' => 'This branch matches a Remotes org; only that org\'s maintainers can claim it.',
                ]);
            }

            $branch->forceFill([
                'domain' => $domain,
                'verification_token' => $this->verification->newToken(),
                'verification_requested_at' => null,
                'verified_at' => null,
                'verified_domain' => null,
                'domain_description' => null,
                'og_image_url' => null,
                'brand_color' => null,
            ])->save();

            $branch->members()->create([
                'user_id' => $user->id,
                'role' => BranchRole::Maintainer,
            ]);

            return $branch->refresh();
        });
    }
}
