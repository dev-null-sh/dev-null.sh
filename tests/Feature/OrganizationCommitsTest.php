<?php

declare(strict_types=1);

use App\Actions\CreateCommitRestoration;
use App\Actions\CreateCommitStar;
use App\Actions\CreateExport;
use App\Actions\DeleteOrganization;
use App\Actions\DeleteUser;
use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
use App\Enums\Feature;
use App\Enums\Log;
use App\Enums\OrganizationRole;
use App\Jobs\CheckUserLevel;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Poll;
use App\Models\User;
use App\Queries\LatestLogCommitsQuery;
use App\Services\Features;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    resolve(Features::class)->update(Feature::OrganizationCommits, true);
    $this->organization = Organization::factory()->create(['handle' => 'Laravel']);
    $this->member = User::factory()->create(['pasteis_mode' => true]);
    $this->organization->members()->attach($this->member->id, ['role' => OrganizationRole::Member]);
});

it('lets a member commit as the organization, by its handle in any case', function (): void {
    $mentioned = User::factory()->create(['handle' => 'taylor']);

    $this->actingAs($this->member)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Laravel 13 is out, thanks @taylor', 'author' => 'laravel'])
        ->assertRedirectToRoute('log.show', Log::Following)
        ->assertInertiaFlash('committed.author', ['handle' => 'Laravel']);

    $commit = Commit::query()->sole();

    expect($commit->author_id)->toBe($this->organization->id)
        ->and($commit->user_id)->toBe($this->member->id)
        ->and(Notification::query()->where('recipient_id', $mentioned->id)->exists())->toBeFalse();
});

