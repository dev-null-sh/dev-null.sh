<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $host = Str::of(config()->string('app.url'))->after('://')->before('/')->value();

        DB::table('link_previews')
            ->whereLike('url', "%{$host}/@%/commits/%")
            ->whereNotExists(fn (Builder $query): Builder => $query->from('commits')
                ->whereNotNull('user_id')
                ->whereRaw("commits.id::text = substring(lower(link_previews.url) from '/commits/([0-9a-f-]{36})')"))
            ->delete();
    }
};
