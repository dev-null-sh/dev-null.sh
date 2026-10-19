/// <reference types="node" />
import assert from 'node:assert/strict';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { CommitSignedBadge } from '../../resources/js/components/commit-signed-badge';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';

const render = (signedAt: string | null) =>
    renderToString(
        <TooltipProvider>
            <CommitSignedBadge
                commit={{ id: 'signed-commit', signed_at: signedAt }}
            />
        </TooltipProvider>,
    );

test('shows a shield for a signed commit', () => {
    const html = render('2026-10-19T14:51:57+00:00');

    assert.match(html, /data-test="commit-signed"/);
    assert.match(html, /aria-label="Signed with a passkey"/);
    assert.doesNotMatch(html, />signed</);
});

test('shows nothing for an unsigned commit', () => {
    assert.equal(render(null), '');
});