it('lets no one post as an organization until administrators switch it on', function (): void {
    resolve(Features::class)->update(Feature::OrganizationCommits, false);

    $this->actingAs($this->member)
        ->post(route('commit.store'), ['body' => 'Not yet', 'author' => 'laravel'])
        ->assertSessionHasErrors(['author' => "Posting as an organization isn't available yet."]);
    signedApiRequest(apiKeyPairFor($this->member), 'POST', route('api-commit.store'), ['body' => 'Not yet', 'author' => 'laravel'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('author');
    $this->get(route('log.show', Log::Following))
        ->assertInertia(fn ($page) => $page
            ->where('auth.canPostAsOrganization', false)
            ->has('auth.organizations', 1));

    expect(Commit::query()->exists())->toBeFalse();
});

it('only lets members commit as an organization', function (): void {
    $this->actingAs(User::factory()->create())
        ->post(route('commit.store'), ['body' => 'Not mine to post', 'author' => 'laravel'])
        ->assertSessionHasErrors(['author' => 'You can only post as an organization you are a member of.']);

    expect(Commit::query()->exists())->toBeFalse();
});

it('lets a member reply as the organization', function (): void {
    $parent = Commit::factory()->create();

    $this->actingAs($this->member)
        ->postJson(route('commit-reply.store', $parent), ['body' => 'Fixed in 13.1', 'author' => 'laravel'])
        ->assertCreated()
        ->assertJsonPath('committed.author.handle', 'Laravel');

    expect($parent->replies()->sole()->author_id)->toBe($this->organization->id)
        ->and(Notification::query()->where('recipient_id', $parent->user_id)->exists())->toBeFalse();
});

it('lets apps commit as the organization through the API', function (): void {
    signedApiRequest(apiKeyPairFor($this->member), 'POST', route('api-commit.store'), ['body' => 'Laravel 13 is out', 'author' => 'laravel'])
        ->assertCreated()
        ->assertJsonPath('data.author.type', 'organization')
        ->assertJsonPath('data.author.handle', 'Laravel');
});

it('lets the members of an organization delete its commits, and only them', function (): void {
    $commit = Commit::factory()->for(User::factory())->create(['author_id' => $this->organization->id]);

    $this->actingAs(User::factory()->create())->delete(route('commit.destroy', $commit))->assertForbidden();
    $this->actingAs($this->member)->delete(route('commit.destroy', $commit))->assertRedirect();

    expect($commit->refresh()->trashed())->toBeTrue();
});

it("treats the commits of an organization as its members' own", function (): void {
    $commit = Commit::factory()->for(User::factory())->create(['author_id' => $this->organization->id]);

    $this->actingAs($this->member);

    $this->post(route('commit-star.store', $commit))->assertForbidden();
    $this->post(route('commit-report.store', $commit), ['reason' => 'spam'])->assertForbidden();
    $this->post(route('commit-pastel.store', $commit))->assertForbidden();
    $this->getJson(route('commit-star.index', $commit))->assertOk();
});

it("takes no pastéis and pins on no one's profile", function (): void {
    $commit = Commit::factory()->for($this->member)->create(['author_id' => $this->organization->id]);

    $this->actingAs(User::factory()->create(['pasteis_mode' => true]))->post(route('commit-pastel.store', $commit))->assertNotFound();
    $this->actingAs($this->member)->put(route('commit-pin.update', $commit))->assertForbidden();
});

it("raises no one's level for stars on the commits of an organization", function (): void {
    Bus::fake([CheckUserLevel::class]);
    $stargazer = User::factory()->create();
    $commit = Commit::factory()->for($this->member)->create(['author_id' => $this->organization->id]);

    $this->actingAs($stargazer)->post(route('commit-star.store', $commit));

    Bus::assertDispatched(CheckUserLevel::class, fn (CheckUserLevel $job): bool => $job->user->is($stargazer));
    Bus::assertNotDispatched(CheckUserLevel::class, fn (CheckUserLevel $job): bool => $job->user->is($this->member));
});

it('keeps the commits someone posted for an organization when they delete their account', function (): void {
    $commit = Commit::factory()->for($this->member)->create(['author_id' => $this->organization->id]);
    $own = Commit::factory()->for($this->member)->for(Commit::factory(), 'parent')->create();
    Commit::factory()->for($own, 'parent')->create();

    resolve(DeleteUser::class)->handle($this->member);

    $tombstone = Commit::withTrashed()->findOrFail($own->id);

    expect($commit->refresh()->user_id)->toBeNull()
        ->and($commit->author_id)->toBe($this->organization->id)
        ->and($commit->trashed())->toBeFalse()
        ->and($tombstone->author_id)->toBeNull();
});

it('notifies no one about the commits of an organization whose poster deleted their account', function (): void {
    $commit = Commit::factory()->for($this->member)->create(['author_id' => $this->organization->id]);
    resolve(DeleteUser::class)->handle($this->member);
    $reply = Commit::factory()->for($commit, 'parent')->pending()->create();
    $quote = Commit::factory()->pending()->create(['quoted_commit_id' => $commit->id]);

    resolve(CreateCommitStar::class)->handle(User::factory()->create(), $commit->refresh());
    resolve(UpdateCommitStatuses::class)->handle([$reply->id, $quote->id], CommitStatus::Approved);
    resolve(UpdateCommitStatuses::class)->handle([$commit->id], CommitStatus::Rejected);

    expect($commit->refresh()->status)->toBe(CommitStatus::Rejected)
        ->and(Notification::query()->exists())->toBeFalse();
});

it("tells a member of new commits that are not their organization's", function (): void {
    $seen = Commit::factory()->create(['created_at' => now()->subMinute()]);
    Commit::factory()->for(User::factory())->create(['author_id' => $this->organization->id]);
    Commit::factory()->create();

    expect(resolve(LatestLogCommitsQuery::class)->count(Log::Latest, $this->member, $seen))->toBe(1);
});

it('deletes the commits of an organization GitHub no longer has, keeping the replies under them', function (): void {
    $commit = Commit::factory()->for($this->member)->create(['author_id' => $this->organization->id]);
    $reply = Commit::factory()->for($commit, 'parent')->create();

    resolve(DeleteOrganization::class)->handle($this->organization);

    $commit = Commit::withTrashed()->findOrFail($commit->id);

    expect($commit->trashed())->toBeTrue()
        ->and($commit->author_id)->toBeNull()
        ->and($reply->refresh()->trashed())->toBeFalse();
});

it('exports the commits someone posted for an organization', function (): void {
    $commit = Commit::factory()->for($this->member)->create(['author_id' => $this->organization->id, 'body' => 'Laravel 13 is out']);

    $export = resolve(CreateExport::class)->handle($this->member);

    expect(iterator_to_array($export['organization_commits']))->toBe([[
        'id' => $commit->id,
        'organization' => 'Laravel',
        'parent_id' => null,
        'quoted_commit_id' => null,
        'body' => 'Laravel 13 is out',
        'status' => 'approved',
        'images' => [],
        'created_at' => $commit->created_at->toIso8601String(),
        'deleted_at' => null,
    ]])->and(iterator_to_array($export['commits']))->toBe([]);
});

it('exports the images and polls of what someone posted for an organization, even once GitHub no longer has it', function (): void {
    $commit = Commit::factory()->for($this->member)->create(['author_id' => $this->organization->id]);
    $image = CommitImage::factory()->for($commit)->create();
    Poll::factory()->for($commit)->withOptions('Tabs', 'Spaces')->create();

    $export = resolve(CreateExport::class)->handle($this->member);
    $exported = iterator_to_array($export['organization_commits'])[0];

    expect($exported['images'])->toBe([route('commit-image.show', $image)])
        ->and(iterator_to_array($export['polls'])[0]['options'])->toBe(['Tabs', 'Spaces']);

    resolve(DeleteOrganization::class)->handle($this->organization);

    $exported = iterator_to_array(resolve(CreateExport::class)->handle($this->member)['organization_commits']);

    expect($exported)->toHaveCount(1)
        ->and($exported[0]['id'])->toBe($commit->id)
        ->and($exported[0]['organization'])->toBeNull()
        ->and($exported[0]['deleted_at'])->not->toBeNull();
});

it('lists the commits of an organization on its page, first', function (): void {
    $commit = Commit::factory()->create(['author_id' => $this->organization->id]);
    Commit::factory()->for($commit, 'parent')->create(['author_id' => $this->organization->id]);

    $this->get('/@Laravel')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'commits')
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $commit->id));
});

