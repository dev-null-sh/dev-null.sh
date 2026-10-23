<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\CommitVideoStatus;
use App\Models\CommitVideo;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ReadyCommitVideo implements ValidationRule
{
    public function __construct(private User $user)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! CommitVideo::query()->whereKey($value)->where('user_id', $this->user->id)->where('status', CommitVideoStatus::Ready)->whereNull('commit_id')->exists()) {
            $fail('Choose a video that has finished uploading and processing.');
        }
    }
}
