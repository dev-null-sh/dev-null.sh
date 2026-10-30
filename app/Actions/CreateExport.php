<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\CommitImage;
use App\Models\CommitReport;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Device;
use App\Models\Message;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserTimeout;
use App\Queries\CommitBuilder;
use App\Services\CommitTranslations;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use stdClass;
use Traversable;

/**
 * @phpstan-type ExportPoll array{commit_id: string, duration_minutes: int, opened_at: string|null, options: list<string>, created_at: string}
 * @phpstan-type ExportPollVote array{commit_id: string, option: string, created_at: string}
 * @phpstan-type ExportMessage array{to: string|null, body: string, voice: string|null, created_at: string}
 * @phpstan-type ExportUser array{handle: string}
 * @phpstan-type UserExport array{
 *     schema_version: int,
 *     exported_at: string,
 *     profile: array<string, bool|int|string|null>,
 *     active_days: Generator<int, string>,
 *     commits: Generator<int, array<string, mixed>>,
 *     polls: Generator<int, ExportPoll>,
 *     poll_votes: Generator<int, ExportPollVote>,
 *     stars: Generator<int, string>,
 *     following: Generator<int, ExportUser>,
 *     followers: Generator<int, ExportUser>,
 *     muting: Generator<int, ExportUser>,
 *     blocking: Generator<int, ExportUser>,
 *     dismissed_suggestions: Generator<int, ExportUser>,
 *     organizations: array<int, array{handle: string, role: string, joined_at: string}>,
 *     messages: Generator<int, ExportMessage>,
 *     reports: Traversable<int, array<string, mixed>>,
 *     remote_pins: array<int, array{to: string|null, pinned_at: string|null}>,
 *     remote_mutes: array<int, array{to: string|null, muted_until_at: string|null}>,
 *     notifications: Generator<int, array<string, int|string|null>>,
 *     ssh_keys: array<mixed>,
 *     push_subscriptions: array<int, array{id: string}>,
 *     devices: array<int, array{name: string, fingerprint: string, approved_at: string, last_used_at: string|null}>,
 *     timeouts: array<int, array{reason: string, ends_at: string, created_at: string}>,
 *     sessions: Collection<int, stdClass>
 * }
 */
