<?php

declare(strict_types=1);

use App\Jobs\LookUpUserOrganizations;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('queues a lookup for every user whose bio or company mentions someone', function (): void {
    Queue::fake();

    $bio = User::factory()->create(['bio' => 'Building @laravel']);
    $company = User::factory()->create(['company' => '@laravel']);
    User::factory()->create(['bio' => 'Building things', 'company' => null]);

    $this->artisan('users:look-up-organizations')
        ->expectsOutputToContain('Queued the organizations of 2 users to be looked up.')
        ->assertSuccessful();

    Queue::assertPushed(LookUpUserOrganizations::class, 2);
    Queue::assertPushed(LookUpUserOrganizations::class, fn (LookUpUserOrganizations $job): bool => $job->user->is($bio));
    Queue::assertPushed(LookUpUserOrganizations::class, fn (LookUpUserOrganizations $job): bool => $job->user->is($company));
});
