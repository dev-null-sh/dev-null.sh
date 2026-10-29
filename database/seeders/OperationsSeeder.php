<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Dto\ThreadEngagement;
use App\Enums\CommitStatus;
use App\Enums\Feature;
use App\Enums\ModerationReason;
use App\Enums\NotificationType;
use App\Jobs\DetectCommitLanguage;
use App\Jobs\EmbedCommit;
use App\Jobs\ModerateCommit;
use App\Jobs\SyncGitHubSshKeysJob;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\LinkPreview;
use App\Models\User;
use App\Services\Features;
use App\Services\TheAlgorithm;
use Carbon\CarbonImmutable;
use Faker\Provider\Biased;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Two months of growing, weekly-shaped activity and a few things going wrong, for the operations console: signups and
 * the people seen each day, commits, replies, stars and follows, a moderation queue with a commit waiting over six
 * hours, reports, a ban and timeouts, a feature switched off, failed jobs with a spike in the last hours, and a stale
 * queue. Run it after the regular seeder:
 *
 *     php artisan migrate:fresh --seed
 *     php artisan db:seed --class=OperationsSeeder
 */
final class OperationsSeeder extends Seeder
{
    private const int DAYS = 60;

    /**
     * How busy each weekday is, Monday first: weekends are quieter.
     */
    private const array WEEKDAYS = [1 => 1.0, 2 => 1.1, 3 => 1.1, 4 => 1.05, 5 => 0.95, 6 => 0.7, 7 => 0.65];

    private const int CHUNK = 500;

    private CarbonImmutable $now;

    public function __construct(private readonly TheAlgorithm $algorithm, private readonly Features $features)
    {
        //
    }

    public function run(): void
    {
        $this->now = CarbonImmutable::now();

        $users = $this->seedPeople();
        $commits = $this->seedPosts($users);

        DB::statement('update users set last_seen_at = greatest(last_seen_at, posted.at) from (select user_id, max(created_at)::date as at from commits group by user_id) as posted where posted.user_id = users.id');

        $this->seedModeration($users, $commits);
        $this->seedJobs();

        $this->features->update(Feature::Translations, false);
        DB::table('features')->where('name', Feature::Translations->value)->update(['updated_at' => $this->now->subHours(3)]);
    }

    /**
     * Signups grow day by day, the people seen each day follow the people signed up so far, and everyone was last seen
     * some time since they signed up, most of them recently, and no earlier than they last posted.
     *
     * @return list<array{id: string, created_at: CarbonImmutable}>
     */
    private function seedPeople(): array
    {
        $users = [];
        $counts = [];

        foreach (range(0, self::DAYS - 1) as $day) {
            $date = $this->now->startOfDay()->subDays(self::DAYS - 1 - $day);
            $secondsSoFar = $date->isToday() ? max(1, (int) $date->diffInSeconds($this->now)) : 86_399;
            $busy = self::WEEKDAYS[$date->dayOfWeekIso] * $secondsSoFar / 86_399;
            $signups = max(1, (int) round((3 + 0.4 * $day) * $busy * fake()->randomFloat(2, 0.8, 1.2)));

            foreach (range(1, $signups) as $_) {
                $createdAt = $date->addSeconds(fake()->numberBetween(0, $secondsSoFar));
                $users[] = ['id' => Str::uuid7($createdAt)->toString(), 'created_at' => $createdAt];
            }

            $counts[] = [
                'seen_at' => $date->toDateString(),
                'count' => max($signups, (int) round(count($users) * 0.3 * max($busy, 0.25) * fake()->randomFloat(2, 0.9, 1.1))),
            ];
        }

        $rows = array_map(function (array $user): array {
            $seenDaysAgo = (int) min($user['created_at']->diffInDays($this->now->startOfDay()), fake()->biasedNumberBetween(0, 30, Biased::class.'::linearLow'));

            return [
                // Making the row makes the user's profile already, which the row itself doesn't hold...
                ...Arr::except(User::factory()->raw(['onboarded_at' => $user['created_at'], 'commit_rules_accepted_at' => $user['created_at']]), 'profile'),
                'id' => $user['id'],
                'created_at' => $user['created_at'],
                'updated_at' => $user['created_at'],
                'last_seen_at' => $this->now->subDays($seenDaysAgo)->toDateString(),
            ];
        }, $users);

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        DB::table('active_user_counts')->upsert($counts, ['seen_at'], ['count']);

        return $users;
    }

