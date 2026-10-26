<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PollDuration;
use App\Models\Commit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fills a database with a year of activity at production scale, shaped like a real network rather than spread evenly:
 * a few developers draw most of the follows, stars, and replies, threads run several replies deep, and the local
 * developer that local auto-login signs in as follows thousands, is followed by tens of thousands, and has muted and
 * blocked people, so every log, thread, and inbox has real work to do.
 *
 * Everything is generated in Postgres, in set-based statements, so a million commits take minutes rather than hours.
 * Ids are time-ordered UUIDs built from each row's own created_at, like the ones the app creates, so the cursors
 * that page through logs walk the same index order they do in production.
 *
 * Run it into a database of its own, as it adds to whatever is there:
 *
 *     createdb devnull_stress
 *     DB_DATABASE=devnull_stress php artisan migrate:fresh --seeder=StressSeeder --seed
 *
 * STRESS_SCALE multiplies every count (default 1, about 50k developers and a million commits, a few GB on disk), and
 * STRESS_EMBEDDINGS gives that many recent commits a random embedding for the semantic search (default 0).
 */
final class StressSeeder extends Seeder
{
    private const int DEVELOPERS = 50_000;

    private const int COMMITS = 600_000;

    private const int REPLIES = 400_000;

    private const int QUOTES = 30_000;

    private const int STARS = 6_000_000;

    private const int SAVED_STARS = 300_000;

    private const int POLLS = 8_000;

    private const int POLL_VOTES = 300_000;

    private const int LINK_PREVIEWS = 15_000;

    private const int MUTES = 15_000;

    private const int BLOCKS = 5_000;

    private const int REPORTS = 3_000;

    private const int NOTIFICATIONS = 1_500_000;

    /**
     * The most follows any one developer has, on a log scale, so most follow a few dozen and some follow hundreds.
     */
    private const int MAX_FOLLOWING = 400;

    /**
     * Distinct commit bodies to draw from: enough variety in length and shape, few enough to build in PHP.
     */
    private const int BODIES = 3_000;

    /**
     * Rows per statement for the largest tables, so progress shows and no single statement holds too much.
     */
    private const int BATCH = 500_000;

    private float $scale = 1.0;

    public function run(): void
    {
        throw_unless(app()->isLocal(), RuntimeException::class, 'The stress seeder only runs in the local environment.');

        $this->scale = max(0.001, $this->setting('STRESS_SCALE', 1));

        DB::statement('select setseed(0.42)');

        $this->step('functions and lookup tables', $this->prepare(...));
        $this->step('developers', $this->seedDevelopers(...));
        $this->step('follows', $this->seedFollows(...));
        $this->step('commits', $this->seedCommits(...));
        $this->step('replies', $this->seedReplies(...));
        $this->step('quotes', $this->seedQuotes(...));
        $this->step('stars', $this->seedStars(...));
        $this->step('link previews and images', $this->seedAttachments(...));
        $this->step('polls', $this->seedPolls(...));
        $this->step('saved stars', $this->seedSavedStars(...));
        $this->step('mutes, blocks, and reports', $this->seedSafety(...));
        $this->step('notifications', $this->seedNotifications(...));
        $this->step('hot scores', $this->seedHotScores(...));
        $this->step('embeddings', $this->seedEmbeddings(...));
        $this->step('statistics', fn () => DB::statement('analyze'));

        $this->dropLookupTables();
    }

    /**
     * Read from the environment rather than config, as these only matter for the one command they are passed to.
     */
    private function setting(string $name, float $default): float
    {
        $value = Env::get($name);

        return is_numeric($value) ? (float) $value : $default;
    }

    private function count(int $base): int
    {
        return max(1, (int) round($base * $this->scale));
    }

    private function step(string $label, callable $callback): void
    {
        $started = microtime(true);
        $callback();

        $this->command?->info(sprintf('  %-28s %6.1fs', $label, microtime(true) - $started));
    }

