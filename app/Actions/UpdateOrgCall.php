<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Conversation;
use App\Models\User;
use App\Queries\OrgCallQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * @phpstan-import-type Signal from OrgCallQuery
 */
final readonly class UpdateOrgCall
{
    /**
     * Keeps the user in the org's call, and leaves each signal for the member it is for. A signal holds the
     * sender's relay addresses, so it is encrypted and gone soon after.
     *
     * @param  array<string, Signal>  $signals
     */
    public function handle(User $user, Conversation $org, array $signals): void
    {
        // Under the call's lock, so a check-in that crossed paths with leaving doesn't put the user back in.
        Cache::lock(CreateOrgCall::key($org).':lock', 5)->block(5, function () use ($user, $org, $signals): void {
            if (Cache::get(CreateOrgCall::memberKey($user->id)) !== $org->id) {
                return;
            }

            Cache::put(CreateOrgCall::memberKey($user->id), $org->id, CreateOrgCall::SECONDS);
            Cache::touch(CreateOrgCall::key($org), CreateOrgCall::SECONDS);

            foreach ($signals as $toId => $signal) {
                Cache::put(CreateOrgCall::signalKey($org, $user->id, $toId), Crypt::encryptString(json_encode($signal, JSON_THROW_ON_ERROR)), CreateConversationCall::SECONDS);
            }
        });
    }
}
