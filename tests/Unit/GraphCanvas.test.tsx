import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import {
    GraphCanvas,
    WithoutGraph,
} from '../../resources/js/components/graph-canvas';

test('keeps the rail pieces until the canvas has measured its nodes, so the server renders the same rail', () => {
    const markup = renderToStaticMarkup(
        <GraphCanvas>
            <WithoutGraph>
                <span data-test="rail-piece" />
            </WithoutGraph>
        </GraphCanvas>,
    );

    assert.match(markup, /data-test="rail-piece"/);
});

test('draws the rail pieces outside any canvas', () => {
    const markup = renderToStaticMarkup(
        <WithoutGraph>
            <span data-test="rail-piece" />
        </WithoutGraph>,
    );

    assert.match(markup, /data-test="rail-piece"/);
});
