<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PasskeyFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property-read string $user_id
 * @property-read string $name
 * @property-read string $credential_id
 * @property-read array{public_key: string, algorithm: int, sign_count: int} $credential
 * @property-read CarbonInterface|null $last_used_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Visible([
    'id',
    'name',
    'created_at',
    'last_used_at',
])]
final class Passkey extends Model
{
    /** @use HasFactory<PasskeyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'string',
            'name' => 'string',
            'credential_id' => 'string',
            'credential' => 'array',
            'last_used_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
