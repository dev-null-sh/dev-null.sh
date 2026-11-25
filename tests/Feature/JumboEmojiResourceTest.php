<?php

declare(strict_types=1);

use App\Http\Resources\MessageResource;
use App\Models\Commit;
use App\Models\Message;
use App\Models\MessageImage;
use App\Models\MessageVoice;
use App\Models\User;
use Illuminate\Http\Request;

function jumboEmojiOf(Message $message): mixed
{
    $resource = MessageResource::make($message);
    $resource->withGuildMentions = false;

    return $resource->toArray(Request::create('/'))['jumbo_emoji'];
}

it('tells API clients how many emoji a message shows large, as the web thread does', function (): void {
    expect(jumboEmojiOf(Message::factory()->create(['body' => '🚀'])))->toBe(1)
        ->and(jumboEmojiOf(Message::factory()->create(['body' => ':tada: 🎉'])))->toBe(2)
        ->and(jumboEmojiOf(Message::factory()->create(['body' => 'Awesome 🚀'])))->toBe(0)
        ->and(jumboEmojiOf(Message::factory()->create(['body' => '🎉🚀✨🙌'])))->toBe(0);
});

it('shows a message with images or a voice note at its normal size, whether they were loaded or not', function (bool $loaded): void {
    $withImage = MessageImage::factory()->for(Message::factory()->state(['body' => '🚀']))->create()->message;
    $withVoice = MessageVoice::factory()->for(Message::factory()->state(['body' => '🚀']))->create()->message;
    $alone = Message::factory()->create(['body' => '🚀']);

    foreach ([$withImage, $withVoice, $alone] as $message) {
        $message->unsetRelations();

        if ($loaded) {
            $message->load(['images', 'voice']);
        }
    }

    expect(jumboEmojiOf($withImage))->toBe(0)
        ->and(jumboEmojiOf($withVoice))->toBe(0)
        ->and(jumboEmojiOf($alone))->toBe(1);
})->with(['loaded' => true, 'looked up' => false]);

it('tells public and signed API clients how many emoji a commit shows large', function (): void {
    $user = User::factory()->create();
    $fire = Commit::factory()->create(['body' => '🔥']);
    $words = Commit::factory()->create(['body' => 'nice one 🔥']);

    $this->getJson(route('api-commit.show', $fire))->assertOk()->assertJsonPath('data.jumbo_emoji', 1);
    $this->getJson(route('api-commit.show', $words))->assertOk()->assertJsonPath('data.jumbo_emoji', 0);

    signedApiRequest(apiKeyPairFor($user), 'GET', route('api-commit.show', $fire))
        ->assertOk()
        ->assertJsonPath('data.jumbo_emoji', 1);
});