    /**
     * Runs an insert of the given number of rows in batches, passing each batch's size to the statement.
     *
     * @param  callable(int): string  $statement
     */
    private function inBatches(int $rows, callable $statement): void
    {
        for ($done = 0; $done < $rows; $done += self::BATCH) {
            DB::statement($statement(min(self::BATCH, $rows - $done)));
        }
    }

    /**
     * Freshly filled tables have no statistics, and without them Postgres plans the next statement as if they were
     * empty: one lookup table took seconds instead of milliseconds before this.
     */
    private function analyze(string ...$tables): void
    {
        DB::statement('analyze '.implode(', ', $tables));
    }

    private function prepare(): void
    {
        // A version 7 UUID whose leading 48 bits are the given time in milliseconds...
        DB::statement(<<<'SQL'
            create function pg_temp.uuid7(at timestamp) returns uuid language sql volatile as $$
                select encode(set_bit(set_bit(overlay(uuid_send(gen_random_uuid())
                    placing substring(int8send(floor(extract(epoch from at) * 1000)::bigint) from 3) from 1 for 6),
                    52, 1), 53, 1), 'hex')::uuid
            $$
            SQL);

        DB::statement('create unlogged table stress_bodies (n int primary key, body text not null)');

        foreach (array_chunk($this->bodies(), 500) as $chunk) {
            DB::table('stress_bodies')->insert($chunk);
        }
    }

    /**
     * Commit bodies in the shapes people post: short status lines, longer thoughts, long-form posts that collapse to a
     * preview, mentions, links, and code blocks.
     *
     * @return list<array{n: int, body: string}>
     */
    private function bodies(): array
    {
        $languages = ['php', 'js', 'ts', 'sql', 'bash', 'sema', 'json', 'yaml'];
        $snippets = [
            '$commit = Commit::query()->latest()->first();',
            'const res = await fetch(`/commits/${id}`);',
            'select count(*) from commits where parent_id is null;',
            'git rebase -i HEAD~3 && git push --force-with-lease',
            '(defagent guide {:tools [get-weather] :max-turns 3})',
            '{"stars": 42, "replies": 7}',
        ];

        $bodies = [];

        for ($n = 1; $n <= self::BODIES; $n++) {
            $body = match (true) {
                $n % 10 === 0 => "Shipped it:\n```".$languages[$n % count($languages)]."\n".$snippets[$n % count($snippets)]."\n```",
                $n % 7 === 0 => fake()->sentence().' @dev_'.fake()->numberBetween(1, 500),
                $n % 11 === 0 => fake()->sentence().' https://example.com/'.fake()->slug(3),
                $n % 13 === 0 => fake()->realTextBetween(Commit::PREVIEW_LENGTH + 100, Commit::MAX_BODY_LENGTH - 100),
                $n % 3 === 0 => fake()->realTextBetween(120, 270),
                default => fake()->realTextBetween(20, 110),
            };

            $bodies[] = ['n' => $n, 'body' => Str::limit($body, Commit::MAX_BODY_LENGTH - 3, '...')];
        }

        return $bodies;
    }

