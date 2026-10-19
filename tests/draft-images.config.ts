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
            'tests/Unit/ActivityCells.test.tsx',
            'tests/Unit/AuthorHover.test.tsx',
            'tests/Unit/FollowState.test.tsx',
            'tests/Unit/GuestFollow.test.tsx',
            'tests/Unit/DraftStash.test.tsx',
            'tests/Unit/DraftImageReads.test.tsx',
            'tests/Unit/ImageAttachments.test.tsx',
            'tests/Unit/ComposerDraft.test.tsx',
            'tests/Unit/QuoteDraft.test.tsx',
            'tests/Unit/EmojiSearch.test.tsx',
            'tests/Unit/EmojiShortcodes.test.tsx',
            'tests/Unit/QuoteReply.test.tsx',
            'tests/Unit/SearchDialogComposition.test.tsx',
            'tests/Unit/TabSlide.test.tsx',
            'tests/Unit/ReportResolution.test.tsx',
            'tests/Unit/LinkifiedText.test.tsx',
        ],
    },
});
