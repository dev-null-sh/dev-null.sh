<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final readonly class CreateConversationCall
{
    /**
     * How long a call rings. The offer holds the caller's IP addresses, so it is encrypted and gone soon after.
     */
    public const int SECONDS = 45;

    public static function key(User $callee): string
    {
        return "call:{$callee->id}";
    }

    public function handle(User $caller, User $callee, string $offer): void
    {
        Cache::put(self::key($callee), Crypt::encryptString(json_encode([
            'caller_id' => $caller->id,
            'offer' => $offer,
            'answer' => null,
        ], JSON_THROW_ON_ERROR)), self::SECONDS);
    }
}
