<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\BroadcastRole;
use App\Enums\OrgRole;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * The one role column holds org roles in orgs and broadcast roles in broadcasts.
 *
 * @implements CastsAttributes<OrgRole|BroadcastRole|null, OrgRole|BroadcastRole|null>
 */
final class ParticipantRole implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): OrgRole|BroadcastRole|null
    {
        return match (true) {
            $value === null => null,
            $value === OrgRole::Maintainer->value || $value === OrgRole::Contributor->value => OrgRole::from($value),
            is_string($value) => BroadcastRole::from($value),
            default => null,
        };
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof OrgRole || $value instanceof BroadcastRole ? $value->value : $value;
    }
}
