<?php

declare(strict_types=1);

namespace App\Contracts\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface LogQuery
{
    /**
     * @return Builder<Commit>
     */
    public function builder(User $user): Builder;
}
