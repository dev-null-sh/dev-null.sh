<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\CreateConversation;
use App\Actions\CreateMessage;
use App\Actions\CreateOrg;
use App\Actions\UpdateCommitHotScores;
use App\Actions\UpdateConversationReadThrough;
use App\Enums\RemotesFrom;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

final class DatabaseSeeder extends Seeder
{
    public function __construct(
        private readonly UpdateCommitHotScores $updateCommitHotScores,
        private readonly CreateConversation $createConversation,
        private readonly CreateMessage $createMessage,
        private readonly CreateOrg $createOrg,
        private readonly UpdateConversationReadThrough $updateConversationReadThrough,
    ) {
        //
    }

    public function run(): void
    {
        $users = User::factory()->count(10)->create();

        $users->each(function (User $user) use ($users): void {
            Commit::factory()->for($user)->count(fake()->numberBetween(1, 10))->sequence(
                fn (): array => ['created_at' => fake()->dateTimeBetween('-2 weeks')],
            )->create();

            $user->following()->attach(
                $users->except([$user->id])->random(fake()->numberBetween(1, 5))->modelKeys(),
            );
        });

        $localDeveloper = User::query()->firstWhere('github_id', 'local')
            ?? User::factory()->create(['github_id' => 'local', 'handle' => 'local-dev', 'name' => 'Local Developer']);

        Commit::factory()->for($localDeveloper)->count(3)->sequence(
            fn (): array => ['created_at' => fake()->dateTimeBetween('-3 days')],
        )->create();

        $commits = Commit::query()->topLevel()->get();

        $commits->each(fn (Commit $commit) => $this->engage($commit, $users));

        $this->updateCommitHotScores->handle($commits->map(fn (Commit $commit): string => $commit->id)->all());

        $localDeveloper->following()->syncWithoutDetaching($users->random(3)->modelKeys());
        $localDeveloper->followers()->syncWithoutDetaching($users->random(5)->modelKeys());

        $this->seedNotifications($localDeveloper, $users);

        $this->converse($localDeveloper, $users);
    }

    /**
     * Give the local sign-in account an inbox with a few different kinds of activity.
     *
     * @param  Collection<int, User>  $users
     */
    private function seedNotifications(User $localDeveloper, Collection $users): void
    {
        $follower = $localDeveloper->followers()->firstOrFail();
        [$stargazer, $replier, $mentioner] = $users->except([$follower->id])->random(3)->values()->all();
        $commit = $localDeveloper->commits()->firstOrFail();

        Notification::factory()->for($localDeveloper, 'recipient')->for($follower, 'actor')->create([
            'created_at' => now()->subDays(2),
            'read_at' => now()->subDay(),
        ]);

        Notification::factory()->for($localDeveloper, 'recipient')->for($stargazer, 'actor')
            ->starred($commit)->create(['created_at' => now()->subHours(3)]);

        $repliers = $users->except([$follower->id, $stargazer->id, $mentioner->id, $replier->id])->random(4)->values()->all();
        array_unshift($repliers, $replier);

        for ($index = 0; $index < 10; $index++) {
            Notification::factory()->for($localDeveloper, 'recipient')->for($repliers[$index % count($repliers)], 'actor')
                ->replied($commit)->create(['created_at' => now()->subMinutes(60 - $index * 5)]);
        }

        Notification::factory()->for($localDeveloper, 'recipient')->for($mentioner, 'actor')
            ->mentioned()->create(['created_at' => now()->subMinutes(10)]);
    }

