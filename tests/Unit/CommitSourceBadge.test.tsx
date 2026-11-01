/// <reference types="node" />

import assert from 'node:assert/strict';
import { Children, isValidElement, type ReactNode } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { CommitSourceBadge } from '../../resources/js/components/commit-source-badge';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';

const labels = {
    web: 'web',
    cli: 'cli',
    tui: 'tui',
    ios: 'ios',
    android: 'android',
    macos: 'macos',
    windows: 'windows',
    linux: 'linux',
};

for (const [source, label] of Object.entries(labels)) {
    test(`labels ${source} commits as ${label}`, () => {
        const badge = renderToStaticMarkup(
            <TooltipProvider>
                <CommitSourceBadge source={source} />
            </TooltipProvider>,
        );

        assert.match(
            badge,
            new RegExp(
                `<span class="sr-only">Committed via </span>${label}</span>$`,
            ),
        );
        assert.doesNotMatch(badge, /aria-label=/);
    });
}

test('shows the source in its lazy hover tooltip', () => {
    const badge = CommitSourceBadge({ source: 'web' });
    const tooltip = Children.toArray(badge?.props.children).at(1);

    assert.ok(isValidElement<{ children: ReactNode }>(tooltip));
    assert.equal(
        renderToStaticMarkup(<>{tooltip.props.children}</>),
        'Committed via web',
    );
});

test('renders a newly introduced backend source without a frontend change', () => {
    const badge = renderToStaticMarkup(
        <TooltipProvider>
            <CommitSourceBadge source="headless" />
        </TooltipProvider>,
    );

    assert.match(
        badge,
        /<span class="sr-only">Committed via <\/span>headless<\/span>$/,
    );
    assert.doesNotMatch(badge, /aria-label=/);
});

for (const source of [null, undefined]) {
    test(`omits a ${String(source)} source`, () => {
        assert.equal(
            renderToStaticMarkup(
                <TooltipProvider>
                    <CommitSourceBadge source={source} />
                </TooltipProvider>,
            ),
            '',
        );
    });
}