    /**
     * About one commit for every two people seen, most answered by a reply or stars, and newcomers following people.
     *
     * @param  list<array{id: string, created_at: CarbonImmutable}>  $users
     * @return list<array{id: string, user_id: string, created_at: CarbonImmutable}>
     */
    private function seedPosts(array $users): array
    {
        $commits = [];
        $replies = [];
        $stars = [];
        $follows = [];

        foreach (range(0, self::DAYS - 1) as $day) {
            $date = $this->now->startOfDay()->subDays(self::DAYS - 1 - $day);
            $secondsSoFar = $date->isToday() ? max(1, (int) $date->diffInSeconds($this->now)) : 86_399;
            $authors = collect($users)->filter(fn (array $user): bool => $user['created_at']->lessThan($date->addDay()))->values();

            if ($authors->count() < 2) {
                continue;
            }

            $count = (int) round($authors->count() * 0.12 * self::WEEKDAYS[$date->dayOfWeekIso] * $secondsSoFar / 86_399 * fake()->randomFloat(2, 0.8, 1.2));

            foreach (range(1, max(1, $count)) as $_) {
                $createdAt = $date->addSeconds(fake()->numberBetween(0, $secondsSoFar));
                $author = $authors->random();
                $commit = ['id' => Str::uuid7($createdAt)->toString(), 'user_id' => $author['id'], 'created_at' => $createdAt->max($author['created_at'])];
                $commits[] = $commit;

                foreach ($authors->random(fake()->numberBetween(0, 3)) as $stargazer) {
                    $stars[$commit['id'].$stargazer['id']] = null;
                }

                if (fake()->boolean(60)) {
                    $repliedAt = $commit['created_at']->addMinutes(fake()->numberBetween(2, 600))->min($this->now);
                    $replies[] = ['id' => Str::uuid7($repliedAt)->toString(), 'user_id' => $authors->random()['id'], 'parent_id' => $commit['id'], 'created_at' => $repliedAt];
                }
            }

            foreach (array_filter($users, fn (array $user): bool => $user['created_at']->isSameDay($date)) as $newcomer) {
                foreach ($authors->random(min($authors->count(), fake()->numberBetween(1, 6))) as $followed) {
                    if ($followed['id'] !== $newcomer['id']) {
                        $follows[] = ['follower_id' => $newcomer['id'], 'followed_id' => $followed['id'], 'created_at' => $newcomer['created_at']->addMinutes(fake()->numberBetween(1, 120))->min($this->now)];
                    }
                }
            }
        }

        $this->insert('commits', array_map(fn (array $commit): array => $this->post($commit['id'], $commit['user_id'], $commit['created_at'], null), $commits));
        $this->insert('commits', array_map(fn (array $reply): array => $this->post($reply['id'], $reply['user_id'], $reply['created_at'], $reply['parent_id']), $replies));

        $byId = array_column($commits, null, 'id');
        $this->insert('stars', array_map(function (string $key) use ($byId): array {
            $commit = $byId[mb_substr($key, 0, 36)];
            $starredAt = $commit['created_at']->addMinutes(fake()->numberBetween(1, 900))->min($this->now);

            return ['commit_id' => $commit['id'], 'user_id' => mb_substr($key, 36), 'created_at' => $starredAt, 'updated_at' => $starredAt];
        }, array_map(strval(...), array_keys($stars))), ignore: true);

        $this->insert('follows', array_map(fn (array $follow): array => [...$follow, 'updated_at' => $follow['created_at']], $follows), ignore: true);

        return $commits;
    }

