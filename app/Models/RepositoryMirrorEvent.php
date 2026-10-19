<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $id
 * @property-read string $repository_mirror_id
 * @property-read array<string, mixed> $payload
 * @property-read CarbonInterface|null $delivered_at
 */
final class RepositoryMirrorEvent extends Model
{
    use HasUuids;

    public function casts(): array
    {
        return ['payload' => 'encrypted:array', 'delivered_at' => 'immutable_datetime'];
    }
}
