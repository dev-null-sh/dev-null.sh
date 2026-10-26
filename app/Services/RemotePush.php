<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PushSubscription;
use App\Rules\PushEndpoint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\VAPID;

final readonly class RemotePush
{
    public function send(PushSubscription $subscription): bool
    {
        // The endpoint was checked when it was saved, but a row can also come from a restored backup, a
        // backfill or a hand-written fix, and the allowlist itself can change. So it is checked again here,
        // and a row that no longer passes goes the way of one its provider answered 404 for.
        if (! $this->isSupported($subscription->endpoint)) {
            $subscription->delete();

            return true;
        }

        $auth = VAPID::validate([
            'subject' => Config::string('services.web_push.subject'),
            'publicKey' => Config::string('services.web_push.public_key'),
            'privateKey' => Config::string('services.web_push.private_key'),
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

    private function isSupported(string $endpoint): bool
    {
        return Validator::make(
            ['endpoint' => $endpoint],
            ['endpoint' => ['required', 'string', new PushEndpoint]],
        )->passes();
    }
}
