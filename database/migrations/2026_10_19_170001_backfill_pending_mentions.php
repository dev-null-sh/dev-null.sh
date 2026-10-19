<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every commit that mentions someone who isn't on /dev/null yet gives them a profile to claim, so the mentions made
     * before profiles could be claimed count too. Someone the commit already mentions by their account doesn't count,
     * nor does a username someone has now.
     */
    public function up(): void
    {
        DB::table('commits')->select(['id', 'body', 'mentions'])
            ->chunkById(200, function (Collection $commits): void {
                /** @var Collection<int, object{id: string, body: string, mentions: string|null}> $commits */
                $usernamesByCommit = $commits->mapWithKeys(fn (object $commit): array => [
                    $commit->id => array_values(array_diff($this->usernames($commit->body), $this->captured($commit->mentions))),
                ]);

                /** @var Collection<int, object{username: string}> $users */
                $users = DB::table('users')
                    ->whereIn(DB::raw('LOWER(username)'), $usernamesByCommit->flatten()->unique()->all())
                    ->get(['username']);

                $registered = $users->map(fn (object $user): string => mb_strtolower($user->username))->all();

                DB::table('pending_mentions')->insertOrIgnore($usernamesByCommit
                    ->flatMap(fn (array $usernames, string $id): array => array_map(
                        fn (string $username): array => ['commit_id' => $id, 'username' => $username],
                        array_values(array_diff($usernames, $registered)),
                    ))
                    ->all());
            });
    }

    /**
     * The usernames the commit already mentions someone by, even someone who has since deleted their account, who
     * shouldn't get a profile to claim back.
     *
     * @return list<string>
     */
    private function captured(?string $mentions): array
    {
        $identities = $mentions === null ? [] : json_decode($mentions, true, flags: JSON_THROW_ON_ERROR);

        return is_array($identities) ? array_map(strval(...), array_keys($identities)) : [];
    }

    /**
     * Freeze the parser here so future application changes cannot change this backfill.
     *
     * @return list<string>
     */
    private function usernames(string $body): array
    {
        $prose = (string) preg_replace('/((?<![^\n])`{3,}[^`\r\n]*\r?\n(?:[\s\S]*?\r?\n)?`{3,}[ \t]*(?![^\r\n])|`[^`\r\n]+`)/', "\n", $body);

        preg_match_all('/(?<![\p{L}\p{N}_.\/@-])@([A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38})(?![A-Za-z0-9_@\/-]|\.[A-Za-z0-9])/u', $prose, $matches);

        return array_values(array_unique(array_map(strtolower(...), $matches[1])));
    }
};
