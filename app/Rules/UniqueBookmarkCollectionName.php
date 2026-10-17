<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\BookmarkCollection;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A user's collection names are unique ignoring case, so "Read later" and "read later" can't sit side by side.
 */
final readonly class UniqueBookmarkCollectionName implements ValidationRule
{
    public function __construct(private User $user, private ?BookmarkCollection $ignore = null)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $collections = BookmarkCollection::query()
            ->where('user_id', $this->user->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(is_string($value) ? mb_trim($value) : '')]);

        if ($this->ignore instanceof BookmarkCollection) {
            $collections->whereKeyNot($this->ignore->id);
        }

        if ($collections->exists()) {
            $fail(__('You already have a collection with this name.'));
        }
    }
}
