/// <reference types="node" />

import assert from 'node:assert/strict';
import { createInertiaApp } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { BookOpen, ShieldCheck, Users } from 'lucide-react';
import { createElement } from 'react';
import type { ComponentProps } from 'react';
import { renderToString } from 'react-dom/server';
import { beforeEach, test, vi } from 'vite-plus/test';
import ConsoleLayout from '../../resources/js/layouts/console/layout';
import type { User } from '../../resources/js/types';

const links = vi.hoisted(() => [] as InertiaLinkProps[]);

// Inertia renders nothing of `prefetch` on the server, so the links' props are read as they are given.
vi.mock('@inertiajs/react', async (importOriginal) => {
    const inertia = await importOriginal<typeof import('@inertiajs/react')>();

    return {
        ...inertia,
        Link: (props: InertiaLinkProps) => {
            links.push(props);

            return createElement(inertia.Link, props);
        },
    };
});

beforeEach(() => {
    links.length = 0;
});

function prefetchOf(href: string): InertiaLinkProps['prefetch'][] {
    return links
        .filter((link) => link.href === href)
        .map((link) => link.prefetch);
}

const groups: ComponentProps<typeof ConsoleLayout>['groups'] = [
    {
        label: 'Guide',
        items: [
            {
                key: 'introduction',
                title: 'Introduction',
                description: 'What the API is for',
                href: '/docs/api/introduction',
                icon: BookOpen,
            },
        ],
    },
    {
        label: 'Reference',
        items: [
            {
                key: 'users',
                title: 'Users',
                description: 'Profiles and who follows whom',
                href: '/docs/api/users',
                icon: Users,
            },
        ],
    },
    {
        label: 'Elsewhere',
        items: [
            {
                key: 'moderation',
                title: 'Moderation',
                description: 'The review queue',
                href: '/moderation',
                icon: ShieldCheck,
                external: true,
            },
        ],
    },
];

async function render(
    props: Partial<ComponentProps<typeof ConsoleLayout>> = {},
): Promise<string> {
    const result = await createInertiaApp({
        page: {
            component: 'console',
            props: { errors: {} },
            url: '/docs/api/users',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => () => (
            <ConsoleLayout
                chip="api"
                label="/dev/null API"
                navLabel="API docs"
                brandHref="/docs/api"
                groups={groups}
                current="users"
                backHref="/"
                user={null}
                {...props}
            >
                <p>content</p>
            </ConsoleLayout>
        ),
        render: renderToString,
        setup: ({ App, props: appProps }) => <App {...appProps} />,
    });

    return result.body;
}

test('brands the console with its chip', async () => {
    const html = await render();

    assert.match(html, />api<\/span>/);
    assert.match(html, /aria-label="\/dev\/null API"/);
});

test('names the navigation after the console, apart from the brand link', async () => {
    const html = await render();

    assert.equal(html.match(/<nav aria-label="API docs"/g)?.length, 2);
    assert.doesNotMatch(html, /<nav aria-label="\/dev\/null API"/);
});

test('names the console in the header when no page of it is current', async () => {
    const html = await render({ current: 'moderation' });

    assert.equal(html.match(/<h1[^>]*>API docs<\/h1>/g)?.length, 2);
});

test('prefetches its pages on hover when the console asks for it', async () => {
    await render({ prefetch: true });

    assert.deepEqual(prefetchOf('/docs/api/users'), [true, true]);
    assert.deepEqual(prefetchOf('/docs/api'), [true, true]);
});

test('leaves its pages unprefetched by default', async () => {
    await render();

    assert.deepEqual(prefetchOf('/docs/api/users'), [false, false]);
    assert.deepEqual(prefetchOf('/docs/api'), [false, false]);
});

test('labels each group in lowercase', async () => {
    const html = await render();

    assert.match(html, />guide<\/p>/);
    assert.match(html, />reference<\/p>/);
    assert.match(html, />elsewhere<\/p>/);
});

test('marks the current item in both the side and the mobile nav', async () => {
    const html = await render();

    const current = html.match(/<a[^>]*aria-current="page"[^>]*>/g) ?? [];

    assert.equal(current.length, 2);
    assert.ok(current.every((link) => link.includes('href="/docs/api/users"')));
});

test('names the current page in the header', async () => {
    const html = await render();

    assert.match(html, /<h1[^>]*>Users<\/h1>/);
    assert.match(html, /Profiles and who follows whom/);
});

test('opens items elsewhere as plain links, never as the current page', async () => {
    const html = await render({ current: 'moderation' });

    assert.doesNotMatch(html, /aria-current="page"/);
    assert.match(html, /href="\/moderation"/);
});

test('shows the header actions and the content', async () => {
    const html = await render({ headerActions: <button>refresh</button> });

    assert.match(html, /<button>refresh<\/button>/);
    assert.match(html, /<p>content<\/p>/);
});

test('leads a guest back without an account, on a phone too', async () => {
    const html = await render();
    const header = html.split('<header')[1] ?? '';

    assert.match(html, /Back to \/dev\/null/);
    assert.doesNotMatch(html, /@jubianchi/);
    assert.match(
        header,
        /<a href="\/" class="[^"]*lg:hidden[^"]*" aria-label="Back to \/dev\/null"/,
    );
});

test('leads a member back with their handle', async () => {
    const html = await render({
        user: { id: 'u', handle: 'jubianchi', name: 'Julien' } as User,
    });

    assert.match(html, /@jubianchi/);
});