it('shares the organizations a member can post as', function (): void {
    $this->actingAs($this->member)
        ->get(route('log.show', Log::Following))
        ->assertInertia(fn ($page) => $page
            ->has('auth.organizations', 1)
            ->where('auth.organizations.0.type', 'organization')
            ->where('auth.organizations.0.id', $this->organization->id)
            ->where('auth.organizations.0.handle', 'Laravel')
            ->where('auth.organizations.0.lane', null));
});

it('never restores a commit of an organization GitHub no longer has, even within the undo window', function (): void {
    $live = Commit::factory()->for($this->member)->create(['author_id' => $this->organization->id]);
    $deleted = Commit::factory()->for($this->member)->create(['author_id' => $this->organization->id]);
    $deleted->delete();

    resolve(DeleteOrganization::class)->handle($this->organization);

    foreach ([$live, $deleted] as $commit) {
        expect(resolve(CreateCommitRestoration::class)->handle($commit)->trashed())->toBeTrue();
    }
});

it('lets a member restore a commit of the organization whose poster deleted their account', function (): void {
    $poster = User::factory()->create();
    $this->organization->members()->attach($poster->id, ['role' => OrganizationRole::Member]);
    $commit = Commit::factory()->for($poster)->create(['author_id' => $this->organization->id]);
    resolve(DeleteUser::class)->handle($poster);

    $this->actingAs($this->member)->delete(route('commit.destroy', $commit))->assertRedirect();
    $this->post(route('commit-restoration.store', $commit))->assertRedirect();

    expect($commit->refresh()->trashed())->toBeFalse();
});