    /**
     * Developers joined over two years, numbered so that popularity can be drawn by number: the lower the number,
     * the more follows, stars, and replies a developer draws. Number 0 is the local developer.
     */
    private function seedDevelopers(): void
    {
        $developers = $this->count(self::DEVELOPERS);

        DB::statement(<<<SQL
            insert into users (id, github_id, username, name, bio, created_at, updated_at, preferred_lane, onboarded_at, log)
            select pg_temp.uuid7(joined), 'stress-' || n, 'dev_' || n,
                (array['Ada','Linus','Grace','Ken','Barbara','Dennis','Margaret','Guido','Rasmus','Taylor','Nuno','Brent'])[1 + floor(random() * 12)::int]
                    || ' ' || (array['Lovelace','Torvalds','Hopper','Thompson','Liskov','Ritchie','Hamilton','Rossum','Lerdorf','Otwell'])[1 + floor(random() * 10)::int],
                case when random() < 0.6 then 'Building things, mostly in production.' end,
                joined, joined, n % 6, joined, 'following'
            from (select n, localtimestamp - random() * interval '730 days' as joined from generate_series(1, {$developers}) as n) as developers
            SQL);

        DB::statement(<<<'SQL'
            insert into users (id, github_id, username, name, created_at, updated_at, onboarded_at, log)
            values (pg_temp.uuid7(localtimestamp - interval '730 days'), 'local', 'local-dev', 'Local Developer',
                localtimestamp - interval '730 days', localtimestamp, localtimestamp - interval '730 days', 'following')
            on conflict (github_id) do nothing
            SQL);

        DB::statement(<<<'SQL'
            create unlogged table stress_users as
            select case when github_id = 'local' then 0 else substring(github_id from 8)::int end as n, id
            from users where github_id = 'local' or github_id like 'stress-%'
            SQL);
        DB::statement('alter table stress_users add primary key (n)');
        $this->analyze('users', 'stress_users');
    }

    /**
     * How many each developer follows is spread on a log scale, and whom is drawn toward the popular few.
     */
    private function seedFollows(): void
    {
        $developers = $this->count(self::DEVELOPERS);
        $maxFollowing = self::MAX_FOLLOWING;

        DB::statement(<<<SQL
            insert into follows (follower_id, followed_id, created_at, updated_at)
            select follower.id, followed.id, followed_at, followed_at
            from (
                select u.n as follower_n, 1 + floor({$developers} * power(random(), 3))::int as followed_n,
                    localtimestamp - random() * interval '365 days' as followed_at
                from stress_users as u
                cross join lateral generate_series(1, floor(exp(random() * ln({$maxFollowing})))::int + u.n * 0)
                where u.n > 0
            ) as picks
            join stress_users as follower on follower.n = picks.follower_n
            join stress_users as followed on followed.n = picks.followed_n
            where follower.id <> followed.id
            on conflict do nothing
            SQL);

        // The local developer follows two thousand, and twenty thousand follow them back... Spread over the year like every
        // other follow, as follows dated today would all leave unread notifications from a single day.
        $following = $this->count(2_000);
        $followers = $this->count(20_000);

        DB::statement(<<<SQL
            insert into follows (follower_id, followed_id, created_at, updated_at)
            select (select id from stress_users where n = 0), id, followed_at, followed_at
            from (
                select id, localtimestamp - random() * interval '365 days' as followed_at
                from stress_users where n between 1 and {$following}
            ) as followed
            on conflict do nothing
            SQL);

        DB::statement(<<<SQL
            insert into follows (follower_id, followed_id, created_at, updated_at)
            select id, (select id from stress_users where n = 0), followed_at, followed_at
            from (
                select id, localtimestamp - random() * interval '365 days' as followed_at
                from stress_users where n > 0 order by random() limit {$followers}
            ) as followers
            on conflict do nothing
            SQL);
    }

