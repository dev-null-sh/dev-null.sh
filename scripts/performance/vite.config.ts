import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite-plus';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('../../resources/js', import.meta.url)),
        },
    },
    test: {
        env: { VITE_APP_NAME: '/dev/null' },
        include: [
            'tests/Unit/LcpImages.test.tsx',
            'tests/Unit/UseNow.test.tsx',
            'tests/Unit/EmojiSearch.test.tsx',
            'tests/Unit/EmojiShortcodes.test.tsx',
        ],
    },
});
