<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/** @property-read array{personalized?: bool}|null $preferences */
#[Fillable(['user_id', 'preferences'])]
#[Hidden(['user_id', 'preferences'])]
#[WithoutIncrementing]
#[WithoutTimestamps]
final class ExplorePreference extends Model
{
    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    public function casts(): array
    {
        return ['preferences' => 'encrypted:array'];
    }
}