    /**
     * Top-level commits over the last year, more of them recent, written mostly by the most active developers. A few
     * are still pending or were rejected, and one in a hundred has been deleted.
     */
    private function seedCommits(): void
    {
        $developers = $this->count(self::DEVELOPERS);
        $bodies = self::BODIES;

        $this->inBatches($this->count(self::COMMITS), fn (int $rows): string => <<<SQL
            insert into commits (id, user_id, body, status, created_at, updated_at, deleted_at)
            select pg_temp.uuid7(committed_at), author.id, bodies.body,
                case when roll < 0.95 then 'approved' when roll < 0.98 then 'pending' else 'rejected' end,
                committed_at, committed_at,
                case when random() < 0.01 then least(localtimestamp, committed_at + interval '1 hour') end
            from (
                select 1 + floor({$developers} * power(random(), 2))::int as author_n,
                    1 + floor(random() * {$bodies})::int as body_n,
                    localtimestamp - power(random(), 1.5) * interval '365 days' as committed_at,
                    random() as roll
                from generate_series(1, {$rows})
            ) as picks
            join stress_users as author on author.n = picks.author_n
            join stress_bodies as bodies on bodies.n = picks.body_n
            SQL);

        $this->seedLocalCommits();
        $this->analyze('commits');

        // Numbered in random order, so a commit's number says how much attention it draws, not when it was written...
        DB::statement(<<<'SQL'
            create unlogged table stress_threads as
            select row_number() over (order by random())::int as n, id, user_id, created_at
            from commits where parent_id is null and deleted_at is null and status = 'approved'
            SQL);
        DB::statement('alter table stress_threads add primary key (n)');
        $this->analyze('stress_threads');
    }

    private function seedLocalCommits(): void
    {
        $commits = $this->count(2_000);
        $bodies = self::BODIES;

        DB::statement(<<<SQL
            insert into commits (id, user_id, body, status, created_at, updated_at)
            select pg_temp.uuid7(committed_at), (select id from stress_users where n = 0), bodies.body, 'approved', committed_at, committed_at
            from (
                select 1 + floor(random() * {$bodies})::int as body_n, localtimestamp - random() * interval '365 days' as committed_at
                from generate_series(1, {$commits})
            ) as picks
            join stress_bodies as bodies on bodies.n = picks.body_n
            SQL);
    }

    /**
     * Replies in three rounds, each answering the round before, so threads run three deep and most replies land in
     * the few threads that take off.
     */
    private function seedReplies(): void
    {
        $replies = $this->count(self::REPLIES);
        $rounds = [
            ['parents' => 'stress_threads', 'rows' => (int) round($replies * 0.6), 'next' => 'stress_replies_1'],
            ['parents' => 'stress_replies_1', 'rows' => (int) round($replies * 0.3), 'next' => 'stress_replies_2'],
            ['parents' => 'stress_replies_2', 'rows' => (int) round($replies * 0.1), 'next' => null],
        ];

        foreach ($rounds as $round) {
            $this->seedReplyRound($round['parents'], $round['rows']);

            if ($round['next'] !== null) {
                $this->analyze('commits');

                DB::statement(<<<SQL
                    create unlogged table {$round['next']} as
                    select row_number() over (order by random())::int as n, id, user_id, created_at, root_id
                    from commits where parent_id in (select id from {$round['parents']}) and deleted_at is null and status = 'approved'
                    SQL);
                DB::statement("alter table {$round['next']} add primary key (n)");
                $this->analyze($round['next']);
            }
        }
    }

    private function seedReplyRound(string $parents, int $rows): void
    {
        $developers = $this->count(self::DEVELOPERS);
        $bodies = self::BODIES;
        $parentCount = DB::table($parents)->count();
        $rootColumn = $parents === 'stress_threads' ? 'parent.id' : 'parent.root_id';

        if ($parentCount === 0) {
            return;
        }

        $this->inBatches($rows, fn (int $batch): string => <<<SQL
            insert into commits (id, user_id, body, status, parent_id, root_id, created_at, updated_at)
            select pg_temp.uuid7(replied_at), author.id, bodies.body,
                case when roll < 0.97 then 'approved' else 'pending' end,
                parent.id, {$rootColumn}, replied_at, replied_at
            from (
                select 1 + floor({$parentCount} * power(random(), 3))::int as parent_n,
                    1 + floor({$developers} * power(random(), 1.5))::int as author_n,
                    1 + floor(random() * {$bodies})::int as body_n,
                    power(random(), 3) as delay, random() as roll
                from generate_series(1, {$batch})
            ) as picks
            join {$parents} as parent on parent.n = picks.parent_n
            join stress_users as author on author.n = picks.author_n
            join stress_bodies as bodies on bodies.n = picks.body_n
            cross join lateral (select least(localtimestamp, parent.created_at + picks.delay * interval '3 days') as replied_at) as timing
            SQL);
    }

