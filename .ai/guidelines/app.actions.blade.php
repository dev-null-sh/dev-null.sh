# App/Actions guidelines

- This application uses the Action pattern and prefers for much logic to live in reusable and composable Action classes.
- Actions live in `app/Actions`, they are named based on what they do, with no suffix.
- Actions will be called from many different places: jobs, commands, HTTP requests, API requests, MCP requests, and more.
- Create dedicated Action classes for business logic with a single `handle()` method.
- Inject dependencies via constructor using private properties.
- Create new actions with `php artisan make:action "{name}" --no-interaction`
- Wrap complex operations in `DB::transaction()` within actions when multiple models are involved.
- Some actions won't require dependencies via `__construct` and they can use just the `handle()` method.
- Actions never authorize or validate anything: that lives in the form request of the endpoint calling them.

## CRUD by design

- Every action name starts with `Create`, `Update`, or `Delete`. Never with any other verb: no `Approve`, `Reject`, `Pin`, `Star`, `Follow`, `Save`, `Sync`, `Notify`, `Render`, `Fetch`, `Mark`, `Export`, `Store`.
- Name the rest after the model, relationship, or attribute being changed, and pass what it changes to as an argument, so one action covers every value:
  - `ApproveCommit` / `RejectCommit` → `UpdateCommitStatus::handle($commit, CommitStatus::Approved)`.
  - `ApproveCommits` → `UpdateCommitStatuses::handle($commitIds, CommitStatus::Approved)`.
  - `PinCommit` / `UnpinCommit` → `UpdateCommitPin` / `DeleteCommitPin`.
  - `StarCommit` / `UnstarCommit` → `CreateCommitStar` / `DeleteCommitStar`.
  - `FollowUser` / `UnfollowUser` → `CreateUserFollow` / `DeleteUserFollow`.
  - `ExportPersonalData` → `CreateExport`.
  - `NotifyAboutReplies` → `CreateReplyNotifications`.
- Mirror the controller and form request it serves: `CommitStarController@store` → `CreateCommitStarRequest` → `CreateCommitStar`.
- Code that only reads is not an action: database reads go in `app/Queries` (`CommitByReferenceQuery`), and pure parsing or computing goes in `app/Services` (`Urls`, `Mentions`).
- `tests/Unit/ArchTest.php` enforces this.

@boostsnippet('Example action class', 'php')
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;

final readonly class UpdateCommitStatus
{
    public function __construct(private UpdateCommitStatuses $updateCommitStatuses)
    {
        //
    }

    public function handle(Commit $commit, CommitStatus $status, ?string $reason = null): void
    {
        $this->updateCommitStatuses->handle([$commit->id], $status, $reason);
    }
}
@endboostsnippet
