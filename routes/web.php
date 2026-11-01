<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\AppController;
use App\Http\Controllers\BanController;
use App\Http\Controllers\BannedController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BulkActivityController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\Cli\ScriptController;
use App\Http\Controllers\CommitApprovalController;
use App\Http\Controllers\CommitContinuationController;
use App\Http\Controllers\CommitController;
use App\Http\Controllers\CommitImageController;
use App\Http\Controllers\CommitLinkPreviewController;
use App\Http\Controllers\CommitOpenGraphImageController;
use App\Http\Controllers\CommitPastelController;
use App\Http\Controllers\CommitPhotoController;
use App\Http\Controllers\CommitPinController;
use App\Http\Controllers\CommitPollVoteController;
use App\Http\Controllers\CommitPreviewController;
use App\Http\Controllers\CommitRejectionController;
use App\Http\Controllers\CommitReplyController;
use App\Http\Controllers\CommitReportController;
use App\Http\Controllers\CommitReportImageController;
use App\Http\Controllers\CommitRestorationController;
use App\Http\Controllers\CommitSearchController;
use App\Http\Controllers\CommitSitemapController;
use App\Http\Controllers\CommitStarController;
use App\Http\Controllers\CommitStarCountController;
use App\Http\Controllers\CommitStatusController;
use App\Http\Controllers\CommitTranslationController;
use App\Http\Controllers\ConversationArchiveController;
use App\Http\Controllers\ConversationCallController;
use App\Http\Controllers\ConversationCallIceServerController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationDropController;
use App\Http\Controllers\ConversationMergeController;
use App\Http\Controllers\ConversationMessageController;
use App\Http\Controllers\ConversationMuteController;
use App\Http\Controllers\ConversationPinController;
use App\Http\Controllers\ConversationPingController;
use App\Http\Controllers\ConversationReadController;
use App\Http\Controllers\ConversationTypingController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\EmailDraftController;
use App\Http\Controllers\EmailPreviewController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FailedJobController;
use App\Http\Controllers\FailedJobRetryController;
use App\Http\Controllers\FailedJobsController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\IssueSuggestionController;
use App\Http\Controllers\JoinLinkController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\LocalSessionController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MentionController;
use App\Http\Controllers\MentionSuggestionController;
use App\Http\Controllers\MessageImageController;
use App\Http\Controllers\MessageLinkPreviewController;
use App\Http\Controllers\MessagePinController;
use App\Http\Controllers\MessageReactionController;
use App\Http\Controllers\MessageVoiceController;
use App\Http\Controllers\MobileAuthorizationController;
use App\Http\Controllers\ModeratedUserController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationGroupController;
use App\Http\Controllers\NotificationReadAtController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OperationsEventController;
use App\Http\Controllers\OrganizationAdminController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationFollowController;
use App\Http\Controllers\OrganizationLaneController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\OrganizationMentionController;
use App\Http\Controllers\OrganizationProfileController;
use App\Http\Controllers\OrganizationSitemapController;
use App\Http\Controllers\OrgArchiveController;
use App\Http\Controllers\OrgCallController;
use App\Http\Controllers\OrgCallIceServerController;
use App\Http\Controllers\OrgController;
use App\Http\Controllers\OrgMemberController;
use App\Http\Controllers\OrgMessageController;
use App\Http\Controllers\OrgMuteController;
use App\Http\Controllers\OrgPinController;
use App\Http\Controllers\OrgPingController;
use App\Http\Controllers\OrgReadController;
use App\Http\Controllers\OrgTypingController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\ProductMetricsController;
use App\Http\Controllers\ProfileOpenGraphImageController;
use App\Http\Controllers\ProfileSearchController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RelatedCommitController;
use App\Http\Controllers\RemoteCandidateController;
use App\Http\Controllers\ReportedCommitController;
use App\Http\Controllers\SafetyController;
use App\Http\Controllers\ScheduledCommitController;
use App\Http\Controllers\ScheduledCommitSubmissionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SiteActivityController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StarredCommitController;
use App\Http\Controllers\SuggestionDismissalController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\TestEmailController;
use App\Http\Controllers\TrafficController;
use App\Http\Controllers\UnsubscriptionController;
use App\Http\Controllers\UserActivityGraphController;
use App\Http\Controllers\UserAutomaticTranslationController;
use App\Http\Controllers\UserBanController;
use App\Http\Controllers\UserBlockController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserFeedController;
use App\Http\Controllers\UserFollowController;
use App\Http\Controllers\UserFollowersController;
use App\Http\Controllers\UserFollowersYouKnowController;
use App\Http\Controllers\UserFollowingController;
use App\Http\Controllers\UserLaneController;
use App\Http\Controllers\UserLiveController;
use App\Http\Controllers\UserMuteController;
use App\Http\Controllers\UserMutualsController;
use App\Http\Controllers\UserPasteisModeController;
use App\Http\Controllers\UserPreviewController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserRemotePreferencesController;
use App\Http\Controllers\UserRemotesController;
use App\Http\Controllers\UserSitemapController;
use App\Http\Controllers\UserTermsController;
use App\Http\Controllers\UserTimeoutController;
use App\Http\Controllers\UserTimezoneController;
use App\Http\Controllers\UserTranslationLanguageController;
use App\Http\Middleware\PreventPostingInTimeout;
use App\Http\Middleware\RedirectToHandleCase;
use App\Http\Middleware\RememberIntendedUrl;
use App\Http\Middleware\RequireCommitRules;
use App\Http\Middleware\SerializePushSessions;
use App\Http\Middleware\SerializePushSubscriptions;
use App\Http\Middleware\ThrottleCommits;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Pipeline;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

