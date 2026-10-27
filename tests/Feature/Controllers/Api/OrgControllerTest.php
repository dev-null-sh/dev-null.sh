<?php

declare(strict_types=1);

use App\Enums\OrgEvent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserTimeout;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('starts an org with people who follow the user, who maintains it', function (): void {
    [$nuno, $taylor] = followersOf($this->user, 2);

    $response = signedApiRequest($this->keyPair, 'POST', route('api-org.store'), [
        'name' => '  dev/null core  ',
        'members' => [$nuno->handle, $taylor->handle],
    ]);

    $org = Conversation::query()->sole();

    $response->assertCreated()->assertExactJson(['org' => ['id' => $org->id]]);

    expect($org->id)->toBeUuid()
        ->and($org->name)->toBe('dev/null core')
        ->and($org->roleOf($this->user)?->value)->toBe('maintainer')
        ->and($org->roleOf($nuno)?->value)->toBe('contributor')
        ->and($org->roleOf($taylor)?->value)->toBe('contributor')
        ->and(Message::query()->sole()->event)->toBe(OrgEvent::Created);
});

it('leaves an org without a name when none is given', function (?string $name): void {
    [$nuno] = followersOf($this->user, 1);

    signedApiRequest($this->keyPair, 'POST', route('api-org.store'), ['name' => $name, 'members' => [$nuno->handle]])->assertCreated();

    expect(Conversation::query()->sole()->name)->toBeNull();
})->with([null, '', '   ']);

it('says why people cannot be added, as the web does', function (string $case, string $reason): void {
    [$nuno] = followersOf($this->user, 1);
    $candidate = User::factory()->create(['handle' => 'candidate']);

    if ($case !== 'not a follower') {
        $candidate->following()->attach($this->user);
    }

    match ($case) {
        'remotes off' => $candidate->update(['remotes_enabled' => false]),
        'blocked by someone joining' => $nuno->blocking()->attach($candidate),
        'blocked by the maintainer' => $this->user->blocking()->attach($candidate),
        default => null,
    };

    signedApiRequest($this->keyPair, 'POST', route('api-org.store'), ['members' => ['candidate', $nuno->handle]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['members.0' => "@candidate {$reason}."]);

    expect(Conversation::query()->exists())->toBeFalse();
})->with([
    ['not a follower', "doesn't follow you"],
    ['remotes off', 'has remotes off'],
    ['blocked by someone joining', 'has a block with someone in the org'],
    ['blocked by the maintainer', 'has a block with someone in the org'],
]);

it('refuses someone who does not follow the user, in the api error format', function (): void {
    User::factory()->create(['handle' => 'taylorotwell']);

    signedApiRequest($this->keyPair, 'POST', route('api-org.store'), ['members' => ['taylorotwell']])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => "@taylorotwell doesn't follow you.",
            'errors' => ['members.0' => ["@taylorotwell doesn't follow you."]],
        ]);
});

it('lets site admins add anyone with remotes on', function (): void {
    config(['app.admins' => [$this->user->handle]]);
    $nuno = User::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-org.store'), ['members' => [$nuno->handle]])->assertCreated();

    expect(Conversation::query()->sole()->roleOf($nuno)?->value)->toBe('contributor');
});

it('caps an org at a hundred people', function (): void {
    $members = array_map(fn (User $member): string => $member->handle, followersOf($this->user, Conversation::MAX_MEMBERS));

    signedApiRequest($this->keyPair, 'POST', route('api-org.store'), ['members' => $members])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('members');

    signedApiRequest($this->keyPair, 'POST', route('api-org.store'), ['members' => array_slice($members, 1)])->assertCreated();
});

it('refuses an org it cannot start', function (Closure $data, array $fields): void {
    [$nuno] = followersOf($this->user, 1);

    signedApiRequest($this->keyPair, 'POST', route('api-org.store'), $data($nuno))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($fields);

    expect(Conversation::query()->exists())->toBeFalse();
})->with([
    'no members' => [fn (User $nuno): array => ['name' => 'core'], ['members']],
    'an empty list' => [fn (User $nuno): array => ['members' => []], ['members']],
    'someone who does not exist' => [fn (User $nuno): array => ['members' => [$nuno->handle, 'nobody-by-that-name']], ['members.1']],
    'the same person twice' => [fn (User $nuno): array => ['members' => [$nuno->handle, $nuno->handle]], ['members.0', 'members.1']],
    'a long name' => [fn (User $nuno): array => ['name' => Str::repeat('a', 61), 'members' => [$nuno->handle]], ['name']],
]);

it('is not there while the user has Remotes off', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $this->user->update(['remotes_enabled' => false]);

    signedApiRequest($this->keyPair, 'POST', route('api-org.store'), ['members' => [$nuno->handle]])->assertNotFound();
});

it('shares the web limits of 30 pushes and 300 Remotes requests a minute', function (string $limiter, int $limit): void {
    [$nuno] = followersOf($this->user, 1);

    RateLimiter::increment(md5($limiter.$this->user->id), amount: $limit);

    signedApiRequest($this->keyPair, 'POST', route('api-org.store'), ['members' => [$nuno->handle]])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited');

    expect(Conversation::query()->exists())->toBeFalse();
})->with([
    ['messages', 30],
    ['conversations', 300],
]);

it('rejects requests that are not signed', function (): void {
    $this->postJson(route('api-org.store'), ['members' => ['nunomaduro']])->assertUnauthorized();
});

it('lets maintainers rename the org, as the web does', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno], name: null);

    signedApiRequest($this->keyPair, 'PUT', route('api-org.update', $org), ['name' => ' polling crew '])->assertNoContent();

    expect(Conversation::query()->findOrFail($org)->name)->toBe('polling crew')
        ->and(Message::query()->latest('id')->firstOrFail()->event)->toBe(OrgEvent::Renamed);

    signedApiRequest($this->keyPair, 'PUT', route('api-org.update', $org), ['name' => null])->assertNoContent();

    expect(Conversation::query()->findOrFail($org)->name)->toBeNull();

    signedApiRequest($this->keyPair, 'PUT', route('api-org.update', $org), ['name' => Str::repeat('a', 61)])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('name');
});

it('keeps contributors and outsiders from renaming the org', function (): void {
    $mark = User::factory()->create();
    $this->user->following()->attach($mark);
    $org = createOrg($mark, [$this->user]);
    $elsewhere = createOrg($mark, followersOf($mark, 1));

    signedApiRequest($this->keyPair, 'PUT', route('api-org.update', $org), ['name' => 'nope'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only maintainers can rename the org.');
    signedApiRequest($this->keyPair, 'PUT', route('api-org.update', $elsewhere), ['name' => 'nope'])->assertNotFound();

    expect(Conversation::query()->findOrFail($org)->name)->toBe('dev/null core');
});

it('refuses renaming during a timeout', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);
    UserTimeout::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, 'PUT', route('api-org.update', $org), ['name' => 'buy my course'])
        ->assertForbidden()
        ->assertJsonPath('code', 'timed_out');

    expect(Conversation::query()->findOrFail($org)->name)->toBe('dev/null core');
});
