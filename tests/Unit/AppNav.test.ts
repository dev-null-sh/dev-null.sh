import { execFileSync } from 'node:child_process';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

await test('navigation preserves guest and moderation active states', () => {
    execFileSync('bun', ['test', 'tests/Unit/AppNav.test.tsx'], {
        cwd: fileURLToPath(new URL('../..', import.meta.url)),
        stdio: 'inherit',
        env: { ...process.env, NODE_TEST_CONTEXT: undefined },
    });
});
