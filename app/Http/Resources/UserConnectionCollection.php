<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of someone's followers or following, each with the profiles their bio and company mention, as their profile
 * links them.
 */
final class UserConnectionCollection extends ResourceCollection
{
    /**
     * @param  CursorPaginator<int, covariant User>  $page
     * @param  array<int|string, array<string, string>>  $bioProfiles
     */
    public function __construct(private readonly CursorPaginator $page, private readonly array $bioProfiles)
    {
        parent::__construct($page);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return array_map(fn (User $user): array => [
            ...UserResource::make($user)->toArray($request),
            'bio_profiles' => (object) $this->bioProfiles[$user->id],
        ], $this->page->items());
    }
}
