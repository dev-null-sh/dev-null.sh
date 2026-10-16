import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite-plus';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('../resources/js', import.meta.url)),
        },
    },
    test: {
        include: [
            'tests/Unit/SeriesStash.test.tsx',
            'tests/Unit/ComposerDraft.test.tsx',
            'tests/Unit/QuoteDraft.test.tsx',
        ],
    },
});