    /**
     * Quotes are top-level commits pointing at another, drawn toward the popular threads like replies are.
     */
    private function seedQuotes(): void
    {
        $quotes = $this->count(self::QUOTES);
        $developers = $this->count(self::DEVELOPERS);
        $threads = DB::table('stress_threads')->count();
        $bodies = self::BODIES;

        DB::statement(<<<SQL
            insert into commits (id, user_id, body, status, quoted_commit_id, created_at, updated_at)
            select pg_temp.uuid7(quoted_at), author.id, bodies.body, 'approved', quoted.id, quoted_at, quoted_at
            from (
                select 1 + floor({$threads} * power(random(), 3))::int as quoted_n,
                    1 + floor({$developers} * power(random(), 1.5))::int as author_n,
                    1 + floor(random() * {$bodies})::int as body_n, power(random(), 2) as delay
                from generate_series(1, {$quotes})
            ) as picks
            join stress_threads as quoted on quoted.n = picks.quoted_n
            join stress_users as author on author.n = picks.author_n
            join stress_bodies as bodies on bodies.n = picks.body_n
            cross join lateral (select least(localtimestamp, quoted.created_at + picks.delay * interval '2 days') as quoted_at) as timing
            SQL);
    }

    /**
     * Stars pile onto the commits that take off, and the most active developers give most of them. One in ten
     * lands on a reply instead.
     */
    private function seedStars(): void
    {
        $developers = $this->count(self::DEVELOPERS);
        $threads = DB::table('stress_threads')->count();
        $stars = $this->count(self::STARS);

        $this->inBatches((int) round($stars * 0.9), fn (int $rows): string => <<<SQL
            insert into stars (user_id, commit_id, created_at, updated_at)
            select stargazer.id, starred.id, starred_at, starred_at
            from (
                select 1 + floor({$threads} * power(random(), 4))::int as commit_n,
                    floor({$developers} * power(random(), 1.5))::int as stargazer_n, power(random(), 3) as delay
                from generate_series(1, {$rows})
            ) as picks
            join stress_threads as starred on starred.n = picks.commit_n
            join stress_users as stargazer on stargazer.n = picks.stargazer_n
            cross join lateral (select least(localtimestamp, starred.created_at + picks.delay * interval '7 days') as starred_at) as timing
            on conflict do nothing
            SQL);

        $replies = DB::table('stress_replies_1')->count();

        $this->inBatches((int) round($stars * 0.1), fn (int $rows): string => <<<SQL
            insert into stars (user_id, commit_id, created_at, updated_at)
            select stargazer.id, starred.id, starred.created_at, starred.created_at
            from (
                select 1 + floor({$replies} * power(random(), 3))::int as commit_n,
                    1 + floor({$developers} * power(random(), 1.5))::int as stargazer_n
                from generate_series(1, {$rows})
            ) as picks
            join stress_replies_1 as starred on starred.n = picks.commit_n
            join stress_users as stargazer on stargazer.n = picks.stargazer_n
            on conflict do nothing
            SQL);
    }

