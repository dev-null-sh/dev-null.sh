import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vitest';
import { ExternalLinkGuard } from '../../resources/js/components/external-link-guard';

test('renders nothing until an external link is clicked', () => {
    assert.equal(renderToStaticMarkup(<ExternalLinkGuard />), '');
});
