<?php

declare(strict_types=1);

use App\Models\Commit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $deleted = DB::table('commits')
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', now()->subSeconds(Commit::RESTORABLE_SECONDS));

        DB::table('polls')->whereIn('commit_id', (clone $deleted)->select('id'))->delete();

        DB::table('link_previews')
            ->whereIn(DB::raw("substring(url from '/commits/([0-9a-f-]{36})')"), (clone $deleted)->selectRaw('id::text'))
            ->delete();

        $deleted->update(['body' => '', 'mentions' => null, 'embedding' => null, 'link_preview_id' => null]);
    }
};
