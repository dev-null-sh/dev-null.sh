import assert from 'node:assert/strict';
import { App } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { FoldedCommitBody } from '../../resources/js/components/commit-body';
import type { BodySegment } from '../../resources/js/types';

function renderBody(
    segments: BodySegment[],
    previewSegments: BodySegment[] | null,
) {
    return renderToStaticMarkup(
        <App
            initialComponent={() => (
                <FoldedCommitBody
                    segments={segments}
                    previewSegments={previewSegments}
                    mentions={null}
                />
            )}
            initialPage={
                {
                    component: 'Preview',
                    url: '/',
                    version: null,
                    props: {
                        errors: {},
                        auth: { user: null, pasteis: null, isAdmin: false },
                    },
                    clearHistory: false,
                    encryptHistory: false,
                } as unknown as ComponentProps<typeof App>['initialPage']
            }
        />,
    );
}

const block = (html: string): BodySegment => ({
    type: 'block',
    language: 'bash',
    filename: null,
    html,
});

test('a shortened code preview uses its continuation cue without a loose ellipsis', () => {
    const markup = renderBody(
        [block('echo before\necho after')],
        [block('echo before'), { type: 'text', text: '\n…' }],
    );
    assert.ok(markup.includes('mask-b-from-'));
    assert.ok(markup.includes('echo before'));
    assert.ok(!markup.includes('echo after'));
    assert.ok(!markup.includes('…'));
    assert.ok(markup.includes('Show more'));
    assert.ok(markup.includes('aria-label="Copy code"'));
});

test('a completed code block keeps the ellipsis when later prose is hidden', () => {
    const markup = renderBody(
        [block('echo ready'), { type: 'text', text: '\nMore explanation' }],
        [block('echo ready'), { type: 'text', text: '\n…' }],
    );
    assert.ok(!markup.includes('mask-b-from-'));
    assert.ok(markup.includes('echo ready'));
    assert.ok(markup.includes('…'));
    assert.ok(markup.includes('Show more'));
});

test('a complete body shows all its code without an expansion control', () => {
    const markup = renderBody([block('echo before\necho after')], null);
    assert.ok(!markup.includes('mask-b-from-'));
    assert.ok(markup.includes('echo after'));
    assert.ok(!markup.includes('Show more'));
});
