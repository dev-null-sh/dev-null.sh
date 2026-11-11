<?php

declare(strict_types=1);

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config(['app.admins' => ['moderator-demo']]);
    $this->admin = User::factory()->create(['handle' => 'moderator-demo']);
});

it('lists open message and user reports oldest first, with the message and its author, without who reported', function (): void {
    $older = MessageReport::factory()->for(Message::factory()->create(['body' => 'buy my course']))->create(['created_at' => now()->subHour(), 'details' => 'Spam DM.']);
    $newer = MessageReport::factory()->create();
    MessageReport::factory()->create(['dismissed_at' => now()]);
    $person = UserReport::factory()->create(['reason' => 'harassment']);
    UserReport::factory()->create(['dismissed_at' => now()]);

    $this->actingAs($this->admin)->get(route('reported-user.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('moderation/people-reports')
            ->where('reasons.harassment', 'Harassment or hate')
            ->has('messageReports', 2)
            ->where('messageReports.0.id', $older->id)
            ->where('messageReports.0.details', 'Spam DM.')
            ->where('messageReports.0.message.body', 'buy my course')
            ->where('messageReports.0.message.user.handle', $older->message->user->handle)
            ->missing('messageReports.0.reporter_id')
            ->where('messageReports.1.id', $newer->id)
            ->has('userReports', 1)
            ->where('userReports.0.id', $person->id)
            ->where('userReports.0.reason', 'harassment')
            ->where('userReports.0.user.handle', $person->user->handle)
            ->missing('userReports.0.reporter_id'));
});

it('dismisses message and user reports, telling moderators', function (): void {
    Event::fake([UsersUpdated::class]);
    $message = MessageReport::factory()->create();
    $user = UserReport::factory()->create();

    $this->actingAs($this->admin)->fromRoute('reported-user.index')
        ->delete(route('message-report.destroy', $message))->assertRedirectToRoute('reported-user.index');
    $this->fromRoute('reported-user.index')
        ->delete(route('user-report.destroy', $user))->assertRedirectToRoute('reported-user.index');

    expect($message->refresh()->dismissed_at)->not->toBeNull()
        ->and($user->refresh()->dismissed_at)->not->toBeNull();
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Moderation && $event->data === ['message_reports' => [$message->id]]);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Moderation && $event->data === ['user_reports' => [$user->id]]);
    $this->get(route('reported-user.index'))->assertInertia(fn ($page) => $page->has('messageReports', 0)->has('userReports', 0));
});

it('keeps the queue and dismissals to moderators', function (): void {
    $message = MessageReport::factory()->create();
    $user = UserReport::factory()->create();

    foreach ([$message->message->user, $user->user, User::factory()->create()] as $member) {
        $this->actingAs($member)->get(route('reported-user.index'))->assertForbidden();
        $this->delete(route('message-report.destroy', $message))->assertForbidden();
        $this->delete(route('user-report.destroy', $user))->assertForbidden();
    }

    expect($message->refresh()->dismissed_at)->toBeNull()
        ->and($user->refresh()->dismissed_at)->toBeNull();
});
