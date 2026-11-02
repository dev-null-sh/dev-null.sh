<?php

declare(strict_types=1);

namespace App\Services\PushNotificationProviders;

use App\Contracts\Services\PushNotificationProvider;
use App\Models\PushSubscription;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\VAPID;

final readonly class WebPushNotificationProvider implements PushNotificationProvider
{
    public function publicKey(): string
    {
        $publicKey = config('services.web_push.public_key');
        assert(is_string($publicKey));

        return $publicKey;
    }

    public function send(PushSubscription $subscription): bool
    {
        $subject = config('services.web_push.subject');
        assert(is_string($subject));

        $privateKey = config('services.web_push.private_key');
        assert(is_string($privateKey));

        $auth = VAPID::validate([
            'subject' => $subject,
            'publicKey' => $this->publicKey(),
            'privateKey' => $privateKey,
        ]);
        $endpoint = $subscription->endpoint;
        $headers = VAPID::getVapidHeaders(
            'https://'.parse_url($endpoint, PHP_URL_HOST),
            $auth['subject'], $auth['publicKey'], $auth['privateKey'], ContentEncoding::aes128gcm,
        );

        $windows = str_ends_with((string) parse_url($endpoint, PHP_URL_HOST), '.notify.windows.com');

        // An empty authenticated push needs no browser encryption secret or message data.
        try {
            $response = Http::withHeaders([...$headers, 'TTL' => '300', 'Topic' => 'devnull-remotes', ...$windows ? ['X-WNS-Type' => 'wns/raw'] : ['Urgency' => 'normal']])
                ->withOptions(['allow_redirects' => false])->timeout(10)->withBody('', 'application/octet-stream')->post($endpoint);
        } catch (ConnectionException) {
            return false;
        }

        if (in_array($response->status(), [404, 410], true)) {
            $subscription->delete();
        }

        return ! $response->serverError() && $response->status() !== 429;
    }
}
