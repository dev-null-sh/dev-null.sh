<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationKind;
use App\Enums\OrgRole;
use App\Enums\RemoteStatus;
use App\Enums\SponsorsFrom;
use Carbon\CarbonInterface;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read ConversationKind $kind
 * @property-read string|null $pair
 * @property-read string|null $name
 * @property-read SponsorsFrom|null $sponsors_from
 * @property-read int|null $last_message_id
 * @property-read int $reactions_version
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<int, ConversationParticipant> $participants
 * @property-read Collection<int, Message> $messages
 * @property-read Message|null $lastMessage
 * @property-read User|null $partner
 * @property-read string $partner_id
 * @property-read int $ahead
 * @property-read CarbonInterface|null $last_message_at
 * @property-read RemoteStatus $partner_status
 * @property-read int $partner_read_through_id
 * @property-read CarbonInterface|null $partner_read_at
 * @property-read CarbonInterface|null $archived_at
 */
final class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * An org holds the maintainer and up to fourteen others.
     */
    public const int MAX_MEMBERS = 15;

    /**
     * Two people share exactly one conversation, whichever of them started it.
     */
    public static function pairOf(User $user, User $other): string
    {
        $ids = [$user->id, $other->id];
        sort($ids);

        return implode(':', $ids);
    }

    public static function between(User $user, User $other): ?self
    {
        return self::query()->where('pair', self::pairOf($user, $other))->first();
    }

    public static function betweenOrFail(User $user, User $other): self
    {
        return self::query()->where('pair', self::pairOf($user, $other))->firstOrFail();
    }

    /**
     * The org a maintainer keeps for their GitHub sponsors, if they turned it on.
     */
    public static function sponsorsOrgOf(User $maintainer): ?self
    {
        return self::query()
            ->whereNotNull('sponsors_from')
            ->whereHas('participants', fn (Builder $query): Builder => $query->where('user_id', $maintainer->id)->where('role', OrgRole::Maintainer))
            ->first();
    }

    public function isOrg(): bool
    {
        return $this->kind === ConversationKind::Org;
    }

    /**
     * A sponsors org fills itself from the maintainer's GitHub sponsors, who stay hidden from each other until they
     * show their membership.
     */
    public function isSponsors(): bool
    {
        return $this->sponsors_from !== null;
    }

    /**
     * Whether the viewer sees the members who keep their membership hidden: only the maintainer of a sponsors org does.
     */
    public function showsHiddenMembersTo(User $viewer): bool
    {
        return $this->isSponsors() && $this->isMaintainedBy($viewer);
    }

    /**
     * The user's role in the org, or null when they aren't in it.
     */
    public function roleOf(User $user): ?OrgRole
    {
        return $this->membershipOf($user)?->role;
    }

    /**
     * The user's place in the org, or null when they aren't in it.
     */
    public function membershipOf(User $user): ?ConversationParticipant
    {
        return $this->isOrg() ? $this->participants()->where('user_id', $user->id)->first() : null;
    }

    public function isMaintainedBy(User $user): bool
    {
        return $this->roleOf($user) === OrgRole::Maintainer;
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'kind' => ConversationKind::class,
            'pair' => 'string',
            'name' => 'string',
            'sponsors_from' => SponsorsFrom::class,
            'last_message_id' => 'integer',
            'reactions_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'partner_id' => 'string',
            'ahead' => 'integer',
            'last_message_at' => 'datetime',
            'partner_status' => RemoteStatus::class,
            'partner_read_through_id' => 'integer',
            'partner_read_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ConversationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The other person, on conversations loaded for one of the two with their partner_id selected.
     *
     * @return BelongsTo<User, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }
}
