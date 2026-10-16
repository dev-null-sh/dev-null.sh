<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\BookmarkCollectionLimit;
use App\Rules\UniqueBookmarkCollectionName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class CreateBookmarkCollectionRequest extends FormRequest
{
    public const array NAME_RULES = ['required', 'string', 'max:50'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'name' => [...self::NAME_RULES, new UniqueBookmarkCollectionName($user), new BookmarkCollectionLimit($user)],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->trim()->value();
    }
}
