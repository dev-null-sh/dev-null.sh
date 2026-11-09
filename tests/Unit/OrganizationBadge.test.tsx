/// <reference types="node" />
import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { OrganizationBadge } from '../../resources/js/components/organization-badge';

test('labels the badge with the organization that posted the commit', () => {
    const html = renderToStaticMarkup(<OrganizationBadge handle="laravel" />);

    assert.match(html, /Organization/);
    assert.match(html, /title="Posted by the @laravel organization"/);
});
