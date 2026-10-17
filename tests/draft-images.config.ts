import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite-plus';

export default defineConfig({
    resolve: {
        alias: [
            {
                find: '@',
                replacement: fileURLToPath(
                    new URL('../resources/js', import.meta.url),
                ),
            },
        ],
    },
    test: {
        // Inlined so the usePage mocks reach the translation hook.
        server: { deps: { inline: ['@erag/lang-sync-inertia'] } },
        include: [
            'tests/Unit/DraftStash.test.tsx',
            'tests/Unit/ComposerDraft.test.tsx',
            'tests/Unit/QuoteDraft.test.tsx',
            'tests/Unit/EmojiSearch.test.tsx',
            'tests/Unit/EmojiShortcodes.test.tsx',
            'tests/Unit/QuoteReply.test.tsx',
        ],
    },
});
