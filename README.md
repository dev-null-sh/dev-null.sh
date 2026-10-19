# Project setup

A social network for developers. Sign in with GitHub, follow developers, and share commits.

Read the [knowledge base](KNOWLEDGE_BASE.md) for the stack, hosting, repository, and platform lore.

## Getting Started

Requires PHP 8.5+, Composer, Bun, and PostgreSQL 17+ with the [pgvector](https://github.com/pgvector/pgvector) and `pg_trgm` extensions available. [Laravel Herd](https://herd.laravel.com)'s PostgreSQL service ships with both. Create a `devnull` database for the app and a `testing` database for the test suite.

```bash
git clone https://github.com/nunomaduro/dev-null.sh.git
cd dev-null.sh
composer setup
```

The setup script installs dependencies, creates `.env`, generates an app key, runs migrations, and builds assets.

### Local sign-in

With `APP_ENV=local`, run `composer dev`, visit `http://localhost:8000` (or your configured `APP_URL`), and sign in: a modal asks for a username and signs you in as that user, creating it if it doesn't exist yet. GitHub isn't involved at all. The routes are only registered in the local environment, see `routes/web.php`.

### GitHub login

Outside the local environment, users sign in with GitHub. Create a [GitHub OAuth app](https://github.com/settings/developers) with the callback URL `{APP_URL}/auth/github/callback`, then set `GITHUB_CLIENT_ID` and `GITHUB_CLIENT_SECRET` in `.env`.

### Issue commands

The `/feature` and `/bug` commands below the composer open an issue on `nunomaduro/dev-null.sh` from the draft, as a GitHub App crediting the reporter. No user grants any extra permission and no user token is stored. Create a [GitHub App](https://github.com/settings/apps/new) with webhooks disabled and only the **Issues: Read and write** repository permission, install it on `nunomaduro/dev-null.sh` alone, generate a private key, then set `GITHUB_APP_ID` and `GITHUB_APP_PRIVATE_KEY` (the PEM contents, with newlines written as `\n`) in `.env`. Each installation token is requested for issues on this repository only and is cached, encrypted, for 50 minutes.

### Embeds

A link card for a video, song or pen becomes a player that loads when clicked. The player URL is built on the server from the link, so only the sites below can be framed. None of them need an API key.

| Key | Links |
|---|---|
| `youtube` | `youtube.com/watch`, `youtu.be`, `/shorts`, `/live`, `/playlist` (with `t=` start times) |
| `vimeo` | `vimeo.com/{id}`, unlisted `vimeo.com/{id}/{hash}`, channels and groups |
| `loom` | `loom.com/share/{id}` |
| `twitch` | channels, `/videos/{id}` and clips |
| `suno` | `suno.com/song/{id}` |
| `spotify` | tracks, albums, playlists, artists, episodes and shows |
| `soundcloud` | tracks and sets |
| `apple_music` | albums, playlists and songs |
| `codepen` | pens |
| `asciinema` | recordings |

| Variable | Default | |
|---|---|---|
| `EMBEDS_ENABLED` | `true` | `false` turns every link back into a plain link card |
| `EMBEDS_PROVIDERS` | empty (all) | Comma-separated keys from the table above, such as `youtube,suno,spotify` |
| `EMBEDS_YOUTUBE_PRIVACY_ENHANCED` | `true` | Plays YouTube from `youtube-nocookie.com` |

Twitch requires the embedding site's host, which is taken from `APP_URL`. To add a site, implement `App\Contracts\Services\EmbedProvider` and register it in `config/embeds.php`.

### Verify installation

Run `composer test` to check linting, static analysis, type coverage, and tests. The code coverage check requires Xdebug or PCOV.

## Public API

Read-only, no authentication, JSON, versioned under `/api/v1`. Only approved commits are returned; pending, rejected, and deleted commits are a 404 or left out. Rate limited to 60 requests a minute per IP, and open to cross-origin `GET` requests.

| Endpoint | Returns |
|---|---|
| `GET /api/v1/users/{username}` | A public profile with commit, follower, and following counts |
| `GET /api/v1/users/{username}/commits` | The user's top-level commits, newest first, 20 per page (`?replies=1` for their replies instead) |
| `GET /api/v1/commits/{id}` | One commit with its author, public parent id, star and reply counts, link preview (with its `embed` player, if any), and images (`id`, `url`, `width`, `height`) |

Lists are cursor-paginated: follow `links.next` (or pass `meta.next_cursor` as `?cursor=`) until it is `null`.

```bash
curl -s https://dev-null.sh/api/v1/users/nunomaduro/commits | jq '.data[] | {short_hash, body}'
```

Every profile also has an Atom feed of its latest 50 top-level commits at `/@{username}/feed.atom`, with their images as enclosures, cached for five minutes.

## Signed requests

The mobile app signs its requests to `/api/v1` with an SSH key it keeps on the device, which the user approved on the web (see [Signing in](#signing-in)). A signed request to the endpoints above gets what the web shows that user. The log, replies, notifications, saved commits, committing, deleting, following, starring, saving, muting, blocking, reporting and pastéis only take signed requests. Signed requests are limited to 120 a minute per user.

Commits in a signed response carry what the web's cards show: `preview_segments`, the excerpt a list shows of a long commit (or `null`), the `poll` with the user's view of its votes and each option's `percentage`, the `quoted_commit` (a tombstone once deleted, or `null` when the user may not see it, while `quoted_commit_id` still says a commit was quoted), and in lists the `reply_preview` the web shows under each commit. Its `next_cursor` continues with `GET /api/v1/commits/{id}/replies?newest=1&cursor=…`, as on the web.

A signed profile also says `is_muting` and has the web's `achievements`, with the Unix rank.

`POST` and `DELETE /api/v1/users/{username}/mute` mute and unmute someone, and `POST` and `DELETE /api/v1/users/{username}/block` block and unblock them, as the web does: nobody can mute or block themselves, muting is silent, and blocking ends the follows both ways, which unblocking doesn't bring back. Each answers with the user and the new `is_muting` or `is_blocking`. `GET /api/v1/me/muted` and `GET /api/v1/me/blocked` list the people the user mutes and blocks, last first, as Settings → Safety does, 20 a page.

`GET /api/v1/log/{log}` serves the web's three logs, `following`, `latest` and `trending`, with the same queries and 20 commits a page. Trending is ranked, so it says `is_ranked`. With `?since={id}`, every log counts the `new_commits_count` newer than that commit, which is always 0 on Trending, since the web offers none there. An empty Following log comes with a few `suggestions`.

`GET /api/v1/notifications` groups notifications like the web and takes the web's `?type=` filter: `all` (the default), `none`, or a comma-separated list of `replies`, `quotes`, `stars`, `mentions`, `follows` and `moderation`. The response echoes it as `filters.type`. Listing them marks every notification as read, whatever the filter, and `read_at` says when, so the groups read at or after it are the new ones. `GET /api/v1/notifications/{id}/group` lists the notifications of one group, with the same filter, the way the web expands it.

`POST /api/v1/commits` takes a `body`, and either a `parent_id` to reply to a commit or a `quoted_commit_id` to quote one, but not both. Returns a `201` on successful commit creation.

`POST /api/v1/commits/{id}/reports` reports a commit to the moderators, privately, as the web's Report button does. It takes a `reason`, one of `spam`, `harassment`, `unsafe` or `other`, and optional `details` of up to 500 characters, and answers `204`. Reporting doesn't hide the commit. Only someone else's published commit can be reported: the user's own is a `403` with `own_commit`, and one that is pending, rejected, deleted, missing or across a block is a `404` with `commit_unavailable`. Reporting a commit again also answers `204` and keeps the first report. Each user can send 5 reports a minute.

`GET /api/v1/me` also has what only the user sees in the web's profile settings: `preferred_lane` (a lane, a custom `#rrggbb` color, or `null` for Automatic), the `automatic_lane` Automatic picks for them, their `sponsors_url` (or `null`), and `github_synced_at`, when their profile was last synced from GitHub (`null` if it only was when they signed in). `PUT /api/v1/me/github` syncs it from GitHub again, like Sync from GitHub on the web, and shares its limit of one sync a minute. `PUT /api/v1/me/lane` saves their color, with a `lane` from `0` to `5`, or `null` for Automatic, or a custom `color` as lowercase `#rrggbb` with a `null` lane, as the web's color picker does. Both answer like `GET /api/v1/me`.

`DELETE /api/v1/me` deletes the signed-in user's account the way Delete account does on the web, with their commits and every device they approved. It answers `204`, and from then on the key gets `device_not_approved`.

Each request sends three headers:

| Header | Value |
|---|---|
| `X-Devnull-Timestamp` | Unix time in seconds, within 5 minutes of the server's clock |
| `X-Devnull-Nonce` | 32 random hex characters, used once |
| `X-SSH-Signature` | The SSHSIG signature, base64 on one line |

The signature is made with `ssh-keygen -Y sign -n devnull-api` over these lines, joined by `\n`: the timestamp, the nonce, the method, the path with its query string exactly as sent, and the raw body. A body goes as JSON, with `Content-Type: application/json`, since the signature covers its bytes but not how they are read. Any other body is refused.

```text
1760534400
3f9c2a7e5b1d4c8f9a0b2c3d4e5f6a7b
POST
/api/v1/commits
{"body":"Hello from my phone"}
```

A request with any of these headers has to be signed right. A bad or expired signature is a `401`, never the public view. A request without them always gets the public view, and across a block a signed request gets neither profile: it is a `403`, `user_blocked` for the one who blocked and `user_unavailable` for the other, and their commits are hidden as on the web.

A key signs in once its user approved it as a device. It doesn't have to be on their GitHub account, and being on it isn't enough, so a key made for the GitHub Action can't use the API. Users see their devices, when each was last used, and revoke them under Settings → Devices (`/settings/devices`). A key that isn't approved, or was revoked, gets a `401` with `device_not_approved`, and the app signs in again.

### Errors

An error is JSON with a `message` to show the user. The errors the app has to tell apart also have a `code`, which stays the same when the message changes:

```json
{"message": "This key isn't approved for the /dev/null app. Sign in again in the app.", "code": "device_not_approved"}
```

| Status | `code` | When |
|---|---|---|
| `400` | `invalid_grant` | `POST /api/v1/devices` only: the code is wrong, expired or used, was handed out for another key, or the verifier doesn't match. |
| `401` | `invalid_signature` | The signature is missing, malformed or wrong, has expired, or was used already. |
| `401` | `device_not_approved` | Nobody approved the key as a device, or it was revoked. |
| `403` | `user_blocked` | The user blocked the profile they asked for. Neither of them sees the other's commits. The error comes with the `user` they blocked, as the web shows them. |
| `403` | `user_unavailable` | The profile isn't available to the user, because it blocked them. |
| `403` | `own_commit` | The user tried to report their own commit. |
| `404` | `commit_unavailable` | The commit to report doesn't exist, was deleted, isn't published, or is across a block. |
| `422` | `pasteis_exhausted` | `POST /api/v1/commits/{id}/pasteis` only: the user gave all 10 of today's pastéis. They come back at 00:00 UTC. |
| `429` | `rate_limited` | Over 120 signed requests a minute for the user, 10 commits a minute, 30 follows and unfollows a minute, 60 stars and unstars a minute, 60 saves and unsaves a minute, 5 reports a minute, 60 pastéis a minute, one profile sync a minute, or 60 failed signatures a minute from one IP. `Retry-After` says when to try again. |
| `503` | `github_sync_failed` | `PUT /api/v1/me/github` only: GitHub couldn't be reached, or didn't answer as expected. Nothing changed. |

Other errors have no `code`, like a `422` for a commit that fails validation or a `404` for a commit that doesn't exist, except when reporting it. Unsigned requests to the public endpoints get the same errors as before, without codes.

### Signing in

The app signs in the way an OAuth app does, with the authorization code flow and PKCE ([RFC 7636](https://www.rfc-editor.org/rfc/rfc7636)), and /dev/null as the provider. The code it gets is bound to its key, so only the app that holds the key can redeem it.

1. The app makes an ed25519 key and a random `code_verifier`, and opens this page in a browser session (`ASWebAuthenticationSession` on iOS):

   ```text
   https://dev-null.sh/mobile/authorize
       ?fingerprint=SHA256:hPEdU4FVZ1lfjzbPyBhqTWG2qxUu3ZbCnD8mZw9t2Vk
       &device_name=devnull%20on%20iPhone
       &code_challenge=E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM
       &code_challenge_method=S256
       &state=af0ifjsldkj
       &redirect_uri=devnull://auth/callback
   ```

   `code_challenge` is `base64url(sha256(code_verifier))` without padding. `redirect_uri` has to start with `devnull://`. A guest signs in with GitHub first and comes back to the page. A request with a missing or bad parameter gets an error page and is never redirected.
2. The page asks "Approve devnull on iPhone?", with the key's fingerprint and the account it signs in as. **Approve** redirects (`303`) to `devnull://auth/callback?code=…&state=af0ifjsldkj`. **Cancel** goes to `devnull://auth/callback?error=access_denied&state=af0ifjsldkj`. The app checks that `state` is the one it sent. A code is good for 5 minutes and only once, and each user can approve 10 times a minute.
3. The app redeems the code with `POST /api/v1/devices`, signed with the key like any other request, though the key isn't approved yet:

   ```json
   {"code": "…", "code_verifier": "dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk"}
   ```

   The key becomes an approved device of the user who approved it, and the answer is `200` with the user, like `GET /api/v1/me`. From then on every request it signs is theirs, until they revoke the device.

| Status | `code` | Means |
|---|---|---|
| `200` | | The device is approved. The body is the user, with `unread_notifications_count`. |
| `400` | `invalid_grant` | The code is wrong, expired or used, was handed out for another key, or the verifier doesn't match. Sign in again. |
| `401` | `invalid_signature` | The signature is bad, expired or used already. |
| `422` | | `code` or `code_verifier` is missing, or the verifier isn't 43 to 128 characters of `A-Z a-z 0-9 - . _ ~`. |
| `429` | `rate_limited` | Over 12 tries a minute for one key, or 60 from one IP, counted before signatures are checked. `Retry-After` says when to try again. |

## Available Tooling

### Development
- `composer dev` - Starts Laravel server, queue worker, log monitoring, and Vite+ dev server concurrently

### Code Quality
- `composer lint` - Runs Rector (refactoring), Pint (PHP formatting), and Oxfmt (JS/TS formatting)
- `composer test:lint` - Dry-run mode for CI/CD pipelines

### Testing
- `composer test:type-coverage` - Ensures 100% type coverage with Pest
- `composer test:types` - Runs PHPStan at the configured maximum level and TypeScript checks
- `composer test:unit` - Runs Pest tests with 100% code coverage requirement
- `composer test` - Runs the complete test suite (linting, static analysis, type coverage, unit tests)

### Maintenance
- `composer update:requirements` - Updates the PHP and frontend dependency version constraints

### Algolia developer search

Developer search and the remote picker can rank people with Algolia. Every user change is noted in `search_syncs` in the same transaction, and the scheduler runs `search:sync` every ten seconds to send up to 1,000 changes per batch from the current database state. Nothing is noted while `ALGOLIA_APP_ID` or `ALGOLIA_WRITE_KEY` is missing.

On Laravel Cloud, add the variables in the environment variables section of the environment's settings; they apply on the next deployment. Roll out in this order:

1. Set `ALGOLIA_APP_ID` and `ALGOLIA_WRITE_KEY` (an API key with `addObject`, `deleteObject` and `editSettings`). Leave `ALGOLIA_SEARCH_KEY` empty.
2. After the deployment has run the migrations, run `php artisan search:import users` from the environment's **Commands** tab. It configures the `production_users` index and queues every user; `search:sync` sends them. Indexes are prefixed with `APP_ENV` and an underscore.
3. Once the index count matches the users count, set `ALGOLIA_SEARCH_KEY` (a search-only key) and deploy.

Switch off `algolia-user-search` in `/operations` to go back to database search without a deployment; the index keeps syncing. Algolia errors are reported and fall back to database search.

### Semantic commit search

Commit search generates and caches query embeddings with the Laravel AI SDK, using the existing `OPENAI_API_KEY`. The provider, model, and dimensions are explicitly kept compatible with stored embeddings, so no re-embedding or database migration is needed. Query embeddings are cached for one hour; result visibility and relevance are evaluated on every search.

PostgreSQL cosine ranking, visibility filters, and the existing OpenAI relevance classifier are unchanged. Batch ingestion continues through the existing OpenAI service to preserve response-index ordering and count validation. Moderation is unchanged. After installing dependencies, run `php artisan boost:update` to refresh agent guidance and the AI SDK skill (run `php artisan boost:install` first if Boost is not configured locally).

### Knowledge base embeddings

After running the database migrations and configuring `OPENAI_API_KEY`, import the Markdown knowledge base:

```bash
php artisan knowledge-base:embed
```

The command defaults to `KNOWLEDGE_BASE.md`. Pass another repository-relative Markdown path to import a separate file.

Each non-empty section becomes a row in `knowledge_base_sections`, storing its source path, position, heading hierarchy, Markdown content, and vector embedding. All heading levels create boundaries; parent headings accompany child sections in the embedding input. Empty headings create no rows, and headings inside code blocks or blockquotes do not split sections.

Rerun the command after editing a file. It uses the existing OpenAI service to embed only new or changed sections, reusing vectors when both content and heading hierarchy are unchanged, even if a section moved. It atomically updates or inserts rows and removes sections no longer present. Other files are left alone; a failed import preserves the previous data. The reported count is the number of newly generated embeddings. Unchanged imports make no paid requests. Keep sections small enough for the embedding model's input limit; there is no additional size-based splitting. Tom uses this indexed knowledge through his search tool.

#### Internal semantic search

Inject `App\Queries\KnowledgeBaseSearchQuery` into an internal caller:

```php
$sections = $knowledgeBaseSearchQuery->get('Who is Tom?', limit: 5);
```

The query uses the Laravel AI SDK to generate query embeddings, with the same OpenAI provider, model, and dimensions as ingestion and commit search. It searches all imported sources using PostgreSQL cosine distance and returns a collection of sections, closest first, with `id`, `source`, `position`, `headings`, Markdown `content`, and a numeric `distance` (lower is closer). Ties sort by source and position. Embedding vectors are not included in the results.

The default limit is five. Blank queries and nonpositive limits return no results without calling OpenAI; short terms such as `AI` are supported. The SDK caches trimmed query embeddings for an hour and shares compatible cached queries with commit search, but section results are read fresh so imports immediately affect retrieval. Provider errors propagate to the caller rather than looking like an empty knowledge base.

These are nearest matches, not a guarantee of relevance or a calibrated confidence score. There is no similarity cutoff or post-specific relevance classifier; Tom is instructed to assess whether the returned sections answer the question. The query has no public endpoint.

#### Tom's public replies

Set `OPENCODE_API_KEY` alongside `OPENAI_API_KEY`, keep the queue worker running, and index the knowledge base and commits. The existing `@tom` account is identified by its nonnumeric GitHub ID, not merely its username. No new AI conversation tables or migrations are needed.

Tom's scope is simple friendly chat and answers about the platform or public posts. Greetings, thanks, jokes and small talk need no search. Factual platform and post answers use the search tools; unrelated tasks, general coding help and professional advice are outside his role.

An approved commit or reply mentioning `@tom` queues a reply. Pending mentions wait for approval. Tom uses the Laravel AI SDK with DeepSeek V4.1 Flash (`deepseek-v4.1-flash`) through OpenCode Zen's OpenAI-compatible API at `https://opencode.ai/zen/v1`, in non-thinking mode, with two read-only tools: `SearchKnowledgeBase` and `SearchCommits`. Commit searches run as a public viewer, never with access to the mention author's pending or rejected posts. Replies go through the normal commit creation, moderation, embedding and notification pipeline. Failures retry without logging provider response bodies; the same mention never gets a second reply, even if the first was deleted.

Continue by tagging Tom in a reply. He sees at most twelve public ancestor posts on that branch and at most 12,000 characters including the current question, not sibling branches, other threads or private messages. Each turn has three model steps, at most two calls per search tool, five commit excerpts per commit search, and three knowledge-base excerpts per knowledge search. He has no web or write tools. No OpenCode key means new mentions do not queue bot work.

Tom's job timeout is 600 seconds. The outbound request budget can reach 520 seconds: three model steps at up to 40 seconds each, four query embeddings at up to 70 seconds each, and two relevance checks at up to 60 seconds each. Model and embedding budgets include the SDK's one retry and up to ten seconds of backoff. The remaining time allows for database and queue work; normal replies do not wait for this ceiling.

The database, Redis and Beanstalkd connections default to a 660-second `retry_after`, keeping jobs reserved beyond Tom's timeout. If you override `DB_QUEUE_RETRY_AFTER`, `REDIS_QUEUE_RETRY_AFTER` or `BEANSTALKD_QUEUE_RETRY_AFTER`, keep the value at least 660 seconds; for SQS, configure its visibility timeout to at least 660 seconds. Restart workers after deploying these settings. This reservation applies to every job on the connection, so a worker crash can delay redelivery of other jobs until it expires.

#### Translation workers and failure recovery

Background language detection runs on the `translations` queue using the existing `QUEUE_CONNECTION`. Run a separate worker so these jobs cannot occupy the workers processing moderation:

```sh
php artisan queue:work --queue=translations --sleep=3 --tries=3 --timeout=120
```

Keep the existing default-queue workers running separately. No new environment variable is needed, and do not change the connection's default queue (`DB_QUEUE` or `REDIS_QUEUE`) to `translations`. For local development, run the same additional worker alongside `composer dev`. Explicit Translate requests still generate translations in the HTTP request, not in this queue.

On Laravel Cloud with database or Redis queues, add a worker cluster from the environment's infrastructure canvas, keep it awake, and add the command above under **Background processes → New background process → Custom worker**, initially with one process. Save and deploy the environment changes before or alongside the code that routes jobs to `translations`. Cloud restarts the workers on deployment. Each configured process runs on every cluster replica. See the [Cloud worker documentation](https://cloud.laravel.com/docs/workers).

If the environment already uses `QUEUE_CONNECTION=cloud`, create a standard managed queue named `translations` instead, leaving the existing default queue unchanged. Use Pro compute because this job's 120-second timeout exceeds managed Flex's 90-second limit. Do not introduce managed queues just to add this worker: creating the first managed queue changes the environment's default connection and requires the AWS SDK dependency. See the [managed queue documentation](https://cloud.laravel.com/docs/queues).

Detection and moderation make at most three attempts, with 60-second and 600-second backoffs. Translation allows 35 seconds to obtain its lock and up to 70 seconds for two 30-second provider attempts plus retry delay; its lock lasts 90 seconds after acquisition and its job timeout is 120 seconds. Moderation allows 180 seconds for up to three 15-second image/text checks and two 60-second reviews. Both job timeouts remain below the connection's 660-second reservation. Failed moderation stays pending for human review, never automatically approved. Search logs for `Moderation failed for commit`, `Translation failed for commit`, or `Language detection dispatch failed for commit`; errors retain exception types and codes, not provider messages or response bodies.

For database/Redis queues, retry individual failed jobs through `/operations` or `php artisan queue:retry <uuid>`. Existing queued and failed payloads retain their original queue and retry settings, so keep default workers running while those drain. For Cloud managed queues, use Cloud's **Monitoring → Queues** retry controls instead; Artisan failed-job commands do not support that driver. A missing job class still requires workers on the correct release before retrying: application-level catches cannot execute before the job has been deserialized.

#### Automatic production updates

The scheduler dispatches the `App\Jobs\EmbedKnowledgeBase` job every hour, so changes to `KNOWLEDGE_BASE.md` are embedded within an hour of being deployed. The job reads the file from the running release and only embeds new or changed sections, so runs with no changes make no OpenAI requests. Only one import per source runs at a time. A failed run is retried by the queue, leaves existing sections untouched, and is picked up again on the next hour.

Scheduled imports currently cover `KNOWLEDGE_BASE.md` only. If the knowledge base is split into more files, schedule the job for each source.

## License

Licensed under MIT, as declared in `composer.json`. Based on [Nuno Maduro's Laravel starter kit](https://github.com/nunomaduro/laravel-starter-kit-inertia-react).
