<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\AnalyticsProvider;
use App\Contracts\Services\EmailDraftProvider;
use App\Contracts\Services\EmbeddingProvider;
use App\Contracts\Services\GitHubProvider;
use App\Contracts\Services\LinkPreviewProvider;
use App\Contracts\Services\ModerationProvider;
use App\Contracts\Services\PushNotificationProvider;
use App\Contracts\Services\RelevanceProvider;
use App\Contracts\Services\ToolDecisionProvider;
use App\Contracts\Services\TranslationProvider;
use App\Contracts\Services\TurnProvider;
use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\AnalyticsProviders\FathomAnalyticsProvider;
use App\Services\AnalyticsProviders\NullAnalyticsProvider;
use App\Services\CodeTheme;
use App\Services\EmailDraftProviders\NullEmailDraftProvider;
use App\Services\EmailDraftProviders\OpenCodeEmailDraftProvider;
use App\Services\EmbeddingProviders\NullEmbeddingProvider;
use App\Services\EmbeddingProviders\OpenAIEmbeddingProvider;
use App\Services\GitHubProviders\HttpGitHubProvider;
use App\Services\GitHubProviders\NullGitHubProvider;
use App\Services\LinkPreviewProviders\HttpLinkPreviewProvider;
use App\Services\LinkPreviewProviders\NullLinkPreviewProvider;
use App\Services\ModerationProviders\NullModerationProvider;
use App\Services\ModerationProviders\OpenAIModerationProvider;
use App\Services\PushNotificationProviders\NullPushNotificationProvider;
use App\Services\PushNotificationProviders\WebPushNotificationProvider;
use App\Services\RelevanceProviders\NullRelevanceProvider;
use App\Services\RelevanceProviders\OpenAIRelevanceProvider;
use App\Services\ToolDecisionProviders\NullToolDecisionProvider;
use App\Services\ToolDecisionProviders\OpenAIToolDecisionProvider;
use App\Services\TranslationProviders\NullTranslationProvider;
use App\Services\TranslationProviders\OpenCodeTranslationProvider;
use App\Services\TurnProviders\CloudflareTurnProvider;
use App\Services\TurnProviders\LocalTurnProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Tempest\Highlight\Highlighter;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * The services bound in every environment but local and testing.
     *
     * @var array<class-string, class-string>
     */
    private const array PRODUCTION_SERVICES = [
        AnalyticsProvider::class => FathomAnalyticsProvider::class,
        EmailDraftProvider::class => OpenCodeEmailDraftProvider::class,
        EmbeddingProvider::class => OpenAIEmbeddingProvider::class,
        GitHubProvider::class => HttpGitHubProvider::class,
        LinkPreviewProvider::class => HttpLinkPreviewProvider::class,
        ModerationProvider::class => OpenAIModerationProvider::class,
        PushNotificationProvider::class => WebPushNotificationProvider::class,
        RelevanceProvider::class => OpenAIRelevanceProvider::class,
        ToolDecisionProvider::class => OpenAIToolDecisionProvider::class,
        TranslationProvider::class => OpenCodeTranslationProvider::class,
        TurnProvider::class => CloudflareTurnProvider::class,
    ];

    /**
     * The services bound in the local and testing environments instead, so nothing there needs a third party.
     *
     * @var array<class-string, class-string>
     */
    private const array LOCAL_SERVICES = [
        AnalyticsProvider::class => NullAnalyticsProvider::class,
        EmailDraftProvider::class => NullEmailDraftProvider::class,
        EmbeddingProvider::class => NullEmbeddingProvider::class,
        GitHubProvider::class => NullGitHubProvider::class,
        LinkPreviewProvider::class => NullLinkPreviewProvider::class,
        ModerationProvider::class => NullModerationProvider::class,
        PushNotificationProvider::class => NullPushNotificationProvider::class,
        RelevanceProvider::class => NullRelevanceProvider::class,
        ToolDecisionProvider::class => NullToolDecisionProvider::class,
        TranslationProvider::class => NullTranslationProvider::class,
        TurnProvider::class => LocalTurnProvider::class,
    ];

    public function register(): void
    {
        $services = $this->app->environment('local', 'testing')
            ? self::LOCAL_SERVICES
            : self::PRODUCTION_SERVICES; // @codeCoverageIgnore

        foreach ($services as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }

        $this->app->bind(Highlighter::class, fn (): Highlighter => new Highlighter(new CodeTheme()));
    }

    public function boot(): void
    {
        $this->bootRateLimitingDefaults();

        $this->bootExceptionHandling();
    }

    private function bootExceptionHandling(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): ExceptionResponse|RedirectResponse|null {
            // API routes render their own errors (JSON, or plain text for the `devnull commit` script)...
            if ($response->request->expectsJson() || $response->request->is('api/*')) {
                return null;
            }

            if ($response->statusCode() === 419) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => 'Page expired: your session timed out before that went through. Try again.',
                ]);

                return back();
            }

            if ($response->statusCode() === 500 && config('app.debug')) {
                return null;
            }

            if (! in_array($response->statusCode(), [403, 404, 429, 500, 503], true)) {
                return null;
            }

            return $response->render('error', [
                'status' => $response->statusCode(),
            ])->withSharedData();
        });
    }

    private function bootRateLimitingDefaults(): void
    {
        // Each email costs us sender reputation, so a network gets a few an hour...
        RateLimiter::for('join-links', fn (Request $request): Limit => Limit::perHour(3)->by((string) $request->ip())->response(
            fn (): RedirectResponse => back()->withErrors(['email' => __('Too many links sent from here. Try again later.')]),
        ));

        RateLimiter::for('commit-reports', fn (Request $request): Limit => Limit::perMinute(5)->by($this->userId($request)));

        // Every sync costs four GitHub API requests, out of a shared hourly budget. The mobile app shares it, and gets the
        // same message with a code...
        RateLimiter::for('profile-syncs', function (Request $request): Limit {
            $user = $request->user();
            assert($user instanceof User);

            return Limit::perMinute(1)->by($user->id)->response(function (Request $request, array $headers): JsonResponse|RedirectResponse {
                if ($request->is('api/*')) {
                    return new ApiException(ApiErrorCode::RateLimited, __('You just synced. Try again in a minute.'), $headers)->render();
                }

                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => 'You just synced. Try again in a minute.',
                ]);

                return back();
            });
        });

        // The mobile app's 429 comes with a code. The public API's stays as it is...
        $rateLimited = fn (Request $request, array $headers): JsonResponse => new ApiException(ApiErrorCode::RateLimited, 'Too Many Attempts.', $headers)->render();

        RateLimiter::for('api', function (Request $request) use ($rateLimited): Limit {
            $user = $request->user();

            // Signed requests from the mobile app share one budget per user, whichever network the phone is on...
            return $user instanceof User
                ? Limit::perMinute(120)->by($user->id)->response($rateLimited)
                : Limit::perMinute(60)->by((string) $request->ip());
        });

        // Redeeming a code for a device is limited per key once the signature checks out. Before that, each address gets
        // a ceiling, so unsigned or badly signed requests can't force signature checks...
        RateLimiter::for('devices', fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip())->response($rateLimited));

        // Approving the devnull app on the web hands out a code each time...
        RateLimiter::for('mobile-authorizations', fn (Request $request): Limit => Limit::perMinute(10)->by($this->userId($request)));

        // GitHub fetches every README image through a few proxy addresses, so each graph gets its own budget...
        RateLimiter::for('activity-graph', fn (Request $request): Limit => Limit::perMinute(60)->by("{$request->ip()}|{$request->path()}"));

        // Commit actions: rapid j/k with a star on most commits stays well under these...
        RateLimiter::for('stars', fn (Request $request): Limit => Limit::perMinute(60)->by($this->userId($request)));
        RateLimiter::for('pins', fn (Request $request): Limit => Limit::perMinute(20)->by($this->userId($request)));

        // Messages: polling an open conversation every second or so stays well under the first...
        RateLimiter::for('conversations', fn (Request $request): Limit => Limit::perMinute(300)->by($this->userId($request)));
        RateLimiter::for('messages', fn (Request $request): Limit => Limit::perMinute(30)->by($this->userId($request)));

        // Pings: MSN wouldn't let you ping that often either...
        RateLimiter::for('pings', fn (Request $request): Limit => Limit::perMinute(3)
            ->by($this->userId($request))
            ->response(fn (Request $request, array $headers): JsonResponse => response()->json(
                ['message' => 'Three pings a minute, tops. Try again in a bit.'],
                429,
                $headers,
            )));

        // Social graph...
        RateLimiter::for('follows', fn (Request $request): Limit => Limit::perMinute(30)->by($this->userId($request)));

        // People search, mention suggestions, and hover cards share one bucket...
        RateLimiter::for('people', fn (Request $request): Limit => Limit::perMinute(120)->by(
            $request->user() instanceof User ? $this->userId($request) : 'ip:'.$request->ip(),
        ));

        // Every issue lookup GitHub hasn't answered in the last five minutes costs a request out of the shared hourly budget,
        // which also keeps SSH keys in sync for the CLI, so it gets a budget of its own...
        RateLimiter::for('issue-suggestions', fn (Request $request): array => [
            Limit::perMinute(30)->by($this->userId($request)),
            Limit::perDay(300)->by('day:'.$this->userId($request)),
        ]);

        // Every commit search pays for an embedding and a relevance verdict, so it gets a budget of its own...
        RateLimiter::for('search', fn (Request $request): Limit => Limit::perMinute(15)
            ->by($this->userId($request))
            ->response(fn (Request $request, array $headers): JsonResponse => response()->json(
                ['message' => 'Slow down: too many searches. Give it a few seconds.'],
                429,
                $headers,
            )));

        // Every commit page asks for its related commits, using the stored vector and a relevance verdict...
        RateLimiter::for('related-commits', fn (Request $request): Limit => Limit::perMinute(60)->by(
            $request->user() instanceof User ? $this->userId($request) : 'ip:'.$request->ip(),
        ));

        // Exports stream every commit a user ever made...
        RateLimiter::for('exports', fn (Request $request): Limit => Limit::perHour(3)->by($this->userId($request)));
    }

    private function userId(Request $request): string
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user->id;
    }
}
