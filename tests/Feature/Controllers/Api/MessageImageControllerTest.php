<?php

declare(strict_types=1);

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake();
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
    $this->nuno = User::factory()->withRemotes()->create();

    $this->actingAs($this->nuno)->post(route('conversation-message.store', $this->user), [
        'body' => 'look',
        'client_id' => Str::uuid()->toString(),
        'images' => [UploadedFile::fake()->image('screenshot.png', 40, 20)],
    ])->assertCreated();

    $this->image = Message::query()->sole()->images()->sole();
});

function signedImageUrl(): string
{
    return signedApiRequest(test()->keyPair, 'GET', route('api-conversation-message.index', test()->nuno))->json('data.messages.0.images.0.url');
}

it('serves an image from the URL a thread hands out, without signing the request', function (): void {
    $this->travelTo(now()->setTime(10, 0));

    $url = signedImageUrl();

    expect($url)->toStartWith(route('api-message-image.show', $this->image).'?expires='.today()->addDays(2)->timestamp);

    $this->travelTo(now()->setTime(23, 0));

    expect(signedImageUrl())->toBe($url);

    $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('Cache-Control', 'max-age=3600, private')
        ->assertStreamedContent(Storage::disk()->get($this->image->path));
});

it('stops serving it once the URL expires or is changed', function (): void {
    $url = signedImageUrl();

    $this->get(str_replace('user='.$this->user->id, 'user='.$this->nuno->id, $url))->assertForbidden();

    $this->travelTo(today()->addDays(2)->addSecond());

    $this->get($url)->assertForbidden();
});

it('stops serving it once the user can no longer see the message', function (): void {
    $this->user->following()->attach($this->nuno);
    $org = createOrg($this->nuno, [$this->user]);

    $this->actingAs($this->nuno)->post(route('org-message.store', $org), [
        'body' => 'look',
        'client_id' => Str::uuid()->toString(),
        'images' => [UploadedFile::fake()->image('screenshot.png', 40, 20)],
    ])->assertCreated();

    $url = signedApiRequest($this->keyPair, 'GET', route('api-org-message.index', $org))->json('data.messages.1.images.0.url');

    $this->get($url)->assertOk();

    $this->actingAs($this->nuno)->deleteJson(route('org-member.destroy', ['org' => $org, 'member' => $this->user->username]))->assertNoContent();

    $this->get($url)->assertNotFound();
});

it('does not serve an image whose file is gone', function (): void {
    $url = signedImageUrl();

    Storage::disk()->delete($this->image->path);

    $this->get($url)->assertNotFound();
});

it('does not serve an image without a signature', function (): void {
    $this->get(route('api-message-image.show', ['messageImage' => $this->image, 'user' => $this->user->id]))->assertForbidden();
});

it('signs the URL for one person only', function (): void {
    $stranger = User::factory()->create();

    $this->get(url(URL::temporarySignedRoute('api-message-image.show', now()->addHour(), ['messageImage' => $this->image, 'user' => $stranger->id], absolute: false)))
        ->assertNotFound();
});
