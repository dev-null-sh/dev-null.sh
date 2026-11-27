/// <reference types="node" />
import assert from 'node:assert/strict';
import * as Inertia from '@inertiajs/react';
import { App } from '@inertiajs/react';
import type { ComponentProps, ReactElement } from 'react';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { renderToStaticMarkup } from 'react-dom/server';
import { test, vi } from 'vite-plus/test';
import { CommitPoll } from '../../resources/js/components/commit-poll';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import { connectRealtime } from '../../resources/js/lib/realtime';
import type { Poll } from '../../resources/js/types';
import { fakeConnection } from './FakeRealtime';
import { find } from './HookHarness';

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual<typeof Inertia>('@inertiajs/react');
    return {
        ...actual,
        useHttp: vi.fn(actual.useHttp),
        usePage: vi.fn(actual.usePage),
    };
});

const mentionedId = '019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b';
const commit = {
    id: '019a2b3c-0000-7000-8000-000000000001',
    author: { id: '019a2b3c-0000-7000-8000-000000000002' },
    status: 'approved' as const,
    mentions: { alice: mentionedId },
};
const labels = [
    'Bun :rocket: https://bun.sh',
    'https://github.com/laravel/framework',
    'pipe to /dev/null with `rm -rf`',
    '@alice or @bob',
];

function pollWith(results: boolean): Poll {
    return {
        id: '019a2b3c-0000-7000-8000-000000000003',
        closes_at: '2999-01-01T00:00:00Z',
        is_closed: false,
        has_voted: results,
        total_votes: results ? 4 : null,
        options: labels.map((label, index) => ({
            id: String(index),
            label,
            votes_count: results ? 1 : null,
            voted: false,
        })),
    };
}

function renderPage(
    content: ReactElement,
    props: Record<string, unknown> = {},
): string {
    return renderToStaticMarkup(
        <App
            initialComponent={() => (
                <TooltipProvider>{content}</TooltipProvider>
            )}
            initialPage={
                {
                    component: 'Commit',
                    url: '/',
                    version: null,
                    props: {
                        errors: {},
                        auth: { user: null, pasteis: null },
                        ...props,
                    },
                    clearHistory: false,
                    encryptHistory: false,
                } as unknown as ComponentProps<typeof App>['initialPage']
            }
        />,
    );
}

