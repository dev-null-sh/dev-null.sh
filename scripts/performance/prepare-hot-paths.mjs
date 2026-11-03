import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { build } from 'vite-plus';

const directory = resolve('node_modules/.cache/performance');
mkdirSync(directory, { recursive: true });
for (const name of [
    'graph',
    'chat-scroll',
    'certificate-hashes',
    'emoji',
    'conversation-search',
]) {
    writeFileSync(
        `${directory}/baseline-${name}.ts`,
        execFileSync('git', [
            'show',
            `${process.argv[2] ?? 'fb05d164'}:resources/js/lib/${name}.ts`,
        ]),
    );
    for (const version of ['baseline', 'current']) {
        await build({
            configFile: false,
            resolve: { alias: { '@': resolve('resources/js') } },
            build: {
                outDir: directory,
                emptyOutDir: false,
                lib: {
                    entry:
                        version === 'baseline'
                            ? `${directory}/baseline-${name}.ts`
                            : resolve(`resources/js/lib/${name}.ts`),
                    formats: ['es'],
                    fileName: () => `${version}-${name}.mjs`,
                },
            },
        });
    }
}