    /**
     * One in twenty commits carries a link card, and one in thirty has between one and four images.
     */
    private function seedAttachments(): void
    {
        $previews = $this->count(self::LINK_PREVIEWS);

        DB::statement(<<<SQL
            insert into link_previews (id, url, url_hash, title, description, site_name, fetched_at, created_at, updated_at)
            select gen_random_uuid(), 'https://example.com/posts/' || n, encode(sha256(('https://example.com/posts/' || n)::bytea), 'hex'),
                'How we scaled to ' || n || ' requests a second', 'A write-up of what broke and what we changed.', 'example.com',
                localtimestamp, localtimestamp, localtimestamp
            from generate_series(1, {$previews}) as n
            SQL);

        DB::statement(<<<'SQL'
            create temporary table stress_previews as
            select row_number() over (order by random())::int as n, id from link_previews
            SQL);

        DB::statement(<<<SQL
            update commits set link_preview_id = previews.id
            from (
                select commits.id as commit_id, 1 + floor(random() * {$previews})::int as preview_n
                from commits where parent_id is null and random() < 0.05
            ) as picks
            join stress_previews as previews on previews.n = picks.preview_n
            where commits.id = picks.commit_id
            SQL);

        DB::statement(<<<'SQL'
            insert into commit_images (id, commit_id, path, width, height, position, created_at, updated_at)
            select gen_random_uuid(), commits.id, 'commit-images/stress-' || position || '.jpg', 1200, 800, position, commits.created_at, commits.created_at
            from commits
            cross join lateral generate_series(1, 1 + floor(random() * 4)::int + (commits.created_at is null)::int) as position
            where commits.parent_id is null and random() < 0.033
            SQL);
    }

    /**
     * Polls on popular commits, so their votes come from many developers, with two to four options each.
     */
    private function seedPolls(): void
    {
        $polls = $this->count(self::POLLS);
        $votes = $this->count(self::POLL_VOTES);
        $developers = $this->count(self::DEVELOPERS);
        $durations = implode(',', array_map(fn (PollDuration $duration): int => $duration->value, PollDuration::cases()));

        DB::statement(<<<SQL
            create temporary table stress_polls as
            select row_number() over ()::int as n, gen_random_uuid() as id, threads.id as commit_id, threads.created_at,
                2 + floor(random() * 3)::int as options
            from stress_threads as threads where threads.n <= {$polls}
            SQL);

        DB::statement(<<<SQL
            insert into polls (id, commit_id, duration, opened_at, created_at, updated_at)
            select id, commit_id, (array[{$durations}])[1 + floor(random() * 4)::int], created_at, created_at, created_at
            from stress_polls
            SQL);

        DB::statement(<<<'SQL'
            insert into poll_options (id, poll_id, label, position, created_at, updated_at)
            select gen_random_uuid(), polls.id, (array['Tabs','Spaces','Vim','Emacs'])[position], position, polls.created_at, polls.created_at
            from stress_polls as polls
            cross join lateral generate_series(1, polls.options) as position
            SQL);

        DB::statement(<<<SQL
            insert into poll_votes (poll_id, poll_option_id, user_id, created_at, updated_at)
            select polls.id, options.id, voter.id, polls.created_at, polls.created_at
            from (
                select 1 + floor({$polls} * power(random(), 2))::int as poll_n,
                    1 + floor({$developers} * random())::int as voter_n, random() as choice
                from generate_series(1, {$votes})
            ) as picks
            join stress_polls as polls on polls.n = picks.poll_n
            join stress_users as voter on voter.n = picks.voter_n
            join poll_options as options on options.poll_id = polls.id and options.position = 1 + floor(picks.choice * polls.options)::int
            on conflict do nothing
            SQL);
    }

    private function seedSavedStars(): void
    {
        $savedStars = $this->count(self::SAVED_STARS);
        $developers = $this->count(self::DEVELOPERS);
        $threads = DB::table('stress_threads')->count();
        $local = $this->count(3_000);

        DB::statement(<<<SQL
            insert into stars (user_id, commit_id, created_at, updated_at)
            select saver.id, saved.id, saved.created_at, saved.created_at
            from (
                select 1 + floor({$threads} * power(random(), 3))::int as commit_n, floor({$developers} * random())::int as saver_n
                from generate_series(1, {$savedStars})
                union all
                select 1 + floor({$threads} * random())::int, 0 from generate_series(1, {$local})
            ) as picks
            join stress_threads as saved on saved.n = picks.commit_n
            join stress_users as saver on saver.n = picks.saver_n
            on conflict do nothing
            SQL);
    }

