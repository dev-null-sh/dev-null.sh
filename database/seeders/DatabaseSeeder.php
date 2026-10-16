<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\UpdateCommitHotScores;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function __construct(private readonly UpdateCommitHotScores $updateCommitHotScores)
    {
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

        $localDeveloper = User::query()->firstOrCreate(
            ['github_id' => 'local'],
            ['username' => 'local_dev', 'name' => 'Local Developer'],
        );

        Commit::factory()->for($localDeveloper)->count(3)->sequence(
            fn (): array => ['created_at' => fake()->dateTimeBetween('-3 days')],
        )->create();

        $commits = Commit::query()->topLevel()->get();

        $commits->each(fn (Commit $commit) => $this->engage($commit, $users));

        $this->updateCommitHotScores->handle($commits->map(fn (Commit $commit): string => $commit->id)->all());

        $localDeveloper->following()->syncWithoutDetaching($users->random(3)->modelKeys());
        $localDeveloper->followers()->syncWithoutDetaching($users->random(5)->modelKeys());
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