    /**
     * @return array<string, mixed>
     */
    private function post(string $id, string $userId, CarbonImmutable $createdAt, ?string $parentId): array
    {
        return [
            'id' => $id,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'root_id' => $parentId,
            'body' => fake()->sentence(fake()->numberBetween(4, 18)),
            'status' => CommitStatus::Approved->value,
            'mentions' => '[]',
            'hot_score' => $this->algorithm->score($createdAt, ThreadEngagement::none()),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * Commits waiting for review, the oldest for over six hours, reports, some dismissed, verdicts, a ban, and timeouts.
     *
     * @param  list<array{id: string, created_at: CarbonImmutable}>  $users
     * @param  list<array{id: string, user_id: string, created_at: CarbonImmutable}>  $commits
     */
    private function seedModeration(array $users, array $commits): void
    {
        $someone = fn (): User => User::query()->whereKey(collect($users)->random()['id'])->firstOrFail();

        foreach ([7 * 60, 125, 40, 12, 2] as $minutesAgo) {
            Commit::factory()->for($someone())->pending()->create(['created_at' => $this->now->subMinutes($minutesAgo)]);
        }

        $recent = collect($commits)->filter(fn (array $commit): bool => $commit['created_at']->greaterThan($this->now->subDays(3)));

        foreach ($recent->random(8)->values() as $index => $commit) {
            $reportedAt = $this->now->subMinutes(fake()->numberBetween(5, 3 * 24 * 60));

            DB::table('commit_reports')->insert([
                'id' => Str::uuid7($reportedAt)->toString(),
                'commit_id' => $commit['id'],
                'reporter_id' => collect($users)->random()['id'],
                'reason' => fake()->randomElement(array_keys(CommitReport::REASONS)),
                'details' => null,
                'dismissed_at' => $index < 3 ? $reportedAt->addMinutes(fake()->numberBetween(5, 120))->min($this->now) : null,
                'created_at' => $reportedAt,
                'updated_at' => $reportedAt,
            ]);
        }

        foreach ($recent->random(6)->values() as $index => $commit) {
            $isRejected = $index < 4;
            $moderatedAt = $commit['created_at']->addMinutes(fake()->numberBetween(1, 30))->min($this->now);

            if ($isRejected) {
                DB::table('commits')->where('id', $commit['id'])->update(['status' => CommitStatus::Rejected->value]);
            }

            DB::table('notifications')->insert([
                'id' => Str::uuid7($moderatedAt)->toString(),
                'recipient_id' => $commit['user_id'],
                'actor_id' => $commit['user_id'],
                'type' => ($isRejected ? NotificationType::Rejected : NotificationType::Approved)->value,
                'commit_id' => $commit['id'],
                'key' => 'moderated:'.$commit['id'],
                'reason' => $isRejected ? fake()->randomElement(['Engagement bait', 'Off-topic self-promotion', null]) : null,
                'created_at' => $moderatedAt,
                'updated_at' => $moderatedAt,
            ]);
        }

        $banned = $someone();
        $banned->forceFill(['banned_at' => $this->now->subDay()])->save();
        DB::table('bans')->insert([
            'id' => Str::uuid7($this->now->subDay())->toString(),
            'github_id' => $banned->github_id,
            'reason' => ModerationReason::Spam->value,
            'created_at' => $this->now->subDay(),
            'updated_at' => $this->now->subDay(),
        ]);

        foreach ([[$someone(), 4, 20], [$someone(), 72, -48]] as [$user, $hoursAgo, $endsInHours]) {
            DB::table('user_timeouts')->insert([
                'id' => Str::uuid7($this->now->subHours($hoursAgo))->toString(),
                'github_id' => $user->github_id,
                'reason' => collect([ModerationReason::EngagementBait, ModerationReason::LowEffort])->random()->value,
                'ends_at' => $this->now->addHours($endsInHours),
                'created_at' => $this->now->subHours($hoursAgo),
                'updated_at' => $this->now->subHours($hoursAgo),
            ]);
        }
    }

    /**
     * Failed jobs over the last week, with real payloads, the link previews failing in a spike over the last hours, and
     * a few jobs still queued, one stuck on the translations queue.
     */
    private function seedJobs(): void
    {
        $preview = LinkPreview::factory()->create();
        LinkPreview::factory()->count(5)->create();
        $commit = Commit::query()->latest()->firstOrFail();
        $user = User::query()->latest()->firstOrFail();

        $failures = [
            [new UnfurlLink($preview), 'default', 'Illuminate\\Http\\Client\\ConnectionException: cURL error 28: Operation timed out after 5001 milliseconds with 0 bytes received for https://example.com/post'],
            [new EmbedCommit($commit), 'default', 'OpenAI\\Exceptions\\RateLimitException: Request rate limit has been exceeded.'],
            [new ModerateCommit($commit), 'default', 'OpenAI\\Exceptions\\TransporterException: cURL error 7: Failed to connect to api.openai.com port 443'],
            [new SyncGitHubSshKeysJob($user), 'default', 'Illuminate\\Http\\Client\\RequestException: HTTP request returned status code 502: Bad Gateway'],
            [new DetectCommitLanguage($commit->id), 'translations', 'Illuminate\\Queue\\TimeoutExceededException: App\\Jobs\\DetectCommitLanguage has timed out.'],
        ];

        foreach ([[8, 0], [5, 1], [4, 2], [3, 3], [2, 4]] as [$count, $failure]) {
            foreach (range(1, $count) as $_) {
                $this->fail(...$failures[$failure], failedAt: $this->now->subMinutes(fake()->numberBetween(4 * 60, 7 * 24 * 60)));
            }
        }

        foreach (range(1, 18) as $index) {
            $this->fail(...$failures[$index % 4 === 0 ? 1 : 0], failedAt: $this->now->subMinutes(fake()->numberBetween(1, 3 * 60)));
        }

        Queue::connection('database')->pushOn('default', new UnfurlLink($preview));
        Queue::connection('database')->pushOn('default', new EmbedCommit($commit));
        $stale = Queue::connection('database')->pushOn('translations', new DetectCommitLanguage($commit->id));
        DB::table('jobs')->where('id', $stale)->update([
            'created_at' => $this->now->subMinutes(14)->getTimestamp(),
            'available_at' => $this->now->subMinutes(14)->getTimestamp(),
        ]);
    }

    /**
     * Queues the job to get the payload Laravel would have stored, then moves it to the failed jobs.
     */
    private function fail(ShouldQueue $job, string $queue, string $exception, CarbonImmutable $failedAt): void
    {
        $id = Queue::connection('database')->pushOn($queue, $job);
        $payload = DB::table('jobs')->where('id', $id)->value('payload');
        assert(is_string($payload));
        DB::table('jobs')->where('id', $id)->delete();

        DB::table('failed_jobs')->insert([
            'uuid' => str($payload)->after('"uuid":"')->before('"')->value(),
            'connection' => 'database',
            'queue' => $queue,
            'payload' => $payload,
            'exception' => $exception."\nStack trace:\n#0 /var/www/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(134): handle()\n#1 {main}",
            'failed_at' => $failedAt,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(string $table, array $rows, bool $ignore = false): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            $ignore ? DB::table($table)->insertOrIgnore($chunk) : DB::table($table)->insert($chunk);
        }
    }
}
