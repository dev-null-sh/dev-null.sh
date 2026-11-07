<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListUserMutualsRequest extends FormRequest
{
    /**
     * A block hides each one's connections from the other, like the rest of their profile.
     */
    public function authorize(): Response
    {
        $viewer = $this->user();
        assert($viewer instanceof User);

        $user = $this->route('user');
        assert($user instanceof User);

        return $viewer->hasBlockWith($user) ? Response::denyAsNotFound() : Response::allow();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'loaded' => ['sometimes', 'array:users,organizations'],
            'loaded.*' => ['integer', 'between:0,1000'],
        ];
    }

    /**
     * How many of a list the page loaded, to tell whether those are still the same.
     */
    public function loaded(string $list): int
    {
        return $this->integer("loaded.{$list}");
    }
}
