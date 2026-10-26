<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Message;
use App\Models\MessageImage;
use App\Models\MessageVoice;
use App\Models\User;
use App\Services\CodeSpans;
use App\Services\Mentions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A message as the web's thread shows it: its body, also split into prose, inline code and highlighted code blocks,
 * where it mentions people the message links to, the message it replies to only as much as its ghost shows, and when
 * they were loaded its link card, images, voice note and reactions. In an org it also says who wrote it and the event
 * it records. Which message it replies to is left out wherever the model hides it, as pinned messages do.
 *
 * The app's image views and audio players can't sign requests, so each image and voice note comes with a URL signed
 * for the user, good until midnight UTC the day after tomorrow, which stays the same all day so the app can cache it.
 *
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'client_id' => $this->client_id,
            'body' => $this->body,
            'body_segments' => resolve(CodeSpans::class)->highlight($this->body),
            'mentions' => collect(resolve(Mentions::class)->positions($this->body))
                ->filter(fn (array $mention): bool => array_key_exists(Str::lower($mention['username']), $this->mentions ?? []))
                ->values(),
            'reply_to_id' => $this->when(! in_array('reply_to_id', $this->getHidden(), true), $this->reply_to_id),
            'reply_to' => $this->whenLoaded('replyTo', fn (Message $replyTo): array => [
                'id' => $replyTo->id,
                'user_id' => $replyTo->user_id,
                'body' => $replyTo->body,
                'images_count' => $replyTo->images->count(),
                'has_voice' => $replyTo->voice instanceof MessageVoice,
            ]),
            'link_preview' => $this->whenLoaded('linkPreview'),
            'images' => $this->whenLoaded('images', fn (): Collection => $this->images->map(fn (MessageImage $image): array => [
                'id' => $image->id,
                'width' => $image->width,
                'height' => $image->height,
                'description' => $image->description,
                'url' => url(URL::temporarySignedRoute('api-message-image.show', today()->addDays(2), [
                    'messageImage' => $image,
                    'user' => $request->user()?->id,
                ], absolute: false)),
            ])),
            'voice' => $this->whenLoaded('voice', fn (MessageVoice $voice): array => [
                'id' => $voice->id,
                'duration_ms' => $voice->duration_ms,
                'waveform' => $voice->waveform,
                'url' => url(URL::temporarySignedRoute('api-message-voice.show', today()->addDays(2), [
                    'messageVoice' => $voice,
                    'user' => $request->user()?->id,
                ], absolute: false)),
            ]),
            'reactions' => $this->whenLoaded('reactions'),
            'event' => $this->event,
            'subject_id' => $this->subject_id,
            'user' => $this->whenLoaded('user', fn (User $user): UserResource => UserResource::make($user)),
            'subject' => $this->whenLoaded('subject', fn (User $subject): UserResource => UserResource::make($subject)),
            'pinned_at' => $this->pinned_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
