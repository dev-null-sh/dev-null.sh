<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Services\Features;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Storage::fake();
    Storage::put('guild-icons/rust.webp', 'rust icon');
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);

    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
    $this->rust = Guild::factory()->create(['handle' => 'rust', 'icon_path' => 'guild-icons/rust.webp']);
});

it('serves a guild icon from the URL the directory hands out, without signing the request', function (): void {
    $this->travelTo(now()->setTime(10, 0));

    $url = signedApiRequest($this->keyPair, 'GET', route('api-guild.index'))->json('data.0.icon_url');

    expect($url)->toStartWith(route('api-guild-icon.show', 'rust').'?expires='.today()->addDays(2)->timestamp)
        ->toContain('user='.$this->user->id)
        ->toContain('v='.$this->rust->updated_at->getTimestampMs());

    $this->travelTo(now()->setTime(23, 0));

    expect(signedApiRequest($this->keyPair, 'GET', route('api-guild.index'))->json('data.0.icon_url'))->toBe($url);

    $response = $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'max-age=3600, private');

    expect($response->streamedContent())->toBe('rust icon');
});

it('hands out the API icon URL on commits, also to guests', function (): void {
    $commit = Commit::factory()->for($this->rust)->create();

    $signed = signedApiRequest($this->keyPair, 'GET', route('api-commit.show', $commit))->json('data.guild.icon_url');
    $guest = $this->getJson(route('api-commit.show', $commit))->json('data.guild.icon_url');

    expect($signed)->toContain('user='.$this->user->id)
        ->and($guest)->toStartWith(route('api-guild-icon.show', 'rust').'?')->not->toContain('user=');
    $this->get($signed)->assertOk();
    $this->get($guest)->assertOk();
});

it('stops serving it once the URL expires or is changed', function (): void {
    $url = signedApiRequest($this->keyPair, 'GET', route('api-guild.index'))->json('data.0.icon_url');

    $this->get(str_replace('user='.$this->user->id, 'user='.User::factory()->create()->id, $url))->assertForbidden();
    $this->get(route('api-guild-icon.show', ['guild' => 'rust', 'user' => $this->user->id]))->assertForbidden();

    $this->travelTo(today()->addDays(2)->addSecond());

    $this->get($url)->assertForbidden();
});

it('does not find an icon the guild lacks, or one its viewer may not see', function (): void {
    $go = Guild::factory()->create(['handle' => 'go', 'icon_path' => 'guild-icons/missing.webp']);
    $signedFor = fn (string $handle, ?User $user): string => url(URL::temporarySignedRoute('api-guild-icon.show', now()->addHour(), [
        'guild' => $handle,
        'user' => $user?->id,
    ], absolute: false));

    $this->get($signedFor('go', $this->user))->assertNotFound();
    $go->update(['icon_path' => null]);
    $this->get($signedFor('go', $this->user))->assertNotFound();

    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: true);

    $this->get($signedFor('rust', $this->user))->assertNotFound();
    $this->get($signedFor('rust', null))->assertNotFound();

    config(['app.admins' => [$this->user->handle]]);

    $this->get($signedFor('rust', $this->user))->assertOk();
    $this->json('GET', $signedFor('rust', null), ['user' => $this->user->id])->assertNotFound();
});

it('keeps the web icon URL for the web', function (): void {
    config(['app.admins' => ['admin']]);
    $admin = User::factory()->create(['handle' => 'admin']);

    $this->actingAs($admin)->get(route('guild.index'))->assertInertia(fn ($page) => $page
        ->where('guildPage.data.0.icon_url', route('guild-icon.show', ['guild' => 'rust', 'v' => $this->rust->updated_at->getTimestampMs()])));
});
