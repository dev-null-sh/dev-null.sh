<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Commit;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Keeps the model available to cursor pagination while serializing only the card's public fields.
 *
 * @implements Arrayable<array-key, mixed>
 */
final class ShownCommitResource extends JsonResource implements Arrayable
{
    public function __construct(Commit $resource, private readonly ?string $joinedGuild = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(?Request $request = null): array
    {
        $commit = $this->resource;
        assert($commit instanceof Commit);
        $shown = ShownStreams::commit($commit);
        assert(is_array($shown));

        return $commit->trashed() ? $shown : [...$shown, 'joined_guild' => $this->joinedGuild];
    }
}
