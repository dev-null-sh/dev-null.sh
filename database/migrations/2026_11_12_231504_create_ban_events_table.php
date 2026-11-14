<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ban_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('github_id');
            $table->string('reason');
            $table->timestamps();

            $table->index(['github_id', 'created_at']);
        });

        DB::table('bans')->orderBy('id')->get(['github_id', 'reason', 'created_at', 'updated_at'])->each(
            fn (object $ban): bool => (bool) DB::table('ban_events')->insert([
                'id' => Str::uuid()->toString(),
                'github_id' => $ban->github_id,
                'reason' => $ban->reason,
                'created_at' => $ban->created_at,
                'updated_at' => $ban->updated_at,
            ]),
        );
    }
};
