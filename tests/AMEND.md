# Amendment verification

Run the normal PHP suite, `bun run test:unit`, and the Vitest component tests in
`tests/Unit/AmendCommit.test.tsx`. The project also has component tests using
`node:test`; do not run those files through Vitest.

PHPUnit forces the testing environment and database name. `Tests\TestCase` checks
the effective connection before `RefreshDatabase` can run and refuses anything
except `testing` or the parallel `testing_test_N` databases. Cached local config
therefore fails closed instead of wiping development data.

## Browser smoke tests

With the local development server running and Playwright Chromium installed:

```
TEST_USERNAME=your-local-user node tests/Browser/amend.mjs
WIDTH=390 TEST_USERNAME=your-local-user node tests/Browser/amend.mjs
SCENARIO=expired TEST_USERNAME=your-local-user node tests/Browser/amend.mjs
SCENARIO=secret TEST_USERNAME=your-local-user node tests/Browser/amend.mjs
SCENARIO=thread TEST_USERNAME=your-local-user node tests/Browser/amend.mjs
# The thread scenario uses TEST_USERNAME as a moderator to approve its fixture reply.
```

Optional `CHROMIUM_PATH` selects an installed Chromium executable; `TEST_URL`
defaults to `http://127.0.0.1:8000`. These checks create local commits. Never point
them at production. They verify caret position, saving, public-version history,
reload persistence, retained expired drafts, and secret confirmation.

## PostgreSQL concurrency

Run `php tests/Concurrency/amend.php` with `APP_ENV=testing` and an explicit
PostgreSQL connection to the migrated `testing` database. The script refuses
other database/environment names. It requires `pcntl` and checks both winning
orders for replies and quotes using separate processes and actual row locks.
Do not run it while another process is resetting that database.

Type coverage measures type declarations, not executed code. Runtime PHP
coverage requires a supported coverage driver.
