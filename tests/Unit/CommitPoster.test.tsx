/// <reference types="node" />
import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { CommitPoster } from '../../resources/js/components/commit-poster';
import type { User } from '../../resources/js/types';

test("links moderators to whoever posted an organization's commit", () => {
    const html = renderToStaticMarkup(
        <CommitPoster commit={{ poster: { handle: 'taylor' } as User }} />,
    );

    assert.match(html, /Posted by/);
    assert.match(html, /<a[^>]*href="\/@taylor"[^>]*>@taylor<\/a>/);
});

test('says when that account was deleted, and shows nothing for a commit with no poster apart from its author', () => {
    assert.match(
        renderToStaticMarkup(<CommitPoster commit={{ poster: null }} />),
        /Posted by an account since deleted/,
    );
    assert.equal(renderToStaticMarkup(<CommitPoster commit={{}} />), '');
});
