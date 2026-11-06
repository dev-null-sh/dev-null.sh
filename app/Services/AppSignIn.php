<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Signs in the web view of the iOS app POC (a Capacitor shell around the site).
 *
 * Temporary: part of the proof of concept of a mobile app wrapping the site, to be reverted.
 *
 * The app signs in with GitHub in the system's authentication sheet rather than in its web view: the sheet shares
 * Safari's cookies, so GitHub is often already signed in there, with passkeys and saved passwords. That sheet is
 * another browser, though: once GitHub calls back, the user is handed back to the app with a one-time code instead of
 * being signed in there. The app redeems the code in its web view with the verifier only it knows, PKCE-style, so a
 * code that leaks is useless.
 */
final readonly class AppSignIn
{
    /** Where the sheet hands the code back: the app watches for this scheme, it is not registered with the system. */
    public const string CALLBACK = 'devnull://auth';

    private const string SESSION_KEY = 'app_sign_in_challenge';

    private const int EXPIRES_AFTER_MINUTES = 5;

    public static function challengeFor(string $verifier): string
    {
        return mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * Remembers that the sign-in started in the sheet belongs to the app.
     */
    public function start(Request $request, string $challenge): void
    {
        $request->session()->put(self::SESSION_KEY, $challenge);
    }

    /**
     * The URL that hands the user back to the app, when the app started this sign-in. It's handed out once: signing in
     * again in this browser signs in this browser.
     */
    public function handOff(Request $request, User $user, bool $signedUp = false): ?string
    {
        $challenge = $request->session()->pull(self::SESSION_KEY);

        return is_string($challenge) ? $this->callbackUrl($user, $challenge, $signedUp) : null;
    }

    /**
     * The URL that hands a user already signed in to this browser back to the app.
     */
    public function callbackUrl(User $user, string $challenge, bool $signedUp = false): string
    {
        $code = bin2hex(random_bytes(32));

        Cache::put($this->cacheKey($code), [
            'user_id' => $user->id,
            'challenge' => $challenge,
            'signed_up' => $signedUp,
        ], now()->addMinutes(self::EXPIRES_AFTER_MINUTES));

        return self::CALLBACK.'?'.http_build_query(['code' => $code]);
    }

    /**
     * The user a code signs in, with whether they just signed up, once: a code that doesn't match its verifier is spent
     * all the same.
     *
     * @return array{User, bool}|null
     */
    public function redeem(string $code, string $verifier): ?array
    {
        $payload = Cache::pull($this->cacheKey($code));

        if (! is_array($payload) || ! is_string($payload['challenge'] ?? null) || ! hash_equals($payload['challenge'], self::challengeFor($verifier))) {
            return null;
        }

        $user = User::query()->find($payload['user_id']);

        return $user instanceof User ? [$user, (bool) $payload['signed_up']] : null;
    }

    private function cacheKey(string $code): string
    {
        return 'app-sign-in:'.hash('sha256', $code);
    }
}
