<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $headlines = DB::table('headlines')->where('name', 'Laravel')->select('id');

        DB::table('commits')->whereIn('id', DB::table('commit_headline')
            ->whereIn('headline_id', $headlines)->select('commit_id'))
            ->update(['headlines_classified_at' => null]);

        DB::table('headlines')->where('name', 'Laravel')->delete();
    }
};
