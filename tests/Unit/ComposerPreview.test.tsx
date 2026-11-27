/// <reference types="node" />
import assert from 'node:assert/strict';
import * as Inertia from '@inertiajs/react';
import { App } from '@inertiajs/react';
import type { ComponentProps, KeyboardEvent, ReactElement } from 'react';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { renderToStaticMarkup } from 'react-dom/server';
import { test, vi } from 'vite-plus/test';
import { CommitBody } from '../../resources/js/components/commit-body';
import { CommitComposer } from '../../resources/js/components/commit-composer';
import { revokeObjectUrls } from '../../resources/js/components/commit-image-picker';
import {
    ComposerPollPreview,
    ComposerPreviewBody,
    PreviewToggle,
    useComposerPreview,
    type ComposerPreview,
} from '../../resources/js/components/composer-preview';
import { hasMention } from '../../resources/js/components/linkified-text';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import { bodySegments } from '../../resources/js/lib/code';
import { minBodyLength } from '../../resources/js/lib/commit';

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual<typeof Inertia>('@inertiajs/react');

    return {
        ...actual,
        useHttp: vi.fn(actual.useHttp),
        usePage: vi.fn(actual.usePage),
    };
});

function mountedPreview(body: string, guildHandle?: string) {
    vi.stubGlobal('window', globalThis);
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    const focus = vi.fn();
    const editorFocus = vi.fn();
    vi.stubGlobal('document', { getElementById: () => ({ focus }) });
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) =>
        callback(),
    );
    const page = vi.spyOn(Inertia, 'usePage').mockReturnValue({
        props: { guildsEnabled: true, auth: { user: null, pasteis: null } },
    } as ReturnType<typeof Inertia.usePage>);
    const node = {
        nodeType: 1,
        nodeName: 'DIV',
        tagName: 'DIV',
        addEventListener: () => {},
        removeEventListener: () => {},
    };
    const root = createRoot({
        ...node,
        ownerDocument: {
            ...node,
            defaultView: {
                document: { activeElement: null },
                HTMLIFrameElement: class {},
            },
        },
    } as unknown as Element);
    let preview!: ComposerPreview;

    function Composer(props: { body: string; guildHandle?: string }) {
        preview = useComposerPreview({
            ...props,
            isSubmittable: false,
            textareaRef: {
                current: {
                    focus: editorFocus,
                } as unknown as HTMLTextAreaElement,
            },
        });
        return null;
    }

    act(() => root.render(<Composer body={body} guildHandle={guildHandle} />));

    return {
        get preview() {
            return preview;
        },
        focus,
        editorFocus,
        update: (body: string, guildHandle?: string) =>
            act(() =>
                root.render(<Composer body={body} guildHandle={guildHandle} />),
            ),
        unmount: () => {
            act(() => root.unmount());
            page.mockRestore();
            vi.unstubAllGlobals();
        },
    };
}

