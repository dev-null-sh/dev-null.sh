<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Conversation;
use App\Models\User;
use App\Queries\OrgCallQuery;
use Illuminate\Support\Facades\Cache;

final readonly class DeleteOrgCall
{
    public function __construct(private OrgCallQuery $calls)
    {
        //
    }

    /**
     * Takes the user out of the org's call, with every signal they swapped in it. Without their check-ins, the call
     * no longer counts them, and goes once nobody is left.
     */
    public function handle(User $user, Conversation $org): void
    {
        Cache::lock(CreateOrgCall::key($org).':lock', 5)->block(5, function () use ($user, $org): void {
            if (Cache::get(CreateOrgCall::memberKey($user->id)) === $org->id) {
                Cache::forget(CreateOrgCall::memberKey($user->id));
            }

            $call = $this->calls->get($org);

            if ($call === null) {
                Cache::forget(CreateOrgCall::key($org));

                return;
            }

            CreateOrgCall::forgetSignals($org, $user, array_keys($call['members']));
        });
    }
}
