<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\BookmarkCollection;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowBookmarkCollectionRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $collection = $this->route('bookmarkCollection');
        assert($collection instanceof BookmarkCollection);

        return $collection->isOwnedBy($user) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