final readonly class CreateExport
{
    /**
     * Everything stored about the user, deleted commits included, but never secrets like the remember token or session ids.
     *
     * @return UserExport
     */
    public function handle(User $user): array
    {
        return [
            'schema_version' => 4,
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'github_id' => $user->github_id,
                'handle' => $user->handle,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'bio' => $user->bio,
                'location' => $user->location,
                'website' => $user->website,
                'company' => $user->company,
                'has_sponsors_listing' => $user->has_sponsors_listing,
                'preferred_lane' => $user->preferred_lane,
                'timezone' => $user->timezone,
                'show_local_time' => $user->show_local_time,
                'translation_language' => $user->translation_language,
                'automatic_translation' => $user->automatic_translation ?? true,
                'lane_color' => $user->lane_color,
                'pasteis_mode' => $user->pasteis_mode,
                'pasteis_received' => $user->pasteis_received,
                'pasteis_given_today' => $user->pasteis_given_today,
                'pasteis_given_on' => $user->pasteis_given_on?->toDateString(),
                'remotes_enabled' => $user->remotes_enabled,
                'remotes_from' => $user->remotes_from->value,
                'pull_receipts' => $user->pull_receipts,
                'pinned_commit_id' => $user->pinned_commit_id,
                'live_platform' => $user->live_platform?->value,
                'live_url' => $user->live_url,
                'live_ends_at' => $user->live_ends_at?->toIso8601String(),
                'last_seen_at' => $user->last_seen_at?->toDateString(),
                'commit_rules_accepted_at' => $user->commit_rules_accepted_at?->toIso8601String(),
                'emails_unsubscribed_at' => $user->emails_unsubscribed_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ],
            'active_days' => $this->activeDays($user),
            'commits' => $this->commits($user),
            'polls' => $this->polls($user),
            'poll_votes' => $this->pollVotes($user),
            'stars' => $this->stars($user),
            'following' => $this->handles($user->following()),
            'followers' => $this->handles($user->followers()),
            'muting' => $this->handles($user->muting()),
            'blocking' => $this->handles($user->blocking()),
            'dismissed_suggestions' => $this->handles($user->dismissedSuggestions()),
            'organizations' => $this->organizations($user),
            'messages' => $this->messages($user),
            'reports' => CommitReport::query()->where('reporter_id', $user->id)->oldest('id')->cursor()->map(fn (CommitReport $report): array => [
                'commit_id' => $report->commit_id,
                'reason' => $report->reason,
                'details' => $report->details,
                'attachments' => $report->toArray()['attachments'],
                'created_at' => $report->created_at->toIso8601String(),
            ])->getIterator(),
            'remote_mutes' => Conversation::query()
                ->join('conversation_participants as mine', 'mine.conversation_id', '=', 'conversations.id')
                ->where('mine.user_id', $user->id)
                ->whereNotNull('mine.muted_until_at')
                ->select('conversations.*')
                ->addSelect('mine.muted_until_at as muted_until_at')
                ->with('participants.user')
                ->oldest('mine.id')
                ->get()
                ->map(fn (Conversation $conversation): array => [
                    'to' => $this->recipient($user, $conversation),
                    'muted_until_at' => $conversation->muted_until_at?->toIso8601String(),
                ])
                ->all(),
            'remote_pins' => Conversation::query()
                ->join('conversation_participants as mine', 'mine.conversation_id', '=', 'conversations.id')
                ->where('mine.user_id', $user->id)
                ->whereNotNull('mine.pinned_at')
                ->select('conversations.*')
                ->addSelect('mine.pinned_at as pinned_at')
                ->with('participants.user')
                ->oldest('mine.id')
                ->get()
                ->map(fn (Conversation $conversation): array => [
                    'to' => $this->recipient($user, $conversation),
                    'pinned_at' => $conversation->pinned_at?->toIso8601String(),
                ])
                ->all(),
            'notifications' => $this->notifications($user),
            'ssh_keys' => $user->sshKeys()->pluck('fingerprint')->all(),
            'push_subscriptions' => $user->pushSubscriptions()->get()->map(fn (PushSubscription $subscription): array => [
                'id' => $subscription->id,
            ])->all(),
            'devices' => $user->devices()->oldest('approved_at')->get()->map(fn (Device $device): array => [
                'name' => $device->name,
                'fingerprint' => $device->fingerprint,
                'approved_at' => $device->approved_at->toIso8601String(),
                'last_used_at' => $device->last_used_at?->toIso8601String(),
            ])->all(),
            'timeouts' => $user->timeouts()->oldest()->get()->map(fn (UserTimeout $timeout): array => [
                'reason' => $timeout->reason->value,
                'ends_at' => $timeout->ends_at->toIso8601String(),
                'created_at' => $timeout->created_at->toIso8601String(),
            ])->all(),
            'sessions' => $this->sessions($user),
        ];
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function commits(User $user): Generator
    {
        $commits = $user->commits()
            ->withTrashed()
            ->select(['id', 'parent_id', 'quoted_commit_id', 'body', 'status', 'pasteis_count', 'created_at', 'updated_at', 'deleted_at'])
            ->with('images:id,commit_id')
            ->lazyById(500);

        $translations = resolve(CommitTranslations::class);

        foreach ($commits->chunk(500) as $batch) {
            $cached = Cache::many($batch->map($translations->key(...))->all());

            foreach ($batch as $commit) {
                yield [
                    'id' => $commit->id,
                    'parent_id' => $commit->parent_id,
                    'quoted_commit_id' => $commit->quoted_commit_id,
                    'body' => $commit->body,
                    'cached_translations' => $cached[$translations->key($commit)],
                    'status' => $commit->status->value,
                    'pasteis_count' => $commit->pasteis_count,
                    'images' => $commit->images->map(fn (CommitImage $image): string => $image->url())->all(),
                    'created_at' => $commit->created_at->toIso8601String(),
                    'updated_at' => $commit->updated_at->toIso8601String(),
                    'deleted_at' => $commit->deleted_at?->toIso8601String(),
                ];
            }
        }
    }

    /**
     * @return Generator<int, ExportPoll>
     */
    private function polls(User $user): Generator
    {
        $polls = Poll::query()
            ->whereHas('commit', fn (CommitBuilder $query): CommitBuilder => $query->where('user_id', $user->id))
            ->with('options')
            ->lazyById(500);

        foreach ($polls as $poll) {
            yield [
                'commit_id' => $poll->commit_id,
                'duration_minutes' => $poll->duration->value,
                'opened_at' => $poll->opened_at?->toIso8601String(),
                'options' => array_values($poll->options->map(fn (PollOption $option): string => $option->label)->all()),
                'created_at' => $poll->created_at->toIso8601String(),
            ];
        }
    }

    /**
     * Only the user's own choices: never who else voted.
     *
     * @return Generator<int, ExportPollVote>
     */
    private function pollVotes(User $user): Generator
    {
        foreach ($user->pollVotes()->with(['poll', 'option'])->lazyById(500) as $vote) {
            yield [
                'commit_id' => $vote->poll->commit_id,
                'option' => $vote->option->label,
                'created_at' => $vote->created_at->toIso8601String(),
            ];
        }
    }

    /**
     * The days the user was seen, oldest first. They are read while the export streams, after the request asking for it
     * was recorded, so an export that is the first visit of the day includes that day.
     *
     * @return Generator<int, string>
     */
    private function activeDays(User $user): Generator
    {
        /** @var LazyCollection<int, object{seen_at: string}> $days */
        $days = DB::table('user_active_days')->where('user_id', $user->id)->oldest('seen_at')->cursor();

        foreach ($days as $day) {
            yield $day->seen_at;
        }
    }

    /**
     * @return Generator<int, string>
     */
    private function stars(User $user): Generator
    {
        /** @var LazyCollection<int, object{commit_id: string}> $stars */
        $stars = $user->starredCommits()->newPivotQuery()->select('commit_id')->lazyById(500, 'commit_id');

        foreach ($stars as $star) {
            yield $star->commit_id;
        }
    }

    /**
     * The messages the user sent. What others sent them is theirs to export.
     *
     * @return Generator<int, ExportMessage>
     */
    private function messages(User $user): Generator
    {
        $recipients = Conversation::query()
            ->whereIn('id', ConversationParticipant::query()->select('conversation_id')->where('user_id', $user->id))
            ->with('participants.user')
            ->get()
            ->mapWithKeys(fn (Conversation $conversation): array => [$conversation->id => $this->recipient($user, $conversation)]);

        foreach (Message::query()->where('user_id', $user->id)->whereNull('event')->with('voice')->lazyById(500) as $message) {
            yield [
                'to' => $recipients->get($message->conversation_id),
                'body' => $message->body,
                'voice' => $message->voice?->url(),
                'created_at' => $message->created_at->toIso8601String(),
            ];
        }
    }

    /**
     * The other person in a conversation, or the org it is.
     */
    private function recipient(User $user, Conversation $conversation): ?string
    {
        return $conversation->isOrg()
            ? 'org: '.($conversation->name ?? $conversation->id)
            : $conversation->participants->firstWhere('user_id', '!=', $user->id)?->user->handle;
    }

    /**
     * @param  BelongsToMany<User, User>  $users
     * @return Generator<int, ExportUser>
     */
    private function handles(BelongsToMany $users): Generator
    {
        /** @var LazyCollection<int, object{handle: string}> $users */
        $users = $users->join('profiles', 'profiles.github_id', '=', 'users.github_id')->toBase()->select(['users.id', 'profiles.handle'])->lazyById(500, 'users.id', 'id');

        foreach ($users as $user) {
            yield ['handle' => $user->handle];
        }
    }

    /**
     * @return array<int, array{handle: string, role: string, joined_at: string}>
     */
    private function organizations(User $user): array
    {
        /** @var Collection<int, object{handle: string, role: string, created_at: string}> $memberships */
        $memberships = $user->organizations()->newPivotQuery()
            ->join('profiles', 'profiles.id', '=', 'organization_members.organization_id')
            ->oldest('organization_members.created_at')
            ->get(['profiles.handle', 'organization_members.role', 'organization_members.created_at']);

        return $memberships->map(fn (object $membership): array => [
            'handle' => $membership->handle,
            'role' => $membership->role,
            'joined_at' => CarbonImmutable::parse($membership->created_at)->toIso8601String(),
        ])->all();
    }

    /**
     * Moderators stay anonymous, as they are on the notifications page, and level-ups are the user's own doing.
     *
     * @return Generator<int, array<string, int|string|null>>
     */
    private function notifications(User $user): Generator
    {
        /** @var LazyCollection<int, object{type: string, actor: string, commit_id: string|null, reason: string|null, level: int|string|null, read_at: string|null, created_at: string}> $notifications */
        $notifications = $user->notifications()
            ->join('users as actors', 'actors.id', '=', 'notifications.actor_id')
            ->join('profiles as actor_profiles', 'actor_profiles.github_id', '=', 'actors.github_id')
            ->toBase()
            ->select(['notifications.id', 'notifications.type', 'notifications.commit_id', 'notifications.reason', 'notifications.level', 'notifications.read_at', 'notifications.created_at', 'actor_profiles.handle as actor'])
            ->lazyById(500, 'notifications.id', 'id');

        foreach ($notifications as $notification) {
            $isModeration = in_array($notification->type, [NotificationType::Approved->value, NotificationType::Rejected->value, NotificationType::LeveledUp->value], true);

            yield [
                'type' => $notification->type,
                'actor' => $isModeration ? null : $notification->actor,
                'commit_id' => $notification->commit_id,
                'reason' => $notification->reason,
                'level' => $notification->level === null ? null : (int) $notification->level,
                'read_at' => $notification->read_at === null ? null : CarbonImmutable::parse($notification->read_at)->toIso8601String(),
                'created_at' => CarbonImmutable::parse($notification->created_at)->toIso8601String(),
            ];
        }
    }

    /**
     * Never the session id or payload: either could be used to take over the session.
     *
     * @return Collection<int, stdClass>
     */
    private function sessions(User $user): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->get(['ip_address', 'user_agent', 'last_activity']);
    }
}
