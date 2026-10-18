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

#### Automatic production updates

The scheduler dispatches the `App\Jobs\EmbedKnowledgeBase` job every hour, so changes to `KNOWLEDGE_BASE.md` are embedded within an hour of being deployed. The job reads the file from the running release and only embeds new or changed sections, so runs with no changes make no OpenAI requests. Only one import per source runs at a time. A failed run is retried by the queue, leaves existing sections untouched, and is picked up again on the next hour.

Scheduled imports currently cover `KNOWLEDGE_BASE.md` only. If the knowledge base is split into more files, schedule the job for each source.

## License

Licensed under MIT, as declared in `composer.json`. Based on [Nuno Maduro's Laravel starter kit](https://github.com/nunomaduro/laravel-starter-kit-inertia-react).
