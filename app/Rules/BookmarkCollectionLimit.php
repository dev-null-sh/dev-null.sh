<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\BookmarkCollection;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class BookmarkCollectionLimit implements ValidationRule
{
    public function __construct(private User $user)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (BookmarkCollection::query()->where('user_id', $this->user->id)->count() >= BookmarkCollection::MAX_PER_USER) {
            $fail(__('You can have up to :max collections. Delete one before creating another.', ['max' => BookmarkCollection::MAX_PER_USER]));
        }
    }
}
