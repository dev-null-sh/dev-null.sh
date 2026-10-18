<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Conversations, orgs included, are identified by time-ordered UUIDs like every other public record. Existing
 * conversations keep their history: each gets a UUID from its creation time, and its participants and messages
 * are pointed at it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable();
        });

        DB::table('conversations')->select(['id', 'created_at'])->orderBy('id')->each(fn (object $conversation): int => DB::table('conversations')
            ->where('id', $conversation->id)
            ->update(['uuid' => (string) Str::uuid7(is_string($conversation->created_at) ? Date::parse($conversation->created_at) : null)]));

        foreach (['conversation_participants', 'messages'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->uuid('conversation_uuid')->nullable();
            });

            DB::table($name)->update([
                'conversation_uuid' => DB::raw("(SELECT uuid FROM conversations WHERE conversations.id = {$name}.conversation_id)"),
            ]);

            Schema::table($name, function (Blueprint $table): void {
                $table->dropForeign(['conversation_id']);
                $table->dropColumn('conversation_id');
            });

            Schema::table($name, function (Blueprint $table): void {
                $table->renameColumn('conversation_uuid', 'conversation_id');
            });
        }

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('id');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->renameColumn('uuid', 'id');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->uuid('id')->nullable(false)->change();
            $table->primary('id');
        });

        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->uuid('conversation_id')->nullable(false)->change();
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->uuid('conversation_id')->nullable(false)->change();
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->index(['conversation_id', 'id']);
        });
    }
};
