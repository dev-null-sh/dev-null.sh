import assert from 'node:assert/strict';
import { App } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { CommitCard } from '../../resources/js/components/commit-card';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import type { Commit } from '../../resources/js/types';

for (const posted of [false, true]) {
    test(`links the joined-guild explanation to the ${posted ? 'posted' : 'mentioned'} guild`, () => {
        const guild = {
            handle: 'rust',
            name: 'Rustaceans',
            emoji: '🦀',
            icon_url: null,
        };
        const commit = {
            id: '019a2b3c-0000-7000-8000-000000000001',
            parent_id: null,
            quoted_commit_id: null,
            body: '#rust',
            body_segments: [{ type: 'text', text: '#rust' }],
            preview_segments: null,
            status: 'approved',
            created_at: '2026-01-01T00:00:00Z',
            updated_at: '2026-01-01T00:00:00Z',
            author: {
                id: 'alice',
                type: 'user',
                handle: 'alice',
                name: 'Alice',
                avatar_url: null,
            },
            joined_guild: 'rust',
            guild: posted
                ? guild
                : { handle: 'php', name: 'PHP', emoji: '🐘', icon_url: null },
            guild_handle: posted ? 'rust' : 'php',
            guild_mentions: posted ? {} : { rust: guild },
            mentions: null,
            images: [],
            stars_count: 0,
            pasteis_count: 0,
            starred: false,
        } as unknown as Commit;
        const markup = renderToStaticMarkup(
            <App
                initialComponent={() => (
                    <TooltipProvider>
                        <CommitCard commit={commit} />
                    </TooltipProvider>
                )}
                initialPage={
                    {
                        component: 'log/show',
                        url: '/log/following',
                        version: null,
                        props: {
                            errors: {},
                            now: Date.now(),
                            guildsEnabled: true,
                            auth: {
                                user: { id: 'viewer' },
                                organizations: [],
                                isAdmin: false,
                                pasteis: null,
                            },
                        },
                        clearHistory: false,
                        encryptHistory: false,
                    } as unknown as ComponentProps<typeof App>['initialPage']
                }
            />,
        );

        const explanation = markup.match(/because you joined (.*?)<\/p>/)?.[1];
        assert.ok(explanation);
        assert.match(explanation, /href="\/guilds\/rust"/);
        assert.match(explanation, /Rustaceans/);
        assert.doesNotMatch(explanation, /href="\/search/);
    });
}