for (const change of ['draft', 'guild', 'unmount']) {
    test(`changing ${change} cancels the request and obsolete completion cannot end a newer lookup`, async () => {
        const responses: ((value: unknown) => void)[] = [];
        const cancel = vi.fn();
        const http = vi.spyOn(Inertia, 'useHttp').mockReturnValue({
            submit: () => new Promise((resolve) => responses.push(resolve)),
            cancel,
            transform: vi.fn(),
        } as unknown as ReturnType<typeof Inertia.useHttp>);
        const mounted = mountedPreview('#rust', 'rust');
        let opening!: Promise<void>;

        try {
            act(() => {
                opening = mounted.preview.toggle(true);
            });
            assert.equal(mounted.preview.isResolving, true);
            const calls = cancel.mock.calls.length;
            if (change === 'unmount') {
                mounted.unmount();
            } else {
                mounted.update(change === 'draft' ? '#jobs' : '#rust', 'jobs');
                assert.equal(mounted.preview.isResolving, false);
            }
            assert.equal(cancel.mock.calls.length, calls + 1);
            if (change !== 'unmount') {
                act(() => {
                    void mounted.preview.toggle(true);
                });
                assert.equal(mounted.preview.isResolving, true);
            }
            await act(async () => {
                responses[0]({
                    segments: bodySegments('#rust'),
                    mentions: {},
                    organization_mentions: {},
                    guild_mentions: {
                        rust: {
                            handle: 'rust',
                            name: 'Rust',
                            emoji: '🦀',
                            icon_url: null,
                        },
                    },
                });
                await opening;
            });
            assert.equal(mounted.focus.mock.calls.length, 0);
            if (change !== 'unmount') {
                assert.equal(mounted.preview.isResolving, true);
                assert.equal(mounted.preview.guildMentions, undefined);
                await act(async () =>
                    responses[1]({
                        segments: bodySegments('#jobs'),
                        mentions: {},
                        organization_mentions: {},
                        guild_mentions: {},
                    }),
                );
                assert.equal(mounted.preview.isResolving, false);
                assert.equal(mounted.preview.isPreviewing, true);
                assert.equal(mounted.focus.mock.calls.length, 1);
            }
        } finally {
            if (change !== 'unmount') {
                mounted.unmount();
            }
            http.mockRestore();
        }
    });
}