    /**
     * Remotes on for everyone, the local developer taking them from people they follow. With three of those: one
     * conversation waiting on them, one where their reply was read, and one where it was not yet. And, while
     * requests are on, a request fetched from someone they don't follow. Plus an org they maintain with those three.
     *
     * @param  Collection<int, User>  $users
     */
    private function converse(User $localDeveloper, Collection $users): void
    {
        User::query()->whereKeyNot($localDeveloper->id)->update(['remotes_enabled' => true, 'remotes_from' => RemotesFrom::Everyone]);
        $localDeveloper->update(['remotes_enabled' => true, 'remotes_from' => RemotesFrom::Following]);

        $followed = $localDeveloper->following()->pluck('users.id');
        [$waiting, $read, $unread] = $users->whereIn('id', $followed)->values()->all();
        $stranger = $users->whereNotIn('id', $followed)->firstOrFail();
        $now = Date::now();

        $this->chat([
            [$waiting, $localDeveloper, 'the polling PR is up, can you take a look when you get a sec?', '-1 day 18:40'],
            [$localDeveloper, $waiting, 'on it after dinner', '-1 day 18:52'],
            [$localDeveloper, $waiting, 'merged. messages show up in about a second now', 'today 09:14'],
            [$waiting, $localDeveloper, 'ship it', 'today 09:20'],
            [$waiting, $localDeveloper, 'what do the little dots under my messages mean?', 'today 09:21'],
        ], $now);

        $this->chat([
            [$read, $localDeveloper, 'want to pair on the composer this week?', '-3 days 16:05'],
            [$localDeveloper, $read, 'thursday works', '-3 days 16:30'],
        ], $now, readBy: $read);

        $this->chat([
            [$unread, $localDeveloper, 'nice work on the photo viewer downloads', '-2 days 11:02'],
            [$localDeveloper, $unread, 'thanks! more coming soon', '-2 days 11:15'],
        ], $now);

        Date::setTestNow($now->copy()->modify('-1 day 10:00'));
        $org = $this->createOrg->handle($localDeveloper, 'dev/null core', collect([$waiting, $read, $unread]));

        foreach ([
            [$waiting, 'standup in 10?', '-1 day 10:02'],
            [$read, 'can we pin the polling PR in the log?', 'today 08:40'],
            [$localDeveloper, 'pinned. remotes go out today', 'today 08:45'],
            [$unread, 'nice, I will write the changelog', 'today 09:05'],
        ] as [$sender, $body, $at]) {
            Date::setTestNow($now->copy()->modify($at));
            $this->createMessage->handle($sender, $org, $body, Str::uuid()->toString());
        }

        Date::setTestNow();

        if (config('remotes.requests')) {
            $this->chat([
                [$stranger, $localDeveloper, 'hey! loved your talk on polling, could I ask you a few questions about it?', 'today 08:02'],
            ], $now);
        }
    }

    /**
     * Sending a message marks the conversation read for its sender, so only a reader who replied nothing needs marking.
     *
     * @param  list<array{User, User, string, string}>  $messages
     */
    private function chat(array $messages, CarbonInterface $now, ?User $readBy = null): void
    {
        foreach ($messages as [$sender, $recipient, $body, $at]) {
            Date::setTestNow($now->copy()->modify($at));
            $conversation = $this->createConversation->handle($sender, $recipient);
            $message = $this->createMessage->handle($sender, $conversation, $body, Str::uuid()->toString());
        }

        Date::setTestNow();

        if ($readBy instanceof User && isset($message, $conversation)) {
            $this->updateConversationReadThrough->handle($readBy, $conversation, $message->id);
        }
    }

    /**
     * Most commits draw little attention and a few draw a lot, as on any timeline.
     *
     * @param  Collection<int, User>  $users
     */
    private function engage(Commit $commit, Collection $users): void
    {
        $author = $commit->user;

        if (! $author instanceof User) {
            return;
        }

        $others = $users->except([$author->id]);
        $isHit = fake()->boolean(15);

        $commit->stars()->attach(
            $others->random(fake()->numberBetween(0, $isHit ? $others->count() : 2))->modelKeys(),
        );

        $parent = $commit;

        $replyCount = fake()->numberBetween(0, $isHit ? 6 : 1);

        for ($index = 0; $index < $replyCount; $index++) {
            $parent = Commit::factory()
                ->for(fake()->boolean(70) ? $others->random() : $author)
                ->for(fake()->boolean(60) ? $parent : $commit, 'parent')
                ->create(['created_at' => fake()->dateTimeBetween($parent->created_at)]);
        }
    }
}
