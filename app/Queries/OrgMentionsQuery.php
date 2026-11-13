<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * @phpstan-type OrgMentions array{mentions: Collection<int, Message>, mentions_count: int}
 */
final readonly class OrgMentionsQuery
{
    /**
     * The most mentions handed over, as many as the bell counts before it shows 99+.
     */
    public const int LIMIT = 99;

    /**
     * The org's messages that mention the user and they haven't cleared, latest first with their authors, and how
     * many there are in all.
     *
     * @return OrgMentions
     */
    public function get(User $user, Conversation $org): array
    {
        return [
            'mentions' => $user->mentions()->where('conversation_id', $org->id)->with('user')->orderByDesc('messages.id')->limit(self::LIMIT)->get()->toBase(),
            'mentions_count' => $user->mentions()->where('conversation_id', $org->id)->count(),
        ];
    }
}
