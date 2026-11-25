import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { App } from '@inertiajs/react';
import type { ComponentProps, ReactElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { test, vi } from 'vite-plus/test';
import { isJumboMessage } from '../../resources/js/components/chat/message-parts';
import {
    CommitBody,
    CommitBodyExcerpt,
} from '../../resources/js/components/commit-body';
import { emojiOnlyCount, jumboEmojiCount } from '../../resources/js/lib/emoji';
import type { BodySegment } from '../../resources/js/types';

const text = (value: string): BodySegment[] => [{ type: 'text', text: value }];

for (const [body, count] of [
    ['🔥', 1],
    ['🎉🚀', 2],
    ['👩‍💻🇵🇹👍🏽', 3],
    [':tada:', 1],
    [' 🔥 \n🔥 ', 2],
    ['❤️', 1],
    ['1️⃣', 1],
    ['🎉🚀✨🙌', 0],
    ['nice 🔥', 0],
    ['1', 0],
    ['#', 0],
    ['©', 0],
    ['', 0],
    ['   ', 0],
] as const) {
    test(`counts ${JSON.stringify(body)} as ${count} jumbo emoji`, () => {
        assert.equal(jumboEmojiCount(text(body)), count);
    });
}

for (const [body, count] of [
    ['🎉🚀✨🙌', 4],
    ['🔥', 1],
    ['nice 🔥', 0],
    ['1', 0],
    ['', 0],
] as const) {
    test(`counts ${JSON.stringify(body)} as ${count} emoji with nothing else`, () => {
        assert.equal(emojiOnlyCount(text(body)), count);
    });
}

// The same cases tests/Unit/Services/JumboEmojiTest.php runs on the server's count, so web and API clients agree.
const sharedCases = JSON.parse(
    readFileSync(
        fileURLToPath(new URL('../Fixtures/jumbo-emoji.json', import.meta.url)),
        'utf8',
    ),
) as [string, number][];

for (const [body, count] of sharedCases) {
    test(`counts ${JSON.stringify(body)} as the server does`, () => {
        assert.equal(emojiOnlyCount(text(body)), count);
        assert.equal(jumboEmojiCount(text(body)), count <= 3 ? count : 0);
    });
}

test('never counts code as jumbo emoji', () => {
    assert.equal(jumboEmojiCount([{ type: 'code', code: '🔥' }]), 0);
});

test('never counts a body with more than one segment', () => {
    assert.equal(
        jumboEmojiCount([
            { type: 'text', text: '🔥' },
            { type: 'code', code: 'x' },
        ]),
        0,
    );
});

for (const missing of ['Intl.Segmenter', "the regex 'v' flag"]) {
    test(`shows emoji at their normal size in browsers without ${missing}`, async () => {
        vi.resetModules();

        if (missing === 'Intl.Segmenter') {
            vi.stubGlobal('Intl', { ...Intl, Segmenter: undefined });
        } else {
            vi.stubGlobal(
                'RegExp',
                class extends RegExp {
                    constructor(pattern: string | RegExp, flags?: string) {
                        if (flags?.includes('v')) {
                            throw new SyntaxError('Invalid flags');
                        }

                        super(pattern, flags);
                    }
                },
            );
        }

        try {
            const emoji = await import('../../resources/js/lib/emoji');

            assert.equal(emoji.jumboEmojiCount(text('🔥')), 0);
            assert.equal(emoji.emojiOnlyCount(text('🔥')), 0);
        } finally {
            vi.unstubAllGlobals();
            vi.resetModules();
        }
    });
}

test('counts a long body without reading it all', () => {
    assert.equal(emojiOnlyCount(text('🔥'.repeat(200))), 0);
    assert.equal(emojiOnlyCount(text(`${':tada:'.repeat(5)} hi`)), 0);
});

test('only remote messages with nothing but their text show jumbo emoji', () => {
    const message = (fields: object) =>
        ({ body: '🚀', body_segments: text('🚀'), ...fields }) as never;

    assert.equal(isJumboMessage(message({})), true);
    assert.equal(isJumboMessage(message({ images: [{ id: 'i' }] })), false);
    assert.equal(isJumboMessage(message({ voice: { id: 'v' } })), false);
    assert.equal(
        isJumboMessage(message({ body: 'Awesome', body_segments: undefined })),
        false,
    );
    assert.equal(
        isJumboMessage(message({ body: '🚀', body_segments: undefined })),
        true,
    );
});

function render(
    body: string,
    element?: ReactElement,
    shared: Record<string, unknown> = {},
): string {
    return renderToStaticMarkup(
        <App
            initialComponent={() =>
                element ?? <CommitBody segments={text(body)} mentions={null} />
            }
            initialPage={
                {
                    component: 'Preview',
                    url: '/',
                    version: null,
                    props: {
                        errors: {},
                        auth: { user: null, pasteis: null, isAdmin: false },
                        ...shared,
                    },
                    clearHistory: false,
                    encryptHistory: false,
                } as unknown as ComponentProps<typeof App>['initialPage']
            }
        />,
    );
}

test('marks an emoji-only body as jumbo', () => {
    assert.match(render('🔥'), /data-jumbo-emoji="1"/);
    assert.match(render('🎉🚀'), /data-jumbo-emoji="2"/);
});

test('leaves a body with words at its normal size', () => {
    assert.doesNotMatch(render('nice 🔥'), /data-jumbo-emoji/);
});

test('collapses the whitespace between jumbo emoji', () => {
    assert.match(render('🔥\n\n\n🔥'), /whitespace-normal/);
    assert.doesNotMatch(render('🔥\n\n\n🔥'), /whitespace-pre-wrap/);
    assert.match(render('nice 🔥'), /whitespace-pre-wrap/);
});

test('a body can opt out of jumbo emoji', () => {
    assert.doesNotMatch(
        render(
            '',
            <CommitBody
                segments={text('🔥')}
                mentions={null}
                isJumboable={false}
            />,
        ),
        /data-jumbo-emoji/,
    );
});

test('excerpts show emoji at their normal size unless they ask for jumbo, as quoted commits do', () => {
    assert.doesNotMatch(
        render('', <CommitBodyExcerpt segments={text('🔥')} />),
        /data-jumbo-emoji/,
    );
    assert.match(
        render('', <CommitBodyExcerpt segments={text('🔥')} isJumboable />),
        /data-jumbo-emoji="1"/,
    );
});

test('shows emoji at their normal size where large emoji are turned off on this device', () => {
    const off = { showsLargeEmoji: false };

    assert.doesNotMatch(render('🔥', undefined, off), /data-jumbo-emoji/);
    assert.match(
        render('🔥', undefined, { showsLargeEmoji: true }),
        /data-jumbo-emoji="1"/,
    );
    assert.doesNotMatch(
        render(
            '',
            <CommitBodyExcerpt segments={text('🔥')} isJumboable />,
            off,
        ),
        /data-jumbo-emoji/,
    );
});

test('follows the setting saved on this device over the one a restored page was shown with', () => {
    try {
        // Back restores a page's props as they were, from before the setting was turned off.
        vi.stubGlobal('document', { cookie: 'theme=dark; large_emoji=false' });
        assert.doesNotMatch(
            render('🔥', undefined, { showsLargeEmoji: true }),
            /data-jumbo-emoji/,
        );

        vi.stubGlobal('document', { cookie: 'large_emoji=true' });
        assert.match(
            render('🔥', undefined, { showsLargeEmoji: false }),
            /data-jumbo-emoji="1"/,
        );

        // With nothing saved yet, the page's own prop decides.
        vi.stubGlobal('document', { cookie: '' });
        assert.doesNotMatch(
            render('🔥', undefined, { showsLargeEmoji: false }),
            /data-jumbo-emoji/,
        );
    } finally {
        vi.unstubAllGlobals();
    }
});
