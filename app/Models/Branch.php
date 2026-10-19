<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BranchRole;
use Carbon\CarbonInterface;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string|null $description
 * @property-read string|null $domain_description
 * @property-read string|null $brand_color
 * @property-read string|null $og_image_url
 * @property-read string|null $domain
 * @property-read string|null $verification_token
 * @property-read CarbonInterface|null $verification_requested_at
 * @property-read CarbonInterface|null $verified_at
 * @property-read string|null $verified_domain
 * @property-read CarbonInterface|null $claim_requested_at
 * @property-read string|null $claim_requested_by
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<int, BranchMember> $members
 * @property-read Collection<int, User> $watchers
 * @property-read User|null $claimRequester
 */
#[Hidden([
    'verification_token',
])]
final class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    use HasUuids;

    public const int MAX_MEMBERS = 15;

    public const int MAX_DESCRIPTION_LENGTH = 280;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'description' => 'string',
            'domain_description' => 'string',
            'brand_color' => 'string',
            'og_image_url' => 'string',
            'domain' => 'string',
            'verification_token' => 'string',
            'verification_requested_at' => 'datetime',
            'verified_at' => 'datetime',
            'verified_domain' => 'string',
            'claim_requested_at' => 'datetime',
            'claim_requested_by' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null && filled($this->verified_domain);
    }

    public function hasPendingVerification(): bool
    {
        return $this->verification_requested_at !== null && ! $this->isVerified();
    }

    public function hasPendingClaim(): bool
    {
        return $this->claim_requested_at !== null && $this->claim_requested_by !== null && ! $this->isOwned();
    }

    public function clearClaimRequest(): void
    {
        $this->forceFill([
            'claim_requested_at' => null,
            'claim_requested_by' => null,
        ])->save();
    }

    public function isOwned(): bool
    {
        return $this->members()->exists();
    }

    public function roleOf(User $user): ?BranchRole
    {
        return $this->members()->where('user_id', $user->id)->first()?->role;
    }

    public function isMaintainedBy(User $user): bool
    {
        return $this->roleOf($user) === BranchRole::Maintainer;
    }

    public function getRouteKeyName(): string
    {
        return 'name';
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->where($field ?? $this->getRouteKeyName(), mb_strtolower((string) $value))->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function claimRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claim_requested_by');
    }

    /**
     * @return HasMany<BranchMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(BranchMember::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_watches')->withTimestamps();
    }

    /**
     * @return HasMany<BranchWatch, $this>
     */
    public function watches(): HasMany
    {
        return $this->hasMany(BranchWatch::class);
    }
}
