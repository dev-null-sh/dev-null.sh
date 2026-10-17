<?php

declare(strict_types=1);

namespace App\Contracts\Queries;

use App\Models\User;
use App\Queries\CommitBuilder;

interface LogQuery
{
    public function builder(?User $user): CommitBuilder;
}
