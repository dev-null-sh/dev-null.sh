import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import {
    GraphCanvas,
    WithoutGraph,
} from '../../resources/js/components/graph-canvas';

test('renders the rail pieces in a canvas too, marked for the canvas to hide once it draws', () => {
    const markup = renderToStaticMarkup(
        <GraphCanvas>
            <WithoutGraph>
                <span data-test="rail-piece" />
            </WithoutGraph>
        </GraphCanvas>,
    );

    assert.match(
        markup,
        /<span data-graph-rail="true"><span data-test="rail-piece"><\/span><\/span>/,
    );
    // Not drawn yet on the server: the rail shows.
    assert.doesNotMatch(markup, /data-graph-drawn/);
});

test('draws the rail pieces as they are outside any canvas', () => {
    const markup = renderToStaticMarkup(
        <WithoutGraph>
            <span data-test="rail-piece" />
        </WithoutGraph>,
    );

    assert.equal(markup, '<span data-test="rail-piece"></span>');
});
