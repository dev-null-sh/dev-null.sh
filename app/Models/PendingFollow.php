<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PendingFollowFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A user who follows, on GitHub, an account that isn't on /dev/null yet. They follow it here once it signs in.
 *
 * @property-read int $id
 * @property-read string $user_id
 * @property-read string $github_id
 */
#[WithoutTimestamps]
final class PendingFollow extends Model
{
    /** @use HasFactory<PendingFollowFactory> */
    use HasFactory;
}
