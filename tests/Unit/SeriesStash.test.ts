import { execFileSync } from 'node:child_process';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

await test('draft images survive editing, restoration, and sensitive text confirmation', () => {
    const cli = fileURLToPath(
        new URL('./bin/vp', import.meta.resolve('vite-plus/package.json')),
    );

    execFileSync(
        process.execPath,
        [cli, 'test', 'run', '--config', 'tests/draft-images.config.ts'],
        {
            cwd: fileURLToPath(new URL('../..', import.meta.url)),
            stdio: 'inherit',
        },
    );
});
