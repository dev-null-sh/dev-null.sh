<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

it('notifies the voters of the polls that closed', function (): void {
    $poll = Poll::factory()->closed()->withOptions('First', 'Second')->create();
    $voter = User::factory()->create();
    PollVote::factory()->create(['poll_option_id' => $poll->options->firstOrFail()->id, 'user_id' => $voter->id]);

    $this->artisan('polls:notify-ended')->expectsOutputToContain('Notified the voters of 1 closed polls.')->assertSuccessful();

    expect($voter->notifications()->sole()->type)->toBe(NotificationType::PollEnded);
});

it('notifies the same poll once across runs', function (): void {
    $poll = Poll::factory()->closed()->withOptions('First', 'Second')->create();
    $voter = User::factory()->create();
    PollVote::factory()->create(['poll_option_id' => $poll->options->firstOrFail()->id, 'user_id' => $voter->id]);

    $this->artisan('polls:notify-ended')->assertSuccessful();
    $this->artisan('polls:notify-ended')->assertSuccessful();

    expect($voter->notifications()->count())->toBeOne();
});

it('leaves polls that are running, unopened, or closed more than a day ago alone', function (): void {
    $running = Poll::factory()->withOptions('First', 'Second')->create();
    $unopened = Poll::factory()->unopened()->withOptions('First', 'Second')->create();
    $older = Poll::factory()->closed()->withOptions('First', 'Second')->create(['opened_at' => now()->subDays(4)]);
    $voter = User::factory()->create();

    foreach ([$running, $unopened, $older] as $poll) {
        PollVote::factory()->create(['poll_option_id' => $poll->options->firstOrFail()->id, 'user_id' => $voter->id]);
    }

    $this->artisan('polls:notify-ended')->expectsOutputToContain('Notified the voters of 0 closed polls.')->assertSuccessful();

    expect($voter->notifications()->exists())->toBeFalse();
});

it('keeps going and reports when the voters of one poll cannot be notified', function (): void {
    Exceptions::fake();

    $poll = Poll::factory()->closed()->withOptions('First', 'Second')->create();
    $voter = User::factory()->create();
    PollVote::factory()->create(['poll_option_id' => $poll->options->firstOrFail()->id, 'user_id' => $voter->id]);

    DB::beforeExecuting(function (string $query): void {
        throw_if(str_contains($query, 'insert') && str_contains($query, '"notifications"'), RuntimeException::class, 'Simulated insert failure.');
    });

    $this->artisan('polls:notify-ended')
        ->expectsOutputToContain('Could not notify the voters of poll '.$poll->id.'. It will be retried.')
        ->expectsOutputToContain('Notified the voters of 1 closed polls.')
        ->assertFailed();

    expect($voter->notifications()->exists())->toBeFalse();

    Exceptions::assertReported(RuntimeException::class);
});

it('notifies every closed poll in the window, whatever the backlog', function (): void {
    $author = User::factory()->create();
    $voter = User::factory()->create();

    foreach (range(1, 105) as $i) {
        $poll = Poll::factory()->for(Commit::factory()->for($author))->closed()->withOptions('First', 'Second')->create();
        PollVote::factory()->create(['poll_option_id' => $poll->options->firstOrFail()->id, 'user_id' => $voter->id]);
    }

    $this->artisan('polls:notify-ended')
        ->expectsOutputToContain('Notified the voters of 105 closed polls.')
        ->assertSuccessful();

    expect($voter->notifications()->where('type', 'poll_ended')->count())->toBe(105);
});