for (const results of [false, true]) {
    for (const isStatic of [false, true]) {
        test(`uses each option's loaded guild badges in the ${isStatic ? 'static ' : ''}${results ? 'results' : 'vote'} view`, () => {
            const poll = pollWith(results);
            poll.options = [
                {
                    ...poll.options[0],
                    label: '#RuSt #unknown `#rust`',
                    guild_mentions: {
                        rust: {
                            handle: 'rust',
                            name: 'Rustaceans',
                            emoji: '🦀',
                            icon_url: null,
                        },
                    },
                },
                { ...poll.options[1], label: '#rust' },
            ];

            const markup = renderPage(
                <CommitPoll commit={commit} poll={poll} isStatic={isStatic} />,
                {
                    guildsEnabled: true,
                    auth: {
                        user: { id: 'viewer' },
                        organizations: [],
                        pasteis: null,
                    },
                },
            );

            assert.match(markup, /<code[^>]*>#rust<\/code>/);
            if (isStatic) {
                assert.doesNotMatch(markup, /data-test="guild-mention"/);
                assert.doesNotMatch(markup, /<a/);
                assert.match(markup, /#RuSt #unknown/);
            } else {
                assert.equal(
                    (markup.match(/data-test="guild-mention"/g) ?? []).length,
                    1,
                );
                assert.equal(
                    (markup.match(/href="\/guilds\/rust"/g) ?? []).length,
                    1,
                );
                assert.equal(
                    (markup.match(/href="\/search\?q=%23rust"/g) ?? []).length,
                    1,
                );
                assert.match(markup, /aria-label="Rustaceans, #RuSt guild"/);
                assert.match(markup, /href="\/guilds\/rust"/);
                assert.match(markup, /href="\/search\?q=%23unknown"/);
                assert.match(markup, /href="\/search\?q=%23rust"/);
            }
        });
    }

    test(`renders poll options like a commit body in the ${results ? 'results' : 'vote'} view`, () => {
        const markup = renderPage(
            <CommitPoll commit={commit} poll={pollWith(results)} />,
        );

        assert.ok(markup.includes('Bun 🚀'));
        assert.ok(markup.includes('href="https://bun.sh"'));
        assert.ok(markup.includes('aria-label="laravel/framework on GitHub"'));
        assert.ok(markup.includes('title="/dev/null"'));
        assert.match(markup, /<code[^>]*>rm -rf<\/code>/);
        assert.ok(markup.includes(`href="/mentions/${mentionedId}"`));
        assert.equal(markup.match(/data-test="mention"/g)?.length, 1);
        assert.match(markup, /or <a[^>]*href="\/@bob"[^>]*>@bob<\/a>/);
    });
}

for (const results of [false, true]) {
    test(`renders poll options without links in a static ${results ? 'results' : 'vote'} view`, () => {
        const markup = renderPage(
            <CommitPoll commit={commit} poll={pollWith(results)} isStatic />,
        );

        assert.ok(!markup.includes('<a'));
        assert.ok(markup.includes('Bun 🚀'));
        assert.ok(
            markup.includes('title="https://github.com/laravel/framework"'),
        );
        assert.ok(markup.includes('title="/dev/null"'));
        assert.match(markup, /<code[^>]*>rm -rf<\/code>/);
    });
}

for (const update of ['selection', 'read', 'vote'] as const) {
    test(`parent option metadata refresh preserves ${update === 'selection' ? 'the selected radio' : `newer ${update} totals`} and unchanged props preserve fresh local maps`, async () => {
        vi.useFakeTimers();
        vi.stubGlobal('window', globalThis);
        vi.stubGlobal('location', new URL('http://localhost/'));
        vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
        const fake = fakeConnection();
        connectRealtime(fake.connection);
        const page = vi.spyOn(Inertia, 'usePage').mockReturnValue({
            props: {
                now: Date.now(),
                guildsEnabled: true,
                auth: {
                    user: { id: 'viewer' },
                    organizations: [],
                    pasteis: null,
                },
            },
        } as unknown as ReturnType<typeof Inertia.usePage>);
        const original = pollWith(false);
        original.closes_at = new Date(Date.now() + 60_000).toISOString();
        original.options[0] = {
            ...original.options[0],
            label: '#rust',
            guild_mentions: {
                rust: {
                    handle: 'rust',
                    name: 'Original guild',
                    emoji: '🦀',
                    icon_url: null,
                },
            },
        };
        const local: Poll = {
            ...original,
            has_voted: true,
            total_votes: 17,
            options: original.options.map((option) => ({
                ...option,
                votes_count: option.id === '0' ? 17 : 0,
                voted: option.id === '0',
                guild_mentions:
                    option.id === '0'
                        ? {
                              rust: {
                                  handle: 'rust',
                                  name: 'Read guild',
                                  emoji: '🦀',
                                  icon_url: null,
                              },
                          }
                        : undefined,
            })),
        };
        const data = { option: '' };
        const http = vi.spyOn(Inertia, 'useHttp').mockReturnValue({
            data,
            processing: false,
            setData: (_key: string, value: string) => {
                data.option = value;
            },
            submit: async () => ({ poll: local }),
        } as unknown as ReturnType<typeof Inertia.useHttp>);
        vi.stubGlobal('fetch', async () => Response.json({ poll: local }));
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
        let tree!: ReactElement;
        function Probe({ poll }: { poll: Poll }) {
            tree = CommitPoll({ commit, poll });
            return null;
        }

        try {
            act(() => root.render(<Probe poll={original} />));
            const radio = find(
                tree,
                (props) => props.type === 'radio' && props.value === '0',
            );
            assert.ok(radio);
            (radio.props.onChange as () => void)();
            act(() => root.render(<Probe poll={original} />));
            if (update === 'read') {
                fake.joined.get(`commits.${commit.id}`)?.emit('poll');
                await act(async () => {
                    await vi.advanceTimersByTimeAsync(600);
                });
            } else if (update === 'vote') {
                const button = find(
                    tree,
                    (props) => props['data-test'] === 'poll-vote-button',
                );
                assert.ok(button);
                await act(async () => {
                    await (button.props.onClick as () => Promise<void>)();
                });
            }
            act(() => root.render(<Probe poll={original} />));
            let markup = renderToStaticMarkup(
                <TooltipProvider>{tree}</TooltipProvider>,
            );
            assert.match(
                markup,
                update === 'selection' ? /Original guild/ : /Read guild/,
            );

            const refreshed = {
                ...original,
                options: [...original.options].reverse().map((option) => ({
                    ...option,
                    guild_mentions:
                        option.id === '0'
                            ? {
                                  rust: {
                                      handle: 'rust',
                                      name: 'Renamed guild',
                                      emoji: '🦀',
                                      icon_url: null,
                                  },
                              }
                            : undefined,
                })),
            };
            act(() => root.render(<Probe poll={refreshed} />));
            markup = renderToStaticMarkup(
                <TooltipProvider>{tree}</TooltipProvider>,
            );
            assert.match(markup, /Renamed guild/);
            assert.doesNotMatch(markup, /Original guild|Read guild/);
            if (update === 'selection') {
                assert.equal(
                    find(
                        tree,
                        (props) =>
                            props.type === 'radio' && props.value === '0',
                    )?.props.checked,
                    true,
                );
            } else {
                assert.match(markup, /17 votes/);
                assert.match(markup, /your vote/);
            }
        } finally {
            act(() => root.unmount());
            connectRealtime(null);
            http.mockRestore();
            page.mockRestore();
            vi.unstubAllGlobals();
            vi.useRealTimers();
        }
    });
}

test('shortens a big poll total but keeps the exact total on hover and for screen readers', () => {
    const poll = {
        ...pollWith(true),
        total_votes: 10_250,
        options: pollWith(true).options.map((option, index) => ({
            ...option,
            votes_count: index === 0 ? 10_247 : 1,
        })),
    };
    const markup = renderPage(<CommitPoll commit={commit} poll={poll} />);
    const summary = markup.match(
        /<p[^>]*data-test="poll-summary"[^>]*>(.*?)<\/p>/,
    )?.[1];

    assert.ok(summary);
    assert.match(
        summary,
        /<span aria-hidden="true" title="10,250 votes">10\.2k votes<\/span>/,
    );
    assert.match(summary, /<span class="sr-only">10,250 votes<\/span>/);
});

test('shows a small poll total in full without a hover', () => {
    const markup = renderPage(
        <CommitPoll commit={commit} poll={pollWith(true)} />,
    );
    const summary = markup.match(
        /<p[^>]*data-test="poll-summary"[^>]*>(.*?)<\/p>/,
    )?.[1];

    assert.ok(summary);
    assert.match(summary, /<span aria-hidden="true">4 votes<\/span>/);
    assert.match(summary, /<span class="sr-only">4 votes<\/span>/);
});
