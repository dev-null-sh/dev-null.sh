# General Guidelines

- Don't include any superfluous PHP Annotations, except ones that start with `@` for typing variables.
- Every time you create a new file, stage it immediately with `git add <path>`. Never commit; leave committing to the user.
- Do not write browser tests (`tests/Browser`, Pest browser plugin, Playwright). Cover behavior with Feature and Unit tests only.
- Migrations only go up: never write a `down` method. To undo a migration, write a new one.
- Use the Laravel AI SDK for new AI features and semantic search query embeddings. Load the `ai-sdk-development` skill and keep the embedding provider, model, and dimensions compatible with the stored vectors.

## Minimal code

- Write the least code that does the job: no private helpers, extra cache layers, or defensive checks the feature doesn't need. If a method can be inlined or a branch removed without losing behavior, do it.
- Prefer Laravel collections and the framework's typed accessors over hand-written array handling: `collect(...)->map(...)` instead of building arrays in loops, `$response->collect()` / `$response->fluent()` with `->integer()` / `->string()` instead of `is_array` / `is_numeric` checks and `(int)` casts.

## Required verification

- After changes, before reporting completion or pushing, run `composer test:lint`, `composer test:type-coverage`, and `bun run test:unit`.
- Run the full PHP suite with code coverage using the CI command: `php -d memory_limit=-1 vendor/bin/pest --parallel --ci --coverage --exactly=100.0 --passthru-php="'-d' 'memory_limit=-1'"`. The `--ci` flag prevents local test-impact analysis from skipping tests. Targeted tests alone are not a substitute for the coverage check.
- Fix failures introduced by the changes. Never lower coverage thresholds or exclude code just to pass. Report pre-existing failures or environment blockers explicitly; do not claim a check passed when it failed or could not run.

## Product guide maintenance

- `PRODUCT.md` is the short product context agents design and build against: users, positioning, the rules every feature must keep, vocabulary, and what is deliberately excluded. It does not describe features in detail: limits and behavior belong in `KNOWLEDGE_BASE.md` and the code.
- Update `PRODUCT.md` only when a change adds a whole new area to its one-line feature list, changes one of its rules, or ships something it lists as excluded. Never leave a line that contradicts the code.

## Knowledge base maintenance

- Before finishing any change, check whether `KNOWLEDGE_BASE.md` needs updating to reflect it. Update the relevant sections in the same change when stack, hosting, repository details, platform behavior, or lore have changed; leave it alone when they have not.
- Keep technology descriptions free of version numbers, distinguish planned features from shipped behavior, and verify names, handles, and historical claims rather than guessing.
- Keep topics under descriptive Markdown headings: each non-empty section is embedded separately, with its parent headings providing context.
- The scheduler refreshes knowledge-base embeddings hourly in production. Do not manually run production embedding imports as part of an unrelated change.
