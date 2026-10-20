/// <reference types="node" />

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { ContributorBadge } from '../../resources/js/components/contributor-badge';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';

void test('renders the repository mark with a contributor label', () => {
    const html = renderToStaticMarkup(
        <TooltipProvider>
            <ContributorBadge />
        </TooltipProvider>,
    );

    assert.match(html, /data-test="contributor-badge"/);
    assert.match(html, /Contributor/);
    assert.match(html, /<svg/);
});
