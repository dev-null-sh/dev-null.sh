/// <reference types="node" />
import assert from 'node:assert/strict';
import { App } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { MergeConflict } from '../../resources/js/components/merge-conflict';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import type {
    MergeConflict as MergeConflictData,
    User,
} from '../../resources/js/types';

const commit = { id: '019a2b3c-0000-7000-8000-000000000001' };

function author(handle: string): User {
    return {
        id: handle,
        handle,
        name: handle,
        avatar_url: null,
        lane: 0,
    } as User;
}

function conflict(
    overrides: Partial<MergeConflictData> = {},
): MergeConflictData {
    return {
        id: 'conflict',
        closes_at: '2999-01-01T00:00:00Z',
        is_closed: false,
        side: null,
        can_vote: true,
        ours: {
            id: 'ours',
            body: 'Tabs, always.',
            user: author('alice'),
            votes: null,
        },
        theirs: {
            id: 'theirs',
            body: 'Spaces. Tabs are wrong.',
            user: author('bob'),
            votes: null,
        },
        ...overrides,
    };
}

function render(mergeConflict: MergeConflictData, signedIn = true): string {
    return renderToStaticMarkup(
        <App
            initialComponent={() => (
                <TooltipProvider>
                    <MergeConflict
                        commit={commit}
                        mergeConflict={mergeConflict}
                    />
                </TooltipProvider>
            )}
            initialPage={
                {
                    component: 'Commit',
                    url: '/',
                    version: null,
                    props: {
                        errors: {},
                        auth: { user: signedIn ? { id: 'reader' } : null },
                    },
                    clearHistory: false,
                    encryptHistory: false,
                } as unknown as ComponentProps<typeof App>['initialPage']
            }
        />,
    ).replaceAll('<!-- -->', '');
}

test('shows both replies between conflict markers, with a button to accept each', () => {
    const html = render(conflict());

    assert.match(
        html,
        /&lt;&lt;&lt;&lt;&lt;&lt;&lt;.*@alice.*Tabs, always\..*=======.*&gt;&gt;&gt;&gt;&gt;&gt;&gt;.*@bob.*Spaces\. Tabs are wrong\./s,
    );
    assert.match(html, /data-test="merge-conflict-ours"[^>]*>.*Accept @alice/s);
    assert.match(html, /data-test="merge-conflict-theirs"[^>]*>.*Accept @bob/s);
    assert.doesNotMatch(html, /%/);
});

test('shows the reader their pick and the count once they picked a side', () => {
    const html = render(
        conflict({
            side: 'theirs',
            can_vote: false,
            ours: { ...conflict().ours, votes: 1 },
            theirs: { ...conflict().theirs, votes: 3 },
        }),
    );

    assert.match(html, /25% · 1/);
    assert.match(html, /@bob.*your pick.*75% · 3/s);
    assert.doesNotMatch(html, /Accept @/);
});

test('names the side that won once the conflict closed, or a tie', () => {
    const closed = {
        is_closed: true,
        can_vote: false,
        ours: { ...conflict().ours, votes: 5 },
        theirs: { ...conflict().theirs, votes: 2 },
    };

    assert.match(render(conflict(closed)), /Resolved: kept @alice/);
    assert.match(
        render(conflict({ ...closed, theirs: { ...closed.theirs, votes: 5 } })),
        /Resolved: a tie/,
    );
});

test('shows zero counts once the conflict closed without votes', () => {
    const html = render(
        conflict({
            is_closed: true,
            can_vote: false,
            ours: { ...conflict().ours, votes: 0 },
            theirs: { ...conflict().theirs, votes: 0 },
        }),
    );

    assert.match(html, /Closed/);
    assert.match(html, /@alice.*0% · 0.*@bob.*0% · 0/s);
});

test('offers guests no buttons', () => {
    assert.doesNotMatch(
        render(conflict({ can_vote: false }), false),
        /Accept @/,
    );
});
