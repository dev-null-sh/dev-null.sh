import { execFileSync } from 'node:child_process';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

await test('renders attached gifs with provider attribution on commit cards', () => {
    const cli = fileURLToPath(
        new URL('./bin/vp', import.meta.resolve('vite-plus/package.json')),
    );

    execFileSync(
        process.execPath,
        [cli, 'test', 'run', '--config', 'tests/commit-gif.config.ts'],
        {
            cwd: fileURLToPath(new URL('../..', import.meta.url)),
            stdio: 'inherit',
        },
    );
});
