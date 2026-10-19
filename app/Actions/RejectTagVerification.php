<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tag;
use Illuminate\Validation\ValidationException;

/**
 * Drops a pending verification request; ownership and domain stay.
 */
final readonly class RejectTagVerification
{
    public function handle(Tag $tag): Tag
    {
        if ($tag->verification_requested_at === null) {
            throw ValidationException::withMessages(['tag' => 'This branch is not waiting for verification.']);
        }

        $tag->forceFill(['verification_requested_at' => null])->save();

        return $tag->refresh();
    }
}
