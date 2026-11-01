<?php

declare(strict_types=1);

use App\Actions\DeleteOrganization;
use App\Actions\DeleteOrganizationMember;
use App\Enums\Feature;
use App\Enums\OrganizationRole;
use App\Models\Commit;
use App\Models\Organization;
use App\Models\ScheduledCommit;
use App\Models\User;
use App\Services\Features;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
    resolve(Features::class)->update(Feature::OrganizationCommits, true);
    $this->organization = Organization::factory()->create(['handle' => 'Laravel']);
    $this->member = User::factory()->create(['commit_rules_accepted_at' => now()]);
    $this->organization->members()->attach($this->member->id, ['role' => OrganizationRole::Member]);
});

it('lets a member schedule a commit as the organization, which goes out as it when due', function (): void {
    $this->actingAs($this->member)
        ->post(route('scheduled-commit.store'), ['body' => 'Laravel 13.3 ships today', 'author' => 'laravel', 'scheduled_at' => now()->addHour()->toIso8601String()])
        ->assertSessionHasNoErrors();

    $this->get(route('scheduled-commit.index'))
        ->assertInertia(fn ($page) => $page
            ->where('scheduledCommits.data.0.author.type', 'organization')
            ->where('scheduledCommits.data.0.author.handle', 'Laravel'));

    $this->travel(1)->hour();
    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    $commit = Commit::query()->sole();

    expect($commit->author_id)->toBe($this->organization->id)
        ->and($commit->user_id)->toBe($this->member->id)
        ->and(ScheduledCommit::query()->sole()->commit_id)->toBe($commit->id);
});

it('lets apps schedule a commit as the organization through the API, and says who it goes out as', function (): void {
    $keyPair = apiKeyPairFor($this->member);

    signedApiRequest($keyPair, 'POST', route('api-scheduled-commit.store'), ['body' => 'Laravel 13.3 ships today', 'author' => 'laravel', 'scheduled_at' => now()->addHour()->toIso8601String()])
        ->assertCreated()
        ->assertJsonPath('data.author.type', 'organization')
        ->assertJsonPath('data.author.id', $this->organization->id)
        ->assertJsonPath('data.author.handle', 'Laravel');
    signedApiRequest($keyPair, 'POST', route('api-scheduled-commit.store'), ['body' => 'My own release notes', 'scheduled_at' => now()->addHours(2)->toIso8601String()])
        ->assertCreated()
        ->assertJsonPath('data.author', null);

    expect(ScheduledCommit::query()->oldest('scheduled_at')->pluck('author_id')->all())->toBe([$this->organization->id, null]);
});

it('lets no one schedule as an organization they are not a member of, or before administrators switch it on', function (): void {
    $this->actingAs(User::factory()->create(['commit_rules_accepted_at' => now()]))
        ->post(route('scheduled-commit.store'), ['body' => 'Not mine to post', 'author' => 'laravel', 'scheduled_at' => now()->addHour()->toIso8601String()])
        ->assertSessionHasErrors(['author' => 'You can only post as an organization you are a member of.']);

    resolve(Features::class)->update(Feature::OrganizationCommits, false);

    $this->actingAs($this->member)
        ->post(route('scheduled-commit.store'), ['body' => 'Not yet', 'author' => 'laravel', 'scheduled_at' => now()->addHour()->toIso8601String()])
        ->assertSessionHasErrors(['author' => "Posting as an organization isn't available yet."]);
    signedApiRequest(apiKeyPairFor($this->member), 'POST', route('api-scheduled-commit.store'), ['body' => 'Not yet', 'author' => 'laravel', 'scheduled_at' => now()->addHour()->toIso8601String()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('author');

    expect(ScheduledCommit::query()->exists())->toBeFalse();
});

it('cancels what a member scheduled as the organization once they are no longer in it, images included', function (): void {
    Storage::fake();
    $this->actingAs($this->member)->post(route('scheduled-commit.store'), [
        'body' => 'Laravel 13.3 ships today',
        'author' => 'laravel',
        'scheduled_at' => now()->addHour()->toIso8601String(),
        'images' => [UploadedFile::fake()->image('release.png', 300, 200)],
    ])->assertSessionHasNoErrors();
    $paths = array_column(ScheduledCommit::query()->sole()->images ?? [], 'path');
    $own = ScheduledCommit::factory()->for($this->member)->create(['scheduled_at' => now()->addHour()]);
    resolve(DeleteOrganizationMember::class)->handle($this->organization, $this->member);

    $this->travel(1)->hour();
    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    $canceled = ScheduledCommit::query()->whereKeyNot($own->id)->sole();

    expect($canceled->canceled_at)->not->toBeNull()
        ->and($canceled->body)->toBeNull()
        ->and($canceled->images)->toBeNull()
        ->and(Commit::query()->sole()->author_id)->toBe($this->member->id);
    Storage::disk()->assertMissing($paths);
});

it('cancels a commit scheduled as the organization that a former member tries to commit early', function (): void {
    $scheduled = ScheduledCommit::factory()->for($this->member)->create(['author_id' => $this->organization->id]);
    resolve(DeleteOrganizationMember::class)->handle($this->organization, $this->member);

    $this->actingAs($this->member)
        ->post(route('scheduled-commit-submission.store', $scheduled))
        ->assertInertiaFlash('toast.message', 'This commit was already submitted or canceled. Refresh to see its status.');

    expect($scheduled->refresh()->canceled_at)->not->toBeNull()
        ->and(Commit::query()->exists())->toBeFalse();
});

it('cancels what was scheduled as an organization once posting as one is switched off', function (): void {
    $scheduled = ScheduledCommit::factory()->for($this->member)->create(['author_id' => $this->organization->id, 'scheduled_at' => now()->addHour()]);
    resolve(Features::class)->update(Feature::OrganizationCommits, false);

    $this->travel(1)->hour();
    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    expect($scheduled->refresh()->canceled_at)->not->toBeNull()
        ->and(Commit::query()->exists())->toBeFalse();
});

it('deletes what was scheduled as an organization GitHub no longer has, images included', function (): void {
    Storage::fake();
    Storage::disk()->put('scheduled/release.png', 'image');
    ScheduledCommit::factory()->for($this->member)->create(['author_id' => $this->organization->id, 'images' => [['path' => 'scheduled/release.png', 'width' => 300, 'height' => 200, 'description' => null, 'position' => 0]]]);
    ScheduledCommit::factory()->for($this->member)->create(['author_id' => $this->organization->id]);
    $own = ScheduledCommit::factory()->for($this->member)->create();

    resolve(DeleteOrganization::class)->handle($this->organization);

    expect(ScheduledCommit::query()->sole()->id)->toBe($own->id);
    Storage::disk()->assertMissing('scheduled/release.png');
});