// Commit from the terminal: curl -fsSL https://dev-null.sh/commit | bash -s -- 'Hello world!'
Route::get('commit', [ScriptController::class, 'show'])->name('cli-script.show');

Route::middleware('guest')->group(function (): void {
    // Guests land straight on the Trending log...
    Route::get('/', [LogController::class, 'index'])->name('home');

    // Session...
    if (app()->isLocal()) {
        // Locally, signing in asks for any handle in a modal, without GitHub...
        Route::get('login', [LocalSessionController::class, 'create'])->name('login');
        Route::post('login/local', [LocalSessionController::class, 'store'])->name('local-login.store');
    } else {
        Route::get('login', [SessionController::class, 'create'])
            ->middleware('throttle:10,1')
            ->name('login');
    }

    Route::get('auth/github/callback', [SessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    // A link by email, for a guest on a phone who'd rather join from their computer...
    Route::post('join-links', [JoinLinkController::class, 'store'])
        ->middleware('throttle:join-links')
        ->name('join-link.store');
});

// Log, where guests read Trending and Latest...
Route::get('log/everyone', fn () => to_route('log.show', Log::Trending, 301));
Route::get('log/{log}', [LogController::class, 'show'])->name('log.show');

// Terms and privacy, readable by everyone...
Route::get('terms', [TermsController::class, 'show'])->name('terms.show');
Route::get('privacy', [PrivacyController::class, 'show'])->name('privacy.show');

// Unsubscribing from the admins' emails, from the signed link in each one...
Route::get('emails/unsubscribe/{user}', [UnsubscriptionController::class, 'show'])->whereUuid('user')->name('unsubscription.show');
Route::post('emails/unsubscribe/{user}', [UnsubscriptionController::class, 'store'])
    ->whereUuid('user')
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('unsubscription.store');

// How /dev/null was built, readable by everyone...
Route::get('about', [AboutController::class, 'show'])->name('about.show');

// Who sponsors /dev/null, readable by everyone...
Route::get('partners', [PartnerController::class, 'index'])->name('partner.index');

// Logos, colors, and backgrounds, downloadable by everyone...
Route::get('brand', [BrandController::class, 'show'])->name('brand.show');

// The apps, downloadable by everyone, where the Mac app's first page used to be too...
Route::get('apps', [AppController::class, 'index'])->name('app.index');
Route::get('mac', fn () => to_route('app.index', status: 301));

// What was merged into production, readable by everyone...
Route::get('changelog', [ChangelogController::class, 'index'])->name('changelog.index');

// Public pages, readable by guests and crawlers...
Route::middleware([RememberIntendedUrl::class, RedirectToHandleCase::class])->group(function (): void {
    // A handle no user has may be an organization's. Bindings run before the rest of the route's middleware, like
    // the one sharing the page's props, so the organization's page goes through that rest first, like any page.
    Route::get('@{user:handle}', [UserController::class, 'show'])
        ->missing(function (Request $request): mixed {
            $router = resolve(Router::class);

            return new Pipeline(app())
                ->send($request)
                ->through(collect($router->gatherRouteMiddleware(resolve(RoutingRoute::class)))->skipUntil(SubstituteBindings::class)->skip(1)->all())
                ->then(fn (Request $request): SymfonyResponse => $router->prepareResponse($request, app()->call(resolve(OrganizationController::class)->show(...))));
        })
        ->name('user.show');
    // Who follows whom is for members, so scrapers can't walk the graph page by page...
    Route::middleware('auth')->group(function (): void {
        Route::get('@{user:handle}/following', [UserFollowingController::class, 'index'])
            ->name('user-following.index');
        Route::get('@{user:handle}/followers', [UserFollowersController::class, 'index'])
            ->name('user-followers.index');
        Route::get('@{user:handle}/mutuals', [UserMutualsController::class, 'index'])
            ->name('user-mutuals.index');
    });
    Route::get('@{profile:handle}/commits/{commit}', [CommitController::class, 'show'])
        ->scopeBindings()
        ->name('commit.show');
    Route::get('@{profile:handle}/commits/{commit}/photos/{photo}', [CommitPhotoController::class, 'show'])
        ->scopeBindings()
        ->whereNumber('photo')
        ->name('commit-photo.show');
});

// Sitemaps, the same for everyone, so they skip the web group's session and cookies, which would keep a cache from sharing them...
Route::withoutMiddleware('web')->middleware('cache.headers:public;max_age=3600;etag')->group(function (): void {
    Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
    Route::get('sitemaps/users-{month}.xml', [UserSitemapController::class, 'show'])
        ->where('month', '[0-9]{4}-[0-9]{2}')
        ->name('user-sitemap.show');
    Route::get('sitemaps/organizations-{month}.xml', [OrganizationSitemapController::class, 'show'])
        ->where('month', '[0-9]{4}-[0-9]{2}')
        ->name('organization-sitemap.show');
    Route::get('sitemaps/commits-{day}.xml', [CommitSitemapController::class, 'show'])
        ->where('day', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
        ->name('commit-sitemap.show');
});

// User...
Route::get('mentions/{user}', [MentionController::class, 'show'])
    ->whereUuid('user')
    ->name('mention.show');
Route::get('mentions/organizations/{organization}', [OrganizationMentionController::class, 'show'])
    ->whereUuid('organization')
    ->name('organization-mention.show');
Route::get('mentions/{user}/preview', [UserPreviewController::class, 'show'])
    ->middleware('throttle:people')
    ->whereUuid('user')
    ->name('mention.preview');
Route::get('@{user:handle}/preview', [UserPreviewController::class, 'show'])
    ->middleware('throttle:people')
    ->name('user-preview.show');
Route::get('@{profile:handle}/og.png', [ProfileOpenGraphImageController::class, 'show'])
    ->name('user-open-graph-image.show');
Route::get('@{user:handle}/feed.atom', [UserFeedController::class, 'show'])
    ->middleware('cache.headers:public;max_age=300;etag')
    ->name('user-feed.show');

// User activity graph, embedded in READMEs...
Route::get('@{user:handle}/graph.svg', [UserActivityGraphController::class, 'show'])
    ->middleware(['throttle:activity-graph', 'cache.headers:public;max_age=3600;etag'])
    ->name('user-activity-graph.show');

// Commit...
Route::get('@{profile:handle}/commits/{commit}/og.png', [CommitOpenGraphImageController::class, 'show'])
    ->scopeBindings()
    ->name('commit-open-graph-image.show');
Route::get('commit-images/{commitImage}', [CommitImageController::class, 'show'])
    ->whereUuid('commitImage')
    ->name('commit-image.show');
Route::get('commits/{commit}/link-preview', [CommitLinkPreviewController::class, 'show'])
    ->name('commit-link-preview.show');
Route::get('commits/{commit}/status', [CommitStatusController::class, 'show'])
    ->name('commit-status.show');
Route::get('commits/{commit}/replies', [CommitReplyController::class, 'index'])
    ->withTrashed()
    ->name('commit-reply.index');
Route::get('commits/{commit}/continuation', [CommitContinuationController::class, 'index'])
    ->name('commit-continuation.index');
Route::get('commits/{commit}/related', [RelatedCommitController::class, 'index'])
    ->middleware('throttle:related-commits')
    ->name('related-commit.index');

// Search: commits by meaning are for members, as each search pays for AI that bots could run up...
Route::get('search', [SearchController::class, 'index'])
    ->middleware('auth')
    ->name('search.index');

// Profile Search: developers and organizations by name or handle...
Route::get('search/profiles', [ProfileSearchController::class, 'index'])
    ->middleware('throttle:people')
    ->name('profile-search.index');

// Commit Search...
Route::get('search/commits', [CommitSearchController::class, 'index'])
    ->middleware(['auth', 'throttle:search'])
    ->name('commit-search.index');

Route::middleware('auth')->group(function (): void {
    Route::post('issues', [IssueController::class, 'store'])
        ->middleware([PreventPostingInTimeout::class, 'throttle:10,1'])
        ->name('issue.store');

    // Session...
    Route::get('push-subscriptions', [PushSubscriptionController::class, 'index'])->name('push-subscription.index');
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->middleware(['throttle:30,1', SerializePushSessions::class, SerializePushSubscriptions::class])->name('push-subscription.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->middleware(SerializePushSessions::class)->name('push-subscription.destroy');

    Route::post('logout', [SessionController::class, 'destroy'])
        ->middleware(SerializePushSessions::class)
        ->name('logout');

    Route::put('terms', [UserTermsController::class, 'update'])->name('user-terms.update');

    // Banned, where a banned user is sent from everywhere else...
    Route::get('banned', [BannedController::class, 'show'])->name('banned.show');

    // Claiming an organization's page, which GitHub confirms the user belongs to...
    Route::post('organizations/{organization}/claim', [OrganizationAdminController::class, 'store'])
        ->whereUuid('organization')
        ->middleware('throttle:10,1')
        ->name('organization-admin.store');

    // Organization Members, which admins manage in the organization's settings...
    Route::get('organizations/{organization}/settings/members', [OrganizationMemberController::class, 'index'])
        ->whereUuid('organization')
        ->name('organization-member.index');
    Route::post('organizations/{organization}/settings/members', [OrganizationMemberController::class, 'store'])
        ->whereUuid('organization')
        ->middleware('throttle:30,1')
        ->name('organization-member.store');
    Route::put('organizations/{organization}/settings/members/{member:handle}', [OrganizationMemberController::class, 'update'])
        ->whereUuid('organization')
        ->withoutScopedBindings()
        ->name('organization-member.update');
    Route::delete('organizations/{organization}/settings/members/{member:handle}', [OrganizationMemberController::class, 'destroy'])
        ->whereUuid('organization')
        ->withoutScopedBindings()
        ->name('organization-member.destroy');

    // An organization's profile, which its admins edit in its settings, starting with its color...
    Route::get('organizations/{organization}/settings/profile', [OrganizationProfileController::class, 'edit'])
        ->whereUuid('organization')
        ->name('organization-profile.edit');
    Route::put('organizations/{organization}/settings/lane', [OrganizationLaneController::class, 'update'])
        ->whereUuid('organization')
        ->name('organization-lane.update');

    // Followers the viewer knows...
    Route::get('@{user:handle}/followers-you-know', [UserFollowersYouKnowController::class, 'index'])
        ->name('user-followers-you-know.index');

    // Onboarding...
    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::put('onboarding', [OnboardingController::class, 'update'])->name('onboarding.update');

    // Private starred commits...
    Route::get('starred', [StarredCommitController::class, 'index'])->name('starred.index');

    // Every commit about a link, a prototype only reachable by its URL...
    Route::get('links', [LinkController::class, 'index'])->name('link.index');

    // Commit...
    Route::get('scheduled-commits', [ScheduledCommitController::class, 'index'])->name('scheduled-commit.index');
    Route::post('scheduled-commits', [ScheduledCommitController::class, 'store'])
        ->middleware([PreventPostingInTimeout::class, RequireCommitRules::class, ThrottleCommits::class])->name('scheduled-commit.store');
    Route::delete('scheduled-commits/{scheduledCommit}', [ScheduledCommitController::class, 'destroy'])->name('scheduled-commit.destroy');
    Route::post('scheduled-commits/{scheduledCommit}/submission', [ScheduledCommitSubmissionController::class, 'store'])
        ->middleware([PreventPostingInTimeout::class, ThrottleCommits::class])->name('scheduled-commit-submission.store');

    Route::get('commit-stars', [CommitStarCountController::class, 'index'])->name('commit-star-count.index');
    Route::post('commits', [CommitController::class, 'store'])
        ->middleware([PreventPostingInTimeout::class, RequireCommitRules::class, ThrottleCommits::class])
        ->name('commit.store');
    Route::post('commits/preview', [CommitPreviewController::class, 'store'])
        ->name('commit-preview.store');
    Route::post('commits/{commit}/translation', [CommitTranslationController::class, 'store'])
        ->middleware('throttle:10,1,translations:')
        ->name('commit-translation.store');
    Route::delete('commits/{commit}', [CommitController::class, 'destroy'])
        ->name('commit.destroy');
    Route::post('commits/{commit}/restoration', [CommitRestorationController::class, 'store'])
        ->withTrashed()
        ->name('commit-restoration.store');
    Route::put('commits/{commit}/pin', [CommitPinController::class, 'update'])
        ->middleware('throttle:pins')
        ->name('commit-pin.update');
    Route::delete('commits/{commit}/pin', [CommitPinController::class, 'destroy'])
        ->middleware('throttle:pins')
        ->name('commit-pin.destroy');
    Route::get('commits/{commit}/stars', [CommitStarController::class, 'index'])
        ->name('commit-star.index');
    Route::post('commits/{commit}/star', [CommitStarController::class, 'store'])
        ->middleware('throttle:stars')
        ->name('commit-star.store');
    Route::post('commits/{commit}/pasteis', [CommitPastelController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('commit-pastel.store');
    Route::post('commits/{commit}/reports', [CommitReportController::class, 'store'])
        ->middleware('throttle:commit-reports')
        ->name('commit-report.store');
    Route::delete('commits/{commit}/star', [CommitStarController::class, 'destroy'])
        ->middleware('throttle:stars')
        ->name('commit-star.destroy');
    Route::post('commits/{commit}/replies', [CommitReplyController::class, 'store'])
        ->middleware([PreventPostingInTimeout::class, RequireCommitRules::class, ThrottleCommits::class])
        ->name('commit-reply.store');
    Route::delete('commits/{commit}/replies/{reply}', [CommitReplyController::class, 'destroy'])
        ->withTrashed()
        ->name('commit-reply.destroy');
    Route::post('commits/{commit}/poll/votes', [CommitPollVoteController::class, 'store'])
        ->name('commit-poll-vote.store');

    // Notifications...
    Route::get('notifications', [NotificationController::class, 'index'])->name('notification.index');
    Route::put('notifications/read-at', [NotificationReadAtController::class, 'update'])->name('notification-read-at.update');
    Route::get('notifications/{notification}/group', [NotificationGroupController::class, 'show'])->name('notification-group.show');

    // Messages...
    Route::get('remotes', [ConversationController::class, 'index'])->name('conversation.index');
    Route::get('remotes/@{partner:handle}', [ConversationController::class, 'show'])->name('conversation.show');
    Route::get('remotes/orgs/{org}', [OrgController::class, 'show'])->whereUuid('org')->name('org.show');
    Route::get('remotes/images/{messageImage}', [MessageImageController::class, 'show'])->whereUuid('messageImage')->name('message-image.show');
    Route::get('remotes/voices/{messageVoice}', [MessageVoiceController::class, 'show'])->whereUuid('messageVoice')->name('message-voice.show');

    Route::middleware('throttle:conversations')->group(function (): void {
        Route::get('remotes/inbox', [InboxController::class, 'index'])->name('inbox.index');
        Route::get('remotes/@{partner:handle}/messages', [ConversationMessageController::class, 'index'])
            ->name('conversation-message.index');
        Route::put('remotes/@{partner:handle}/read', [ConversationReadController::class, 'update'])
            ->name('conversation-read.update');
        Route::put('remotes/@{partner:handle}/typing', [ConversationTypingController::class, 'update'])
            ->name('conversation-typing.update');
        Route::get('remotes/call', [CallController::class, 'show'])->name('call.show');
        Route::get('remotes/@{partner:handle}/call', [ConversationCallController::class, 'show'])
            ->name('conversation-call.show');
        Route::put('remotes/@{partner:handle}/call', [ConversationCallController::class, 'update'])
            ->name('conversation-call.update');
        Route::delete('remotes/@{partner:handle}/call', [ConversationCallController::class, 'destroy'])
            ->name('conversation-call.destroy');
        Route::post('remotes/@{partner:handle}/merge', [ConversationMergeController::class, 'store'])
            ->name('conversation-merge.store');
        Route::post('remotes/@{partner:handle}/drop', [ConversationDropController::class, 'store'])
            ->name('conversation-drop.store');
        Route::put('remotes/preferences', [UserRemotePreferencesController::class, 'update'])
            ->name('user-remote-preferences.update');
        Route::get('remotes/candidates', [RemoteCandidateController::class, 'index'])->name('remote-candidate.index');
        Route::post('remotes/orgs', [OrgController::class, 'store'])->middleware([PreventPostingInTimeout::class, 'throttle:messages'])->name('org.store');
        Route::put('remotes/orgs/{org}', [OrgController::class, 'update'])->whereUuid('org')->middleware(PreventPostingInTimeout::class)->name('org.update');
        Route::put('remotes/orgs/{org}/call', [OrgCallController::class, 'update'])->whereUuid('org')->name('org-call.update');
        Route::delete('remotes/orgs/{org}/call', [OrgCallController::class, 'destroy'])->whereUuid('org')->name('org-call.destroy');
        Route::get('remotes/orgs/{org}/messages', [OrgMessageController::class, 'index'])->whereUuid('org')->name('org-message.index');
        Route::put('remotes/orgs/{org}/read', [OrgReadController::class, 'update'])->whereUuid('org')->name('org-read.update');
        Route::put('remotes/orgs/{org}/typing', [OrgTypingController::class, 'update'])->whereUuid('org')->name('org-typing.update');
        Route::post('remotes/orgs/{org}/archive', [OrgArchiveController::class, 'store'])->whereUuid('org')->name('org-archive.store');
        Route::delete('remotes/orgs/{org}/archive', [OrgArchiveController::class, 'destroy'])->whereUuid('org')->name('org-archive.destroy');
        Route::put('remotes/orgs/{org}/mute', [OrgMuteController::class, 'update'])->whereUuid('org')->name('org-mute.update');
        Route::put('remotes/orgs/{org}/pin', [OrgPinController::class, 'update'])->whereUuid('org')->name('org-pin.update');
        Route::post('remotes/orgs/{org}/members', [OrgMemberController::class, 'store'])->whereUuid('org')->middleware([PreventPostingInTimeout::class, 'throttle:messages'])->name('org-member.store');
        Route::put('remotes/orgs/{org}/members/{member:handle}', [OrgMemberController::class, 'update'])->whereUuid('org')->withoutScopedBindings()->name('org-member.update');
        Route::delete('remotes/orgs/{org}/members/{member:handle}', [OrgMemberController::class, 'destroy'])->whereUuid('org')->withoutScopedBindings()->name('org-member.destroy');
        Route::post('remotes/@{partner:handle}/archive', [ConversationArchiveController::class, 'store'])
            ->name('conversation-archive.store');
        Route::delete('remotes/@{partner:handle}/archive', [ConversationArchiveController::class, 'destroy'])
            ->name('conversation-archive.destroy');
        Route::put('remotes/@{partner:handle}/mute', [ConversationMuteController::class, 'update'])
            ->name('conversation-mute.update');
        Route::put('remotes/@{partner:handle}/pin', [ConversationPinController::class, 'update'])
            ->name('conversation-pin.update');
        Route::get('remotes/messages/{message}/link-preview', [MessageLinkPreviewController::class, 'show'])
            ->whereNumber('message')
            ->name('message-link-preview.show');
        Route::get('remotes/reactions', [MessageReactionController::class, 'index'])->name('message-reaction.index');
        Route::post('remotes/messages/{message}/reactions', [MessageReactionController::class, 'store'])
            ->whereNumber('message')
            ->name('message-reaction.store');
        Route::delete('remotes/messages/{message}/reactions', [MessageReactionController::class, 'destroy'])
            ->whereNumber('message')
            ->name('message-reaction.destroy');
    });

    Route::put('remotes/messages/{message}/pin', [MessagePinController::class, 'update'])
        ->whereNumber('message')
        ->middleware([PreventPostingInTimeout::class, 'throttle:pins'])
        ->name('message-pin.update');
    Route::delete('remotes/messages/{message}/pin', [MessagePinController::class, 'destroy'])
        ->whereNumber('message')
        ->middleware('throttle:pins')
        ->name('message-pin.destroy');

    Route::post('remotes/@{partner:handle}/messages', [ConversationMessageController::class, 'store'])
        ->middleware([PreventPostingInTimeout::class, 'throttle:messages'])
        ->name('conversation-message.store');
    Route::get('remotes/@{partner:handle}/call/ice-servers', [ConversationCallIceServerController::class, 'index'])
        ->middleware('throttle:messages')
        ->name('conversation-call-ice-server.index');
    Route::post('remotes/@{partner:handle}/call', [ConversationCallController::class, 'store'])
        ->middleware([PreventPostingInTimeout::class, 'throttle:messages'])
        ->name('conversation-call.store');
    Route::post('remotes/@{partner:handle}/ping', [ConversationPingController::class, 'store'])
        ->middleware([PreventPostingInTimeout::class, 'throttle:pings'])
        ->name('conversation-ping.store');
    Route::get('remotes/orgs/{org}/call/ice-servers', [OrgCallIceServerController::class, 'index'])
        ->whereUuid('org')
        ->middleware('throttle:messages')
        ->name('org-call-ice-server.index');
    Route::post('remotes/orgs/{org}/call', [OrgCallController::class, 'store'])
        ->whereUuid('org')
        ->middleware([PreventPostingInTimeout::class, 'throttle:messages'])
        ->name('org-call.store');
    Route::post('remotes/orgs/{org}/ping', [OrgPingController::class, 'store'])
        ->whereUuid('org')
        ->middleware([PreventPostingInTimeout::class, 'throttle:pings'])
        ->name('org-ping.store');
    Route::post('remotes/orgs/{org}/messages', [OrgMessageController::class, 'store'])
        ->whereUuid('org')
        ->middleware([PreventPostingInTimeout::class, 'throttle:messages'])
        ->name('org-message.store');

    // Moderation...
    Route::get('moderation', [ModerationController::class, 'show'])->name('moderation.show');
    Route::get('moderation/reports/{report}/images/{image}', [CommitReportImageController::class, 'show'])->whereUuid('report')->whereIn('image', ['0', '1', '2'])->name('commit-report-image.show');
    Route::get('moderation/reports', [ReportedCommitController::class, 'index'])->name('reported-commit.index');
    Route::delete('moderation/reports/{report}', [CommitReportController::class, 'destroy'])->name('commit-report.destroy');
    Route::post('moderation/{commit}/approval', [CommitApprovalController::class, 'store'])
        ->name('commit-approval.store');
    Route::post('moderation/{commit}/rejection', [CommitRejectionController::class, 'store'])
        ->name('commit-rejection.store');
    Route::put('moderation', [ModerationController::class, 'update'])->name('moderation.update');
    Route::get('moderation/people', [ModeratedUserController::class, 'index'])->name('moderated-user.index');
    Route::get('moderation/bulk', [BulkActivityController::class, 'index'])->name('bulk-activity.index');
    Route::delete('moderation/bans/{ban}', [BanController::class, 'destroy'])->whereUuid('ban')->name('ban.destroy');

    // Operations...
    Route::get('operations', [OperationsController::class, 'show'])->name('operations.show');
    Route::get('operations/health', [SystemHealthController::class, 'show'])->name('system-health.show');
    Route::get('operations/events', [OperationsEventController::class, 'index'])->name('operations-event.index');
    Route::get('operations/forecasts', [ForecastController::class, 'show'])->name('forecast.show');
    Route::get('operations/features', [FeatureController::class, 'index'])->name('feature.index');
    Route::get('operations/activity', [SiteActivityController::class, 'show'])->name('site-activity.show');
    Route::get('operations/traffic', [TrafficController::class, 'show'])->name('traffic.show');
    Route::get('operations/metrics', [ProductMetricsController::class, 'show'])->name('product-metrics.show');
    Route::post('operations/failed-jobs/{failedJob}/retry', [FailedJobRetryController::class, 'store'])
        ->whereUuid('failedJob')
        ->name('failed-job-retry.store');
    Route::delete('operations/failed-jobs', [FailedJobsController::class, 'destroy'])->name('failed-jobs.destroy');
    Route::delete('operations/failed-jobs/{failedJob}', [FailedJobController::class, 'destroy'])
        ->whereUuid('failedJob')
        ->name('failed-job.destroy');
    Route::put('operations/features/{feature}', [FeatureController::class, 'update'])->name('feature.update');
    Route::get('operations/emails', [EmailController::class, 'create'])->name('email.create');
    Route::post('operations/emails', [EmailController::class, 'store'])->name('email.store');
    Route::post('operations/emails/test', [TestEmailController::class, 'store'])->name('test-email.store');
    Route::post('operations/emails/preview', [EmailPreviewController::class, 'store'])->name('email-preview.store');
    Route::post('operations/emails/draft', [EmailDraftController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('email-draft.store');

    // Mention Suggestions...
    Route::get('search/mentions', [MentionSuggestionController::class, 'index'])
        ->middleware('throttle:people')
        ->name('mention-suggestion.index');

    Route::get('search/issues', [IssueSuggestionController::class, 'index'])
        ->middleware('throttle:issue-suggestions')
        ->name('issue-suggestion.index');

    // User Profile...
    Route::get('settings', fn () => to_route('user-profile.edit'));
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');
    Route::put('settings/profile', [UserProfileController::class, 'update'])
        ->middleware('throttle:profile-syncs')
        ->name('user-profile.update');
    Route::get('settings/export', ExportController::class)
        ->middleware(['throttle:exports', 'throttle:5,1'])
        ->name('export');

    // User Lane...
    Route::put('settings/lane', [UserLaneController::class, 'update'])->name('user-lane.update');

    // User Timezone...
    Route::put('settings/timezone', [UserTimezoneController::class, 'update'])->name('user-timezone.update');

    Route::put('settings/automatic-translation', [UserAutomaticTranslationController::class, 'update'])->name('user-automatic-translation.update');
    Route::put('settings/translation-language', [UserTranslationLanguageController::class, 'update'])->name('user-translation-language.update');

    Route::put('settings/pasteis-mode', [UserPasteisModeController::class, 'update'])->name('user-pasteis-mode.update');

    Route::put('settings/remotes', [UserRemotesController::class, 'update'])->name('user-remotes.update');

    // User Live...
    Route::put('settings/live', [UserLiveController::class, 'update'])
        ->middleware(PreventPostingInTimeout::class)
        ->name('user-live.update');
    Route::delete('settings/live', [UserLiveController::class, 'destroy'])->name('user-live.destroy');

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    // Terminal...
    Route::get('settings/terminal', fn () => Inertia::render('terminal/show', ['url' => url('/')]))->name('terminal.show');

    // Safety...
    Route::get('settings/safety', [SafetyController::class, 'edit'])->name('safety.edit');

    // Devices...
    Route::get('settings/devices', [DeviceController::class, 'index'])->name('device.index');
    Route::delete('settings/devices/{device}', [DeviceController::class, 'destroy'])->name('device.destroy');

    // Mobile Authorization...
    Route::get('mobile/authorize', [MobileAuthorizationController::class, 'show'])->name('mobile-authorization.show');
    Route::post('mobile/authorize', [MobileAuthorizationController::class, 'store'])
        ->middleware('throttle:mobile-authorizations')
        ->name('mobile-authorization.store');

    // User...
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');

    // User Follow...
    Route::post('@{user:handle}/follow', [UserFollowController::class, 'store'])
        ->middleware('throttle:follows')
        ->name('user-follow.store');
    Route::delete('@{user:handle}/follow', [UserFollowController::class, 'destroy'])
        ->middleware('throttle:follows')
        ->name('user-follow.destroy');

    // Organization Follow...
    Route::post('organizations/{organization}/follow', [OrganizationFollowController::class, 'store'])
        ->whereUuid('organization')
        ->middleware('throttle:follows')
        ->name('organization-follow.store');
    Route::delete('organizations/{organization}/follow', [OrganizationFollowController::class, 'destroy'])
        ->whereUuid('organization')
        ->middleware('throttle:follows')
        ->name('organization-follow.destroy');

    // User Mute...
    Route::post('@{user:handle}/mute', [UserMuteController::class, 'store'])
        ->name('user-mute.store');
    Route::delete('@{user:handle}/mute', [UserMuteController::class, 'destroy'])
        ->name('user-mute.destroy');

    // User Block...
    Route::post('@{user:handle}/block', [UserBlockController::class, 'store'])
        ->name('user-block.store');
    Route::delete('@{user:handle}/block', [UserBlockController::class, 'destroy'])
        ->name('user-block.destroy');

    // Suggestion Dismissal...
    Route::post('@{user:handle}/dismiss', [SuggestionDismissalController::class, 'store'])
        ->name('suggestion-dismissal.store');

    // User Timeout, given by moderators...
    Route::post('@{user:handle}/timeout', [UserTimeoutController::class, 'store'])
        ->name('user-timeout.store');
    Route::delete('@{user:handle}/timeout', [UserTimeoutController::class, 'destroy'])
        ->name('user-timeout.destroy');

    // User Ban, given by moderators...
    Route::post('@{user:handle}/ban', [UserBanController::class, 'store'])
        ->name('user-ban.store');
    Route::delete('@{user:handle}/ban', [UserBanController::class, 'destroy'])
        ->name('user-ban.destroy');
});

Route::fallback(fn () => abort(404));
