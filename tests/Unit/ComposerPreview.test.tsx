/// <reference types="node" />
import assert from 'node:assert/strict';
import { App } from '@inertiajs/react';
import type { ComponentProps, KeyboardEvent, ReactElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { CommitBody } from '../../resources/js/components/commit-body';
import { CommitComposer } from '../../resources/js/components/commit-composer';
import { revokeObjectUrls } from '../../resources/js/components/commit-image-picker';
import {
    ComposerPollPreview,
    ComposerPreviewBody,
    useComposerPreview,
    type ComposerPreview,
} from '../../resources/js/components/composer-preview';
import { hasMention } from '../../resources/js/components/linkified-text';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import { bodySegments } from '../../resources/js/lib/code';
import { minBodyLength } from '../../resources/js/lib/commit';

function renderPage(
    content: ReactElement,
    pasteisMode = false,
    isAdmin = false,
): string {
    return renderToStaticMarkup(
        <App
            initialComponent={() => (
                <TooltipProvider>{content}</TooltipProvider>
            )}
            initialPage={
                {
                    component: 'Preview',
                    url: '/',
                    version: null,
                    props: {
                        errors: {},
                        auth: {
                            user: null,
                            pasteis: pasteisMode
                                ? { remaining: 0, day: '2026-10-16' }
                                : null,
                            isAdmin,
                        },
                    },
                    clearHistory: false,
                    encryptHistory: false,
                } as unknown as ComponentProps<typeof App>['initialPage']
            }
        />,
    );
}

function previewFor(options: {
    body?: string;
    isSubmittable: boolean;
}): ComposerPreview {
    const previews: ComposerPreview[] = [];

    function Composer() {
        previews.push(
            useComposerPreview({
                body: 'Draft commit',
                textareaRef: { current: null },
                ...options,
            }),
        );

        return null;
    }

    renderToStaticMarkup(<Composer />);
    const preview = previews[0];
    assert.ok(preview);

    return preview;
}

for (const modifier of ['ctrlKey', 'metaKey'] as const) {
    for (const shiftKey of [false, true]) {
        test(`${modifier} + ${shiftKey ? 'Shift + ' : ''}Enter publishes from preview`, () => {
            let submitted = 0;
            let prevented = 0;
            const preview = previewFor({ isSubmittable: true });

            preview.onPreviewKeyDown({
                key: 'Enter',
                [modifier]: true,
                shiftKey,
                preventDefault: () => prevented++,
                currentTarget: {
                    closest: () => ({ requestSubmit: () => submitted++ }),
                },
            } as unknown as KeyboardEvent<HTMLDivElement>);

            assert.equal(submitted, 1);
            assert.equal(prevented, 1);
        });
    }
}

test('does not publish an incomplete draft from preview', () => {
    previewFor({ isSubmittable: false }).onPreviewKeyDown({
        key: 'Enter',
        ctrlKey: true,
        shiftKey: false,
        preventDefault: () => assert.fail('must not submit'),
    } as unknown as KeyboardEvent<HTMLDivElement>);
});

function renderComposer(body: string, isInDialog = false): string {
    const windowDescriptor = Object.getOwnPropertyDescriptor(
        globalThis,
        'window',
    );
    const draft = new Map([['stash:preview-user:commit', body]]);

    function ComposerPage() {
        Object.defineProperty(globalThis, 'window', {
            configurable: true,
            value: {
                location: new URL('http://localhost/'),
                localStorage: {
                    getItem: (key: string) => draft.get(key) ?? null,
                },
            },
        });

        return (
            <TooltipProvider>
                <CommitComposer isInDialog={isInDialog} />
            </TooltipProvider>
        );
    }

    try {
        return renderToStaticMarkup(
            <App
                initialComponent={ComposerPage}
                initialPage={
                    {
                        component: 'Composer',
                        url: '/',
                        version: null,
                        props: {
                            errors: {},
                            auth: {
                                user: {
                                    id: 'preview-user',
                                    name: 'Preview User',
                                    username: 'previewuser',
                                    lane: 0,
                                },
                                log: {},
                            },
                        },
                        clearHistory: false,
                        encryptHistory: false,
                    } as unknown as ComponentProps<typeof App>['initialPage']
                }
            />,
        );
    } finally {
        if (windowDescriptor) {
            Object.defineProperty(globalThis, 'window', windowDescriptor);
        } else {
            Reflect.deleteProperty(globalThis, 'window');
        }
    }
}

for (const count of [1, 2, 3, 3850]) {
    test(`counts ${count} emoji as Unicode characters in the preview composer`, () => {
        const markup = renderComposer('🚀'.repeat(count));
        const submit = markup.match(
            /<button[^>]*data-test="create-commit-button"[^>]*>/,
        )?.[0];

        assert.ok(submit);
        assert.equal(
            /\sdisabled(?:=|\s|>)/.test(submit),
            count < minBodyLength,
        );
        assert.equal(
            markup.includes(`aria-label="${4000 - count} characters left"`),
            count === 3850,
        );
        assert.ok(markup.includes('Preview'));
    });
}

test('links the composer avatar to the author profile', () => {
    const markup = renderComposer('');
    const avatar = markup.match(/<a[^>]*href="\/@previewuser"[^>]*>/)?.[0];

    assert.ok(avatar);
    assert.ok(avatar.includes('aria-label="View your profile"'));
});

test('keeps the composer avatar unlinked inside the dialog', () => {
    const markup = renderComposer('', true);

    assert.ok(!markup.includes('href="/@previewuser"'));
});

test('previews the chosen link card instead of the first link in the body', () => {
    const preview = previewFor({
        body: 'https://first.example.com https://second.example.com',
        isSubmittable: true,
    });
    const markup = renderPage(
        <ComposerPreviewBody
            preview={preview}
            images={[]}
            url="https://second.example.com"
        />,
    );
    const card = markup.match(/data-test="link-card-preview"[\s\S]*/)?.[0];

    assert.ok(card);
    assert.ok(card.includes('second.example.com'));
    assert.ok(!card.includes('first.example.com'));
});

test('omits the preview card when the author chooses no card', () => {
    const preview = previewFor({
        body: 'https://first.example.com',
        isSubmittable: true,
    });
    const markup = renderPage(
        <ComposerPreviewBody preview={preview} images={[]} url={null} />,
    );

    assert.ok(markup.includes('href="https://first.example.com"'));
    assert.ok(!markup.includes('data-test="link-card-preview"'));
});

test('retains the link card picker and scheduling beside preview', () => {
    const markup = renderComposer(
        'https://first.example.com https://second.example.com',
    );
    const schedule = markup.match(
        /<button[^>]*aria-controls="body-schedule"[^>]*>/,
    )?.[0];

    assert.equal(
        (markup.match(/data-test="link-card-button"/g) ?? []).length,
        1,
    );
    assert.equal((markup.match(/data-test="preview-toggle"/g) ?? []).length, 1);
    assert.ok(schedule);
    assert.ok(!/\sdisabled(?:=|\s|>)/.test(schedule));
});

const secret = 'ghp_' + 'Ab3dEf6hIj9kLm2nOp5qRs8tUv1wXy4zABCD';

test('keeps token-shaped text editable with preview and no secret warning', () => {
    const markup = renderComposer(secret);

    assert.equal(
        (markup.match(/aria-label="Sensitive text warning"/g) ?? []).length,
        0,
    );
    assert.equal((markup.match(/data-test="preview-toggle"/g) ?? []).length, 1);
    assert.ok(markup.includes(secret));
    assert.ok(!markup.includes('Remove detected secrets'));
    assert.ok(!markup.includes('data-test="secret-warning-minimize"'));
    assert.ok(!markup.includes('Publish possible secrets?'));
});

test('ordinary drafts retain preview without a sensitive text warning', () => {
    const markup = renderComposer('Shipped a small fix');
    assert.ok(markup.includes('data-test="preview-toggle"'));
    assert.ok(!markup.includes('aria-label="Sensitive text warning"'));
});

for (const isAdmin of [false, true]) {
    test(`previews a poll draft as ${isAdmin ? 'an open' : 'a pending'} poll`, () => {
        const markup = renderPage(
            <ComposerPollPreview
                poll={{
                    options: ['Bun :rocket:', '  ', 'Deno'],
                    duration: 4320,
                }}
            />,
            false,
            isAdmin,
        );
        const poll = markup.match(/data-test="commit-poll"[\s\S]*/)?.[0];

        assert.ok(poll);
        assert.equal((poll.match(/<li/g) ?? []).length, 2);
        assert.ok(poll.includes('Bun 🚀'));
        assert.ok(poll.includes('Deno'));
        assert.ok(!poll.includes('<input'));
        assert.ok(
            poll.includes(isAdmin ? '3d left' : 'Voting opens once approved'),
        );
    });
}

for (const [body, expected] of [
    ['Pairing with @alice', true],
    ['@bob-builder, take a look', true],
    ['Mail me at alice@example.com', false],
    ['Run `@alice` in the shell', false],
    ['```java\n@Override\n```', false],
    ['Install @inertiajs/react and @vue.js', false],
] as const) {
    test(`${expected ? 'finds' : 'finds no'} mention in "${body}"`, () => {
        assert.equal(hasMention(bodySegments(body)), expected);
    });
}

test('links the mentions the server found in the preview', () => {
    const preview = previewFor({
        body: 'Pairing with @alice and @nobody-here',
        isSubmittable: true,
    });
    const markup = renderPage(
        <ComposerPreviewBody
            preview={{
                ...preview,
                mentions: { alice: '019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b' },
            }}
            images={[]}
            url={null}
        />,
    );

    assert.equal((markup.match(/data-test="mention"/g) ?? []).length, 1);
    assert.match(markup, /<a[^>]*>@alice<\/a>/);
    assert.ok(markup.includes('@nobody-here'));
});

test('shows mentions as text until the server has found them', () => {
    const preview = previewFor({
        body: 'Pairing with @alice',
        isSubmittable: true,
    });
    const markup = renderPage(
        <ComposerPreviewBody preview={preview} images={[]} url={null} />,
    );

    assert.equal(preview.mentions, null);
    assert.ok(markup.includes('Pairing with @alice'));
    assert.ok(!markup.includes('data-test="mention"'));
});

test('previews attached image descriptions without a published photo link', () => {
    const images = [
        new File(['first'], 'first.png', { type: 'image/png' }),
        new File(['second'], 'second.png', { type: 'image/png' }),
    ];

    try {
        const markup = renderPage(
            <ComposerPreviewBody
                preview={previewFor({ isSubmittable: true })}
                images={images}
                descriptions={['  Build output in the terminal  ', '']}
                url={null}
            />,
        );

        assert.ok(markup.includes('alt="Build output in the terminal"'));
        assert.ok(markup.includes('alt="Attached image 2 of 2"'));
        assert.ok(markup.includes('data-test="commit-images"'));
        assert.ok(!markup.includes('<a '));
    } finally {
        revokeObjectUrls(images);
    }
});

for (const pasteisMode of [false, true]) {
    for (const isStatic of [false, true]) {
        test(`renders pastel words as ${pasteisMode ? 'emoji' : 'text'} in ${isStatic ? 'excerpts' : 'commit previews'}`, () => {
            const body =
                'pastel, pasteis! (pastéis) PASTEL PASTEIS PASTÉIS :rocket:';
            const preview = previewFor({ body, isSubmittable: true });
            const markup = renderPage(
                isStatic ? (
                    <CommitBody
                        segments={preview.segments}
                        mentions={null}
                        isStatic
                    />
                ) : (
                    <ComposerPreviewBody
                        preview={preview}
                        images={[]}
                        url={null}
                    />
                ),
                pasteisMode,
            );

            assert.equal(
                (markup.match(/<img /g) ?? []).length,
                pasteisMode ? 6 : 0,
            );
            assert.ok(markup.includes('🚀'));
            assert.equal(preview.draft, body);
            if (pasteisMode) {
                for (const word of [
                    'pastel',
                    'pasteis',
                    'pastéis',
                    'PASTEL',
                    'PASTEIS',
                    'PASTÉIS',
                ]) {
                    assert.ok(markup.includes(`alt="${word}"`));
                }
            } else {
                assert.ok(
                    markup.includes(
                        'pastel, pasteis! (pastéis) PASTEL PASTEIS PASTÉIS',
                    ),
                );
            }
        });

        test(`preserves code, URLs, usernames and larger words with mode ${pasteisMode ? 'on' : 'off'} in ${isStatic ? 'excerpts' : 'commits'}`, () => {
            const preview = previewFor({
                body: 'pastel https://pasteis.com/pastel @pastel pastels pastelaria épastel pastelé pastel_1\n`pasteis`\n```text\npastéis\n```',
                isSubmittable: true,
            });
            const markup = renderPage(
                <CommitBody
                    segments={preview.segments}
                    mentions={{
                        pastel: '019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b',
                    }}
                    isStatic={isStatic}
                />,
                pasteisMode,
            );

            assert.equal(
                (markup.match(/<img /g) ?? []).length,
                pasteisMode ? 1 : 0,
            );
            assert.ok(markup.includes('@pastel'));
            assert.ok(
                markup.includes('pastels pastelaria épastel pastelé pastel_1'),
            );
            assert.match(markup, /<code[^>]*>pasteis<\/code>/);
            assert.match(markup, /<pre[^>]*>pastéis<\/pre>/);
            assert.equal(
                markup.includes('href="https://pasteis.com/pastel"'),
                !isStatic,
            );
            assert.equal(markup.includes('data-test="mention"'), !isStatic);
            if (isStatic) {
                assert.ok(markup.includes('https://pasteis.com/pastel'));
                assert.ok(!markup.includes('<a '));
            }
        });
    }
}
