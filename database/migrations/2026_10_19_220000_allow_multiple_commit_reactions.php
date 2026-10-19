<?php

declare(strict_types=1);

use App\Services\ReactionEmoji;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commit_reactions', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('commit_id')->constrained()->cascadeOnDelete();
            $table->string('flavor');
            $table->timestamps();
            $table->primary(['user_id', 'commit_id', 'flavor']);
            $table->index(['commit_id', 'flavor']);
        });
        Schema::table('repository_reactions', function (Blueprint $table): void {
            $table->string('emoji_key', 64)->nullable();
        });
        foreach (DB::table('repository_reactions')->orderBy('commit_id')->cursor() as $reaction) {
            if (! is_string($reaction->flavor)) {
                throw new UnexpectedValueException('Invalid encrypted reaction.');
            }
            DB::table('repository_reactions')->where('repository_mirror_id', $reaction->repository_mirror_id)->where('commit_id', $reaction->commit_id)->update([
                'emoji_key' => ReactionEmoji::key(Crypt::decryptString($reaction->flavor)),
            ]);
        }
        Schema::table('repository_reactions', function (Blueprint $table): void {
            $table->dropPrimary();
            $table->string('emoji_key', 64)->nullable(false)->change();
            $table->primary(['repository_mirror_id', 'commit_id', 'emoji_key']);
        });
    }
};
