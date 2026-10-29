<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final readonly class UpdateConversationCall
{
    /**
     * Answers the partner's call, which they pick up on their next poll and then forget.
     */
    public function handle(User $callee, User $caller, string $offer, string $answer): void
    {
        Cache::put(CreateConversationCall::key($callee), Crypt::encryptString(json_encode([
            'caller_id' => $caller->id,
            'offer' => $offer,
            'answer' => $answer,
        ], JSON_THROW_ON_ERROR)), CreateConversationCall::SECONDS);
    }
}
