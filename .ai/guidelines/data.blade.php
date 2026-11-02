# Data guidelines

Assume the database has already been leaked. Every column, row, cache entry, log line, and file we keep is something an attacker already has. The safest data is the data we never stored.

## Store as little as possible

- Do not add a column, table, cache key, or file unless the feature cannot work without it. "Might be useful later", analytics, debugging, and convenience are not reasons to store anything.
- Derive instead of storing: counts, flags, and anything computable from data we already hold are queried or computed, never persisted. Caching a computation is fine when the cache entry has a TTL and holds nothing sensitive.
- Keep only what is shown. If a value is fetched from a third party (GitHub, OpenAI, a linked page) and never displayed or acted on, drop it on the spot instead of saving it "just in case".
- Prefer short-lived over permanent. Typing indicators, signature nonces, and sync markers live in the cache with a TTL, not in a table.
- Every new column needs three answers in the migration's pull request: why the feature cannot work without it, who is allowed to see it, and when it is deleted. If any answer is "not sure", do not add the column.

## Name timestamps after the moment

- Every date or time column ends in `_at` and names the moment it records: `created_at`, `updated_at`, `live_ends_at`, `onboarded_at`. Never `_since`, `_on`, `_date`, `_time`, or a bare noun, so every timestamp reads the same way across features.

## Never store secrets

- Never persist tokens, secrets, passwords, private keys, OAuth access or refresh tokens, API keys, session ids, raw signatures, or anything that grants access. The GitHub OAuth token is used for the sign-in request and discarded; it is never written anywhere.
- Credentials the app needs come from the environment (`config/services.php`), never from a table, a seeder, a test fixture, or a committed file.
- A short-lived secret that must be held between requests (the GitHub App installation token) goes in the cache, encrypted with `Crypt::encryptString()`, with a TTL shorter than its lifetime. Never in the database, never unencrypted.
- Never log or report a secret, a request body, a message body, an email address, or a full request. Log identifiers (user id, commit id, status code) and nothing else.
- Never put a secret in a job payload, a queued notification, a flash message, a URL, a cookie, or an Inertia prop.

## Encrypt sensitive content

- Use encrypted Eloquent casts for stored content that contains or can reasonably contain sensitive information: private message bodies, associated mentions, private free-text fields, and non-public personal or contact details.
- Ordinary operational metadata—timestamps, booleans, counters, foreign keys, status enums, and routine preferences—uses normal column types and casts. A field being user-specific or hidden from other users does not by itself require encryption.
- Judge sensitivity by the field's purpose and contents. Require encryption for metadata only when it directly records sensitive information; theoretical inferences alone do not make routine metadata sensitive.
- Encrypted content must not have an unencrypted duplicate for searching or indexing.

## Expose nothing by default

- Every model that reaches the frontend or the API declares what leaves the server: `#[Visible([...])]` with an explicit allowlist, or `#[Hidden([...])]` covering every column that is not public. New columns are hidden until someone proves they must be visible.
- Internal paths, ids of other users' private records, moderation verdicts, and third-party identifiers are never serialized. When a model needs a URL, expose the route (`CommitImage::url()`), not the storage path.
- Only the user themselves sees their own settings and hidden fields. Error and validation messages must not reveal another user's hidden state (preferences, blocks, pending content) either.

## Delete everything with the account

- `DeleteUser` erases everything stored about a person, files included. Any new table, column, or stored file that relates to a user must cascade on delete or be removed explicitly in `DeleteUser`, and be covered by `tests/Unit/Actions/DeleteUserTest.php`.
- Anything stored about a user is also included in `CreateExport`, except secrets like the remember token and session ids, which are never exported.
- Soft-deleted content keeps its body only for the undo window the feature needs; after that the body is gone, not merely hidden.

## Third parties and uploads

- Request the narrowest OAuth scopes and API permissions that work (public profile and public keys from GitHub, `issues:write` on one repository for the GitHub App). Never ask for access to a user's private data to make a feature slightly easier.
- Uploaded files are re-encoded, stored under a random name on a private disk, and served through an authorizing endpoint. Never keep the original upload, its original filename, or its metadata (EXIF, location).
- Before sending user content to a third party (OpenAI moderation and embeddings, GitHub issues), send only the text the call needs: no emails, ids, IP addresses, or other users' data.
