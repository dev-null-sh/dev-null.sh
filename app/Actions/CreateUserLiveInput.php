<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\LiveInput;
use App\Models\User;
use App\Services\CloudflareStream;

final readonly class CreateUserLiveInput
{
    public function __construct(private CloudflareStream $stream)
    {
        //
    }

    /**
     * The user's live input, made the first time they ask for it, or again if it was deleted on Cloudflare.
     */
    public function handle(User $user): LiveInput
    {
        $input = $user->live_input_id === null ? null : $this->stream->liveInput($user->live_input_id);

        if ($input instanceof LiveInput) {
            return $input;
        }

        $uid = $this->stream->createLiveInput($user->handle);

        $user->update(['live_input_id' => $uid]);

        $input = $this->stream->liveInput($uid);

        assert($input instanceof LiveInput);

        return $input;
    }
}
