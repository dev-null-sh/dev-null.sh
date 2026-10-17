<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\BookmarkCollection;
use App\Models\User;
use App\Rules\UniqueBookmarkCollectionName;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBookmarkCollectionRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->collection()->isOwnedBy($user) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'name' => [...CreateBookmarkCollectionRequest::NAME_RULES, new UniqueBookmarkCollectionName($user, $this->collection())],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->trim()->value();
    }

    private function collection(): BookmarkCollection
    {
        $collection = $this->route('bookmarkCollection');
        assert($collection instanceof BookmarkCollection);

        return $collection;
    }
}