    /**
     * Mutes and blocks between developers, the local developer's included, so every log filters them out, and a
     * moderation queue of open reports.
     */
    private function seedSafety(): void
    {
        $developers = $this->count(self::DEVELOPERS);
        $threads = DB::table('stress_threads')->count();

        foreach (['mutes' => ['muter_id', 'muted_id', self::MUTES, 150], 'blocks' => ['blocker_id', 'blocked_id', self::BLOCKS, 30]] as $table => [$by, $of, $rows, $local]) {
            $rows = $this->count($rows);
            $local = $this->count($local);

            DB::statement(<<<SQL
                insert into {$table} ({$by}, {$of}, created_at, updated_at)
                select actor.id, target.id, localtimestamp, localtimestamp
                from (
                    select 1 + floor({$developers} * random())::int as actor_n, 1 + floor({$developers} * power(random(), 2))::int as target_n
                    from generate_series(1, {$rows})
                    union all
                    select 0, 1 + floor({$developers} * random())::int from generate_series(1, {$local})
                ) as picks
                join stress_users as actor on actor.n = picks.actor_n
                join stress_users as target on target.n = picks.target_n
                where actor.id <> target.id
                on conflict do nothing
                SQL);
        }

        $reports = $this->count(self::REPORTS);

        DB::statement(<<<SQL
            insert into commit_reports (id, commit_id, reporter_id, reason, created_at, updated_at)
            select gen_random_uuid(), reported.id, reporter.id, (array['spam','harassment','other'])[1 + floor(random() * 3)::int], localtimestamp, localtimestamp
            from (
                select 1 + floor({$threads} * random())::int as commit_n, 1 + floor({$developers} * random())::int as reporter_n
                from generate_series(1, {$reports})
            ) as picks
            join stress_threads as reported on reported.n = picks.commit_n
            join stress_users as reporter on reporter.n = picks.reporter_n
            on conflict do nothing
            SQL);
    }

    /**
     * Notifications for what actually happened: every star, reply, and follow the local developer received, and a
     * sample of the stars and replies everyone else did, keyed the way the app keys them.
     */
    private function seedNotifications(): void
    {
        $sample = $this->count(self::NOTIFICATIONS);

        $notifications = [
            'starred' => <<<'SQL'
                select starred.user_id as recipient_id, stars.user_id as actor_id, starred.id as commit_id, starred.id as subject_id,
                    'starred:' || starred.id || ':' || stars.user_id as key, stars.created_at
                from stars join commits as starred on starred.id = stars.commit_id
                where starred.user_id <> stars.user_id
                SQL,
            'replied' => <<<'SQL'
                select parent.user_id, reply.user_id, reply.id, parent.id, 'replied:' || reply.id, reply.created_at
                from commits as reply join commits as parent on parent.id = reply.parent_id
                where reply.status = 'approved' and parent.user_id <> reply.user_id
                SQL,
            'followed' => <<<'SQL'
                select followed_id, follower_id, null::uuid, null::uuid, 'followed:' || follower_id, created_at from follows
                SQL,
        ];

        foreach ($notifications as $type => $events) {
            foreach (['= (select id from stress_users where n = 0)' => null, '<> (select id from stress_users where n = 0)' => $sample / 3] as $recipient => $limit) {
                $limitSql = $limit === null ? '' : 'order by random() limit '.(int) $limit;

                DB::statement(<<<SQL
                    insert into notifications (id, recipient_id, actor_id, type, commit_id, subject_id, key, read_at, created_at, updated_at)
                    select pg_temp.uuid7(created_at), recipient_id, actor_id, '{$type}', commit_id, subject_id, key,
                        case when created_at < localtimestamp - interval '2 days' then created_at + interval '1 hour' end,
                        created_at, created_at
                    from ({$events}) as events (recipient_id, actor_id, commit_id, subject_id, key, created_at)
                    where recipient_id {$recipient}
                    {$limitSql}
                    on conflict do nothing
                    SQL);
            }
        }
    }