test('validation rejection previews an oversized draft locally and reopening does not retry', async () => {
    const body = 'a'.repeat(4000) + ' #rust';
    let finishOld!: (response: unknown) => void;
    const submit = vi.fn(
        (
            _route: unknown,
            options: { onError: (errors: Record<string, string>) => void },
        ) => {
            if (submit.mock.calls.length === 1) {
                return new Promise((resolve) => {
                    finishOld = resolve;
                });
            }

            options.onError({ body: 'The body is too long.' });
            return Promise.reject(new Error('Validation failed'));
        },
    );
    const http = vi.spyOn(Inertia, 'useHttp').mockReturnValue({
        submit,
        cancel: vi.fn(),
        transform: vi.fn(),
    } as unknown as ReturnType<typeof Inertia.useHttp>);
    const mounted = mountedPreview('#rust');

    try {
        let oldOpening!: Promise<void>;
        act(() => {
            oldOpening = mounted.preview.toggle(true);
        });
        mounted.update(body);
        await act(async () => {
            await mounted.preview.toggle(true);
        });
        assert.equal(mounted.preview.isPreviewing, true);
        assert.equal(mounted.preview.isResolving, false);
        await act(async () => {
            finishOld({
                segments: bodySegments('#rust'),
                mentions: {},
                organization_mentions: {},
                guild_mentions: {
                    rust: {
                        handle: 'rust',
                        name: 'Rust',
                        emoji: '🦀',
                        icon_url: null,
                    },
                },
            });
            await oldOpening;
        });
        assert.deepEqual(mounted.preview.segments, bodySegments(body));
        assert.deepEqual(mounted.preview.guildMentions, {});
        assert.equal(mounted.focus.mock.calls.length, 1);
        const markup = renderToStaticMarkup(
            <ComposerPreviewBody
                preview={mounted.preview}
                images={[]}
                url={null}
            />,
        );
        assert.doesNotMatch(markup, /data-test="guild-mention"/);
        assert.match(markup, /#rust/);
        act(() => mounted.preview.close());
        await act(async () => {
            await mounted.preview.toggle(true);
        });
        assert.equal(submit.mock.calls.length, 2);
    } finally {
        mounted.unmount();
        http.mockRestore();
    }
});

test('a no-space quoted hashtag waits for its guild badge before opening preview', async () => {
    let finish!: (response: unknown) => void;
    const submit = vi.fn(
        () =>
            new Promise((resolve) => {
                finish = resolve;
            }),
    );
    const http = vi.spyOn(Inertia, 'useHttp').mockReturnValue({
        submit,
        cancel: vi.fn(),
        transform: vi.fn(),
    } as unknown as ReturnType<typeof Inertia.useHttp>);
    const mounted = mountedPreview('>#rust');

    try {
        let opening!: Promise<void>;
        act(() => {
            opening = mounted.preview.toggle(true);
        });
        assert.equal(mounted.preview.isResolving, true);
        assert.equal(mounted.preview.isPreviewing, false);
        const guild = {
            handle: 'rust',
            name: 'Rustaceans',
            emoji: '🦀',
            icon_url: null,
        };
        await act(async () => {
            finish({
                segments: bodySegments('>#rust'),
                mentions: {},
                organization_mentions: {},
                guild_mentions: { rust: guild },
            });
            await opening;
        });
        assert.equal(mounted.preview.isPreviewing, true);
        assert.equal(mounted.focus.mock.calls.length, 1);
        const markup = renderToStaticMarkup(
            <ComposerPreviewBody
                preview={mounted.preview}
                images={[]}
                url={null}
            />,
        );
        assert.match(markup, /<blockquote/);
        assert.match(markup, /data-test="guild-mention"/);
        assert.match(markup, /Rustaceans/);
    } finally {
        mounted.unmount();
        http.mockRestore();
    }
});

test('network failure returns focus to writing and permits a deliberate retry without duplicate activations', async () => {
    let reject!: (reason: Error) => void;
    const submit = vi.fn(
        () =>
            new Promise((_resolve, fail) => {
                reject = fail;
            }),
    );
    const http = vi.spyOn(Inertia, 'useHttp').mockReturnValue({
        submit,
        cancel: vi.fn(),
        transform: vi.fn(),
    } as unknown as ReturnType<typeof Inertia.useHttp>);
    const mounted = mountedPreview('#rust');

    try {
        let opening!: Promise<void>;
        act(() => {
            opening = mounted.preview.toggle(true);
        });
        const markup = renderToStaticMarkup(
            <TooltipProvider>
                <PreviewToggle preview={mounted.preview} />
            </TooltipProvider>,
        );
        assert.match(markup, /aria-disabled="true"/);
        assert.doesNotMatch(markup, / disabled="/);
        await act(async () => {
            await mounted.preview.toggle(true);
        });
        assert.equal(submit.mock.calls.length, 1);
        await act(async () => {
            reject(new Error('Offline'));
            await opening;
        });
        assert.equal(mounted.preview.isPreviewing, false);
        assert.equal(mounted.preview.isResolving, false);
        assert.equal(mounted.editorFocus.mock.calls.length, 1);
        assert.equal(mounted.focus.mock.calls.length, 0);
        act(() => {
            void mounted.preview.toggle(true);
        });
        assert.equal(submit.mock.calls.length, 2);
    } finally {
        mounted.unmount();
        http.mockRestore();
    }
});

for (const body of [
    'https://example.com/#rust',
    'example.com?q=#jobs',
    'color(#abc) color(#abcd) color(#abcdef) color(#abcdefab)',
    '>color(#fff)',
]) {
    test(`preview opens without a guild lookup for renderer-consumed text: ${body}`, async () => {
        const submit = vi.fn();
        const http = vi.spyOn(Inertia, 'useHttp').mockReturnValue({
            submit,
            cancel: vi.fn(),
            transform: vi.fn(),
        } as unknown as ReturnType<typeof Inertia.useHttp>);
        const mounted = mountedPreview(body);

        try {
            await act(async () => {
                await mounted.preview.toggle(true);
            });
            assert.equal(submit.mock.calls.length, 0);
            assert.equal(mounted.preview.isPreviewing, true);
            assert.equal(mounted.focus.mock.calls.length, 1);
        } finally {
            mounted.unmount();
            http.mockRestore();
        }
    });
}

function renderPage(
    content: ReactElement,
    pasteisMode = false,
    isAdmin = false,
    guildsEnabled = false,
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
                        guildsEnabled,
                        auth: {
                            organizations: [],
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

function previewFor(
    options: {
        body?: string;
        isSubmittable: boolean;
    },
    guildsEnabled = false,
): ComposerPreview {
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

    renderPage(<Composer />, false, false, guildsEnabled);
    const preview = previews[0];
    assert.ok(preview);

    return preview;
}

test('pasted hashtag preview waits for metadata and close discards a stale response', async () => {
    let finish!: (response: unknown) => void;
    const submit = vi.fn(
        () =>
            new Promise((resolve) => {
                finish = resolve;
            }),
    );
    const cancel = vi.fn();
    const http = vi.spyOn(Inertia, 'useHttp').mockReturnValue({
        submit,
        cancel,
        transform: vi.fn(),
    } as unknown as ReturnType<typeof Inertia.useHttp>);
    const focus = vi.fn();
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) =>
        callback(),
    );

    try {
        const preview = previewFor(
            { body: '#JoBs', isSubmittable: true },
            true,
        );
        vi.stubGlobal('document', { getElementById: () => ({ focus }) });
        const opening = preview.toggle(true);

        assert.equal(focus.mock.calls.length, 0);
        assert.equal(submit.mock.calls.length, 1);
        preview.close();
        assert.equal(cancel.mock.calls.length, 2);
        finish({
            segments: bodySegments('#jobs'),
            mentions: {},
            organization_mentions: {},
            guild_mentions: {
                jobs: {
                    handle: 'jobs',
                    name: 'Jobs',
                    emoji: '💼',
                    icon_url: null,
                },
            },
        });
        await opening;
        assert.equal(focus.mock.calls.length, 0);
    } finally {
        http.mockRestore();
        vi.unstubAllGlobals();
    }
});

test('pasted hashtag preview moves focus only after successful metadata lookup', async () => {
    let finish!: (response: unknown) => void;
    const http = vi.spyOn(Inertia, 'useHttp').mockReturnValue({
        submit: () =>
            new Promise((resolve) => {
                finish = resolve;
            }),
        cancel: vi.fn(),
        transform: vi.fn(),
    } as unknown as ReturnType<typeof Inertia.useHttp>);
    const focus = vi.fn();
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) =>
        callback(),
    );

    try {
        const preview = previewFor(
            { body: '#jobs', isSubmittable: true },
            true,
        );
        vi.stubGlobal('document', { getElementById: () => ({ focus }) });
        const opening = preview.toggle(true);

        assert.equal(focus.mock.calls.length, 0);
        finish({
            segments: bodySegments('#jobs'),
            mentions: {},
            organization_mentions: {},
            guild_mentions: {},
        });
        await opening;
        assert.equal(focus.mock.calls.length, 1);
    } finally {
        http.mockRestore();
        vi.unstubAllGlobals();
    }
});

