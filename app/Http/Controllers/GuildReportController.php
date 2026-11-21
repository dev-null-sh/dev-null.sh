<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateGuildReport;
use App\Actions\UpdateGuildReportDismissedAt;
use App\Enums\UserUpdate;
use App\Http\Requests\CreateGuildReportRequest;
use App\Http\Requests\DeleteGuildReportRequest;
use App\Http\Requests\ListGuildReportsRequest;
use App\Models\Guild;
use App\Models\GuildReport;
use App\Models\GuildSubmission;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class GuildReportController
{
    public function index(ListGuildReportsRequest $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('moderation/guilds', [
            'submissions' => Inertia::live(fn (): array => GuildSubmission::query()->addSelect([
                'author' => Profile::query()->select('handle')->whereColumn('id', 'guild_submissions.user_id'),
                'is_edit' => Guild::query()->selectRaw('count(*) > 0')->whereColumn('handle', 'guild_submissions.handle')->whereColumn('user_id', 'guild_submissions.user_id'),
            ])->withCasts(['is_edit' => 'boolean'])->oldest()->oldest('id')->limit(20)->get()
                ->map(fn (GuildSubmission $submission): array => [
                    'id' => $submission->id,
                    'handle' => $submission->handle,
                    'name' => $submission->name,
                    'description' => $submission->description,
                    'emoji' => $submission->emoji,
                    'icon_url' => $submission->icon_path === null ? null : route('guild-submission-icon.show', $submission),
                    'remove_icon' => $submission->remove_icon,
                    'author' => $submission->getAttribute('author'),
                    'is_edit' => $submission->getAttribute('is_edit'),
                ])->all(), UserUpdate::Moderation->value, new PrivateChannel($user)),
            'reports' => Inertia::live(fn (): array => GuildReport::query()->whereNull('dismissed_at')->with('guild')->oldest('id')
                ->simplePaginate(20)->through(fn (GuildReport $report): array => [
                    'id' => $report->id,
                    'details' => $report->details,
                    'guild' => ['id' => $report->guild_id, ...$report->guild->toArray()],
                ])->toArray(), UserUpdate::Moderation->value, new PrivateChannel($user)),
        ]);
    }

    public function store(CreateGuildReportRequest $request, Guild $guild, #[CurrentUser] User $user, CreateGuildReport $action): RedirectResponse
    {
        $action->handle($user, $guild, $request->string('details')->value());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Report received. Admins will review it privately.']);

        return back();
    }

    public function destroy(DeleteGuildReportRequest $request, GuildReport $report, UpdateGuildReportDismissedAt $action): RedirectResponse
    {
        $action->handle($report);

        return back();
    }
}
