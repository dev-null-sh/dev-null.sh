# Emoji reactions, your repositories, and GitHub Actions

This local branch builds on [star flavors, PR #617](https://github.com/nunomaduro/dev-null.sh/pull/617). People can add multiple different emoji to the same commit. Each public emoji has its own count and selected state; reactions are independent of stars, ranking, and star notifications. The original star implementation is preserved unchanged. The six original flavor names remain valid. The picker now uses the shared native chat picker with Unicode 17 emoji, including skin tones, flags, and joined sequences. Search names, `:shortcodes:`, or paste an emoji. Custom uploaded Slack workspace images are outside this change.

## Choose where a reaction goes

The picker shows a **Public** checkbox for dev-null and a separate checkbox labeled with each connected `owner/repository`. Private repositories have a lock icon. Select one or more destinations, then pick an emoji to add it without replacing your others. Each emoji retains its own destination choices. Clicking one of your reaction chips beside the post removes just that emoji. Clicking a selected emoji in the picker also removes it unless you first change the destination checkboxes; in that case, picking it updates only that emoji’s destinations. Uncheck Public to keep the reaction off dev-null; select Public alongside any repositories to save in both places. A selected public repository still makes its copy public.

Repository-only reactions never enter the public star table, counts, rankings, top emoji, or author notifications. Only their owner can read them through the personal picker endpoint, which is not cacheable. Their emoji and queued payload are encrypted at rest. Selecting repository-only removes that emoji’s public copy; switching back to public-only queues a removal record for the repository reaction. Deselecting a repository queues a removal only for that repository. **Clear all** removes all your emoji and their repository copies. Neither adding nor removing a reaction touches existing stars or their notifications. Even a ⭐ emoji reaction is separate from the native Star button. Those removals cannot erase Git history or undo a workflow that already ran.

“Repository only” describes where the reaction is visible, not an encryption guarantee. A public destination makes the JSON public. GitHub, collaborators on a private repository, and the dev-null server operator can access private copies. There is no public/private setting for posts in this change: only approved posts and replies are mirrored. Reaction records contain the target's ID and URL, never somebody else's post body or DMs.

## Connect your repositories

In **Settings → Your repositories**, add an existing `owner/repository` and a fine-grained GitHub token from the same account used to sign in. Select only that repository and grant **Contents: read and write**. Initialize it with a README. It may be private, public, or a repository you collaborate on; you need write access. Tokens are encrypted server-side, never sent back in props or flashed into session old input. This prototype uses personal tokens; an install-and-select GitHub App flow can replace the credential step later.

Connect up to ten destinations; each has its own automatic commit setting and credential. Adding a repo leaves existing connections intact.

Connecting verifies identity, repository write access, and an initialized default branch without writing a probe file. GitHub is the final authority on token Contents permission; an insufficient token leaves deliveries queued with a settings error. The connection pins repository ID, branch, and visibility. Delivery pauses if visibility or repository identity changes. Reconnect to acknowledge the new destination. Token renewal on the same destination preserves queued records. Reconnecting with changed branch or privacy, or disconnecting, cancels pending records and removes local reaction state for that connection only; it preserves public stars and previously written Git history.

Enable **Mirror my new approved commits and replies** for automatic author-owned post records. Each reaction chooses its destinations individually. Existing activity is not backfilled. Uploaded media, poll state, and private chat messages are not copied; post text, source URL, reply/quote IDs, and timestamps are included.

## Repository format

Each activity transition creates an immutable UTF-8 JSON file. This journal is also the mirror: later `saved` and `removed` records describe the current state without rewriting earlier records.

```text
dev-null/<actor-github-id>/commits/<commit-id>/<event-id>.json
dev-null/<actor-github-id>/reactions/<commit-id>/<event-id>.json
```

```json
{
  "event_id": "019...",
  "schema_version": 1,
  "occurred_at": "2026-10-19T12:00:00+00:00",
  "actor": { "github_id": "12345", "username": "example" },
  "type": "reaction.saved",
  "reaction": { "emoji": "🚀", "visibility": "repository" },
  "commit": { "id": "019...", "url": "https://dev-null.sh/@author/commits/019..." }
}
```

Event types: `commit.saved`, `commit.removed`, `reaction.saved`, `reaction.removed`. Adding an emoji or changing its destinations creates a new `reaction.saved` event for the affected destination. Removing an emoji emits `reaction.removed` for that emoji, leaving all others intact. Repeated identical saves do not enqueue another event. When replaying the journal, key each reaction by actor ID, commit ID, and emoji; a removal affects only that key. The journal is written in UUIDv7 event order per connection. Treat `event_id` as the idempotency key when an automation has external effects. A workflow rerun can repeat effects unless that workflow deduplicates them.

## Run delivery

Run the existing Laravel scheduler (`php artisan schedule:work` locally or a scheduler cron in production). The `mirrors:deliver` command runs once per minute, up to 100 events per repository per run. You can run it manually after correcting connection errors. Failures stop that destination's batch, retain pending events, and retry next minute. GitHub writes run in the background. Saving a reaction or changing the connection can briefly wait for the same repository’s in-flight delivery lock.

The activity and outbox record share a database transaction. Disconnect and reconnect serialize against delivery. A retry first checks the immutable event path: an identical existing record counts as delivered, so a lost response does not create a second Git commit. A conflicting file is not overwritten. Delivery does not force-push, create repositories, change repository privacy, or install workflows.

## Actions belong to the user

Copy [the example](examples/emoji-actions.yml) to `.github/workflows/dev-null.yml` **in your destination repository**, and replace `YOUR_GITHUB_NUMERIC_ID`. A normal push from the personal token triggers it. The example reads added JSON files, matches your new 🚀 reactions, and runs an example step. Replace that step with your own job, issue triage, build, notification, or other action. Grant only the permissions the replacement needs.

Payload fields are data. Do not interpolate post text or usernames directly into shell scripts. The example parses JSON in Python and only uses fixed script text. Use `reaction.removed` for removal-specific workflows when desired; the example intentionally ignores those records.

References: [GitHub Contents API](https://docs.github.com/en/rest/repos/contents#create-or-update-file-contents), [events that trigger Actions](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#push). Slack's [reaction events](https://docs.slack.dev/reference/events/reaction_added/) are the interaction inspiration; no Slack service is involved.

## Safe local review

Use a separate local PostgreSQL database, install dependencies, migrate and build as in the project README. Set `APP_ENV=local` and `DEVNULL_REACTION_DEMO=true`, then run:

```sh
php scripts/seed-reaction-demo.php
bun run build
php artisan serve --host=127.0.0.1 --port=8767
```

Open `http://127.0.0.1:8767/_demo/reactions`. It signs into a synthetic local account and displays the real picker, real public reaction behavior, and real encrypted outbox, with an example Actions match. The demo switch blocks all mirror verification and delivery HTTP requests, and the route only exists locally and accepts loopback requests. Nothing is sent to GitHub, and the Actions job is not run locally. Remove the demo switch to test a real connection later.


The picker opens immediately from an account-scoped browser cache and refreshes in the background. Connected repository labels also arrive with the signed-in page, so opening the picker never waits for its state endpoint. A failed refresh keeps the cached controls usable, and an older response cannot overwrite a newer reaction change. Checkbox choices remain stable while the picker is open.

Each reaction chip shows its public reactors on hover or keyboard focus. Names come only from public reactions (up to 20 names plus a remaining count). Private-only reactions appear as locked chips only for their owner; click the chip beside the post to remove one. The picker has no duplicate “Your reactions” row.


Public reaction storage mirrors the shape of stars, but is separate: `commit_reactions` is keyed by user ID, commit ID, and emoji, with timestamps. `/commits/{commit}/reactions` handles reaction writes and deletes; the original `/star` endpoints and actions are unchanged. There is no star-to-reaction migration. Counts and owner-only chips update optimistically and roll back if saving fails. The reaction picker follows Quote, with reaction chips wrapping into additional rows.
