# App/Actions guidelines

- This application uses the Action pattern and prefers for much logic to live in reusable and composable Action classes.
- Actions live in `app/Actions`, they are named based on what they do, with no suffix.
- Actions will be called from many different places: jobs, commands, HTTP requests, API requests, MCP requests, and more.
- Create dedicated Action classes for business logic with a single `handle()` method.
- Inject dependencies via constructor using private properties.
- Create new actions with `php artisan make:action "{name}" --no-interaction`
- Wrap complex operations in `DB::transaction()` within actions when multiple models are involved.
- Transactions and row locks that guard a write live in actions, never in HTTP middleware: that puts database rules in the HTTP layer and holds the transaction open for the whole request.
- When a concurrent request can pass the same check before the write (a limit, the last admin), the action locks the parent row (the conversation, the organization) in a short `DB::transaction()`, checks the condition again under the lock, and doesn't write if it no longer holds. This is not validation: it never aborts or throws a `ValidationException` or an HTTP exception, and the request keeps its own check and error message. When the caller must tell the user, `throw new` a dedicated exception from `app/Exceptions` (never `throw_if()`), named for the rule it protects, without an `Exception` suffix, and the caller catches it (`OrganizationMustHaveAnAdmin`). `CreateOrganizationAdmin`, which returns `false` instead, predates this. When the request's error is enough, skip the write silently (`UpdateMessagePin`).
- Some actions won't require dependencies via `__construct` and they can use just the `handle()` method.
- Actions never authorize or validate anything: that lives in the form request of the endpoint calling them. Checking again under a lock, above, is the only exception.

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
