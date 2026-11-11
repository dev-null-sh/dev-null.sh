<?php

declare(strict_types=1);

use App\Actions\CreateUnclaimedProfileTopics;
use App\Enums\ProfileType;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The developers who got a page before their Mentions tab did are linked to the commits that mention them, a
     * hundred at a time, so each reads the commits once.
     */
    public function up(): void
    {
        Profile::query()
            ->where('type', ProfileType::User)
            ->whereDoesntHave('user')
            ->chunkById(100, function (Collection $profiles): void {
                resolve(CreateUnclaimedProfileTopics::class)->handle(...$profiles->all());
            });
    }
};
