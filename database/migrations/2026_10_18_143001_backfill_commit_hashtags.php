<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('commits')->whereNull('hashtags')->select(['id', 'body'])
            ->chunkById(200, function (Collection $commits): void {
                /** @var Collection<int, object{id: string, body: string}> $commits */
                foreach ($commits as $commit) {
                    DB::table('commits')->where('id', $commit->id)->whereNull('hashtags')->update([
                        'hashtags' => json_encode($this->names($commit->body), JSON_THROW_ON_ERROR),
                    ]);
                }
            });
    }

    /**
     * Freeze the parser here so future application changes cannot change this backfill.
     *
     * @return list<string>
     */
    private function names(string $body): array
    {
        $prose = (string) preg_replace('/((?<![^\n])`{3,}[^`\r\n]*\r?\n(?:[\s\S]*?\r?\n)?`{3,}[ \t]*(?![^\r\n])|`[^`\r\n]+`)/', "\n", $body);

        preg_match_all('/(?<![\p{L}\p{N}_#\/])#([A-Za-z0-9][A-Za-z0-9_]{0,49})(?![A-Za-z0-9_])/u', $prose, $matches);

        $names = [];

        foreach ($matches[1] as $tag) {
            if (! preg_match('/[A-Za-z]/', $tag)) {
                continue;
            }

            $names[mb_strtolower($tag)] ??= mb_strtolower($tag);
        }

        return array_values($names);
    }
};
