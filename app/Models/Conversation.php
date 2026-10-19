<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationKind;
use App\Enums\OrgRole;
use App\Enums\RemoteStatus;
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

    public function isOrg(): bool
    {
        return $this->kind === ConversationKind::Org;
    }

    /**
     * The user's role in the org, or null when they aren't in it.
     */
    public function roleOf(User $user): ?OrgRole
    {
        return $this->isOrg() ? $this->participants()->where('user_id', $user->id)->first()?->role : null;
    }

    public function isMaintainedBy(User $user): bool
    {
        return $this->roleOf($user) === OrgRole::Maintainer;
    }

    /**
     * Orgs whose name matches a branch (trim + case-insensitive).
     *
     * @return Builder<self>
     */
    public static function matchingBranch(string $branch): Builder
    {
        return self::query()
            ->where('kind', ConversationKind::Org)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(mb_trim($branch))]);
    }

    /**
     * When a Remotes org shares a branch's name, only that org's maintainers (and site admins) may claim or verify it.
     * If several orgs share the name, the oldest one is the sole authority so a newly created org cannot squat.
     */
    public static function userMayVerifyBranch(User $user, string $branch): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $authoritative = self::matchingBranch($branch)->oldest('created_at')->oldest('id')->first();

        if (! $authoritative instanceof self) {
            return true;
        }

        return $authoritative->participants()
            ->where('user_id', $user->id)
            ->where('role', OrgRole::Maintainer)
            ->exists();
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