test('failed metadata lookup keeps the editor instead of revealing unresolved badges', async () => {
    const focus = vi.fn();
    const http = vi.spyOn(Inertia, 'useHttp').mockReturnValue({
        submit: () => Promise.reject(new Error('Unavailable')),
        cancel: vi.fn(),
        transform: vi.fn(),
    } as unknown as ReturnType<typeof Inertia.useHttp>);
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) =>
        callback(),
    );

    try {
        const preview = previewFor(
            { body: '#jobs', isSubmittable: true },
            true,
        );
        vi.stubGlobal('document', { getElementById: () => ({ focus }) });
        await preview.toggle(true);

        assert.equal(focus.mock.calls.length, 0);
    } finally {
        http.mockRestore();
        vi.unstubAllGlobals();
    }
});

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
                                organizations: [],
                                user: {
                                    id: 'preview-user',
                                    name: 'Preview User',
                                    handle: 'previewuser',
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

for (const fence of [
    '``` php\ttitle="app/Models/User.php" \t',
    '```php filename="app/Models/User.php"',
    '``` title="app/Models/User.php"',
]) {
    for (const isStatic of [false, true]) {
        test(`labels the code block of "${fence}" with its filename in ${isStatic ? 'excerpts' : 'commits'}`, () => {
            const markup = renderPage(
                <CommitBody
                    segments={bodySegments(`${fence}\n$name;\n\`\`\``)}
                    mentions={null}
                    isStatic={isStatic}
                />,
            );

            assert.match(
                markup,
                /<span[^>]*title="app\/Models\/User\.php"[^>]*>app\/Models\/User\.php<\/span>/,
            );
            assert.ok(!markup.includes('>php<'));
        });
    }
}

for (const [fence, language, filename] of [
    ['``` php\ttitle="app/Models/User.php" \t', 'php', 'app/Models/User.php'],
    ['```php filename="app/Models/User.php"', 'php', 'app/Models/User.php'],
    ['```php filename="a.php" title="b.php"', 'php', 'b.php'],
    ['```php title="" filename="a.php"', 'php', 'a.php'],
    ['```php title="a.php" title="b.php"', 'php', 'a.php'],
    ['```php title="My Model.php"', 'php', 'My Model.php'],
    [
        '```php {1, 3} showLineNumbers title="app.php" tab="Pest 4"',
        'php',
        'app.php',
    ],
    ['```php app/Models/User.php', 'php', null],
    ['```php {1,3}', 'php', null],
    ['```php tab=Pest', 'php', null],
    ['```php title=""', 'php', null],
    ['```php title="app.php', 'php', null],
    ['```php caption="see title="app.php"', 'php', null],
    ['```php {1 title="app.php"}', 'php', null],
    ['```php{1,3} title="app.php"', 'php', 'app.php'],
    ["```php title='app.php'", 'php', 'app.php'],
    ["```php filename='app.php'", 'php', 'app.php'],
    ['```php title="it\'s.php"', 'php', "it's.php"],
    ['```php title=\'"a".php\'', 'php', '"a".php'],
    ["```php title=''", 'php', null],
    ["```php title='app.php", 'php', null],
    ["``` title='app.php'", null, 'app.php'],
    ['```php{1,3}', 'php', null],
    ['``` title="app.php"', null, 'app.php'],
    ['```title="app.php"', null, 'app.php'],
    ['```{1,3}', null, null],
    ['``` {1,3} title="app.php"', null, 'app.php'],
    ['```php\u00a0title="app.php"', 'php\u00a0title="app.php"', null],
    ['```php', 'php', null],
    ['```', null, null],
] as const) {
    test(`reads the language and filename of "${fence}" like the server`, () => {
        assert.deepEqual(bodySegments(`${fence}\n$name;\n\`\`\``), [
            { type: 'block', language, filename, html: '$name;' },
        ]);
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

test('links mentions by handle until the server has found them', () => {
    const preview = previewFor({
        body: 'Pairing with @alice',
        isSubmittable: true,
    });
    const markup = renderPage(
        <ComposerPreviewBody preview={preview} images={[]} url={null} />,
    );

    assert.equal(preview.mentions, null);
    assert.match(
        markup,
        /Pairing with <a[^>]*href="\/@alice"[^>]*>@alice<\/a>/,
    );
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

        test(`preserves code, URLs, handles and larger words with mode ${pasteisMode ? 'on' : 'off'} in ${isStatic ? 'excerpts' : 'commits'}`, () => {
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

test('clicking Preview moves focus into the preview, where Escape returns to writing', () => {
    const preview = previewFor({ isSubmittable: true });
    const focus = vi.fn();
    const getElementById = vi.fn(() => ({ focus }));
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) =>
        callback(),
    );
    vi.stubGlobal('document', { getElementById });

    try {
        const toggles: ReactElement<{
            children: ReactElement<{ onClick: () => void }>;
        }>[] = [];

        function Toggle() {
            toggles.push(
                PreviewToggle({ preview }) as (typeof toggles)[number],
            );

            return null;
        }

        renderToStaticMarkup(<Toggle />);
        const tooltip = toggles[0];
        assert.ok(tooltip);
        tooltip.props.children.props.onClick();

        assert.deepEqual(getElementById.mock.calls, [[preview.previewId]]);
        assert.equal(focus.mock.calls.length, 1);
    } finally {
        vi.unstubAllGlobals();
    }
});