    /**
     * The same score UpdateCommitHotScores gives, for every top-level commit at once: each other developer counts once
     * for each thing they did in the thread, with the weights of the hot score.
     */
    private function seedHotScores(): void
    {
        DB::statement(<<<'SQL'
            with stargazers as (
                select commit_id as thread_id, user_id from stars
                union
                select starred.root_id, stars.user_id from stars
                join commits as starred on starred.id = stars.commit_id
                where starred.root_id is not null and starred.status = 'approved' and starred.deleted_at is null
            ),
            quoters as (
                select quoted_commit_id as thread_id, user_id from commits
                where quoted_commit_id is not null and status = 'approved' and deleted_at is null
                union
                select quoted.root_id, quotes.user_id from commits as quotes
                join commits as quoted on quoted.id = quotes.quoted_commit_id
                where quoted.root_id is not null and quotes.status = 'approved' and quotes.deleted_at is null and quoted.status = 'approved'
            ),
            repliers as (
                select distinct root_id as thread_id, user_id from commits
                where root_id is not null and status = 'approved' and deleted_at is null
            ),
            repliers_answered_by_author as (
                select distinct replies.root_id as thread_id, replies.user_id from commits as replies
                join commits as roots on roots.id = replies.root_id
                join commits as answers on answers.parent_id = replies.id and answers.user_id = roots.user_id
                where replies.status = 'approved' and replies.deleted_at is null and answers.status = 'approved' and answers.deleted_at is null
            ),
            engagements as (
                select thread_id, user_id, 0.5 as weight from stargazers
                union all
                select thread_id, user_id, 1.0 from quoters
                union all
                select thread_id, user_id, 13.5 from repliers
                union all
                select thread_id, user_id, 75.0 from repliers_answered_by_author
            ),
            weighted as (
                select roots.id, sum(engagements.weight) as engagement
                from commits as roots join engagements on engagements.thread_id = roots.id
                where roots.parent_id is null and engagements.user_id is distinct from roots.user_id
                group by roots.id
            )
            update commits set hot_score = round((
                log(2, 1 + coalesce(weighted.engagement, 0))
                + extract(epoch from commits.created_at)::numeric / 21600
            ), 6)
            from commits as roots
            left join weighted on weighted.id = roots.id
            where commits.id = roots.id and roots.parent_id is null and roots.deleted_at is null
            SQL);
    }

    /**
     * Random unit vectors are enough to exercise the vector index, if not to rank anything meaningfully.
     */
    private function seedEmbeddings(): void
    {
        $embeddings = (int) $this->setting('STRESS_EMBEDDINGS', 0);

        if ($embeddings <= 0) {
            return;
        }

        // Writing each vector through the HNSW index took over twenty minutes for 100k commits. Building the index once
        // they are all written takes under a minute...
        Schema::table('commits', fn (Blueprint $table) => $table->dropVectorIndex('commits_embedding_index'));

        DB::statement(<<<SQL
            update commits set embedding = (
                select array_agg(random() - 0.5)::vector from generate_series(1, 1536) where commits.id is not null
            )
            where id in (select id from commits where parent_id is null order by created_at desc limit {$embeddings})
            SQL);

        DB::statement("set maintenance_work_mem = '1GB'");
        Schema::table('commits', fn (Blueprint $table) => $table->vectorIndex('embedding', 'commits_embedding_index'));
        DB::statement('reset maintenance_work_mem');
    }

    private function dropLookupTables(): void
    {
        foreach (['stress_bodies', 'stress_users', 'stress_threads', 'stress_replies_1', 'stress_replies_2'] as $table) {
            DB::statement("drop table if exists {$table}");
        }
    }
}
