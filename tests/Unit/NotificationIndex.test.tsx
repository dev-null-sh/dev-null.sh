/// <reference types="node" />

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import type { ReactElement } from 'react';
import { renderToString } from 'react-dom/server';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import Index, {
    NotificationContent,
} from '../../resources/js/pages/notification/index';
import type {
    Commit,
    Notification,
    NotificationGroup,
    User,
} from '../../resources/js/types';

const actor = {
    id: 'actor',
    name: 'Matthieu Napoli',
    username: 'mnapoli',
    lane: 2,
} as User;

const notification = {
    id: 'notification',
    group_id: 'notification',
    event_count: 1,
    type: 'replied',
    actor,
    actors: [actor],
    actors_count: 1,
    unread_count: 1,
    commit_id: 'reply',
    subject_id: 'parent',
    reason: null,
    created_at: '2026-10-16T11:51:00.000000Z',
    commit: {
        id: 'reply',
        body: 'Try this instead\n```php\nreturn round($score, 6);\n```',
        body_segments: [
            { type: 'text', text: 'Try this instead\n' },
            {
                type: 'block',
                language: 'php',
                html: '<span class="hl-keyword">return</span> round($score, 6);',
            },
        ],
    } as Omit<Commit, 'user'>,
} as NotificationGroup;

async function renderPage(page: () => ReactElement): Promise<string> {
    const result = await createInertiaApp({
        page: {
            component: 'notification/index',
            props: {
                auth: {
                    user: { id: 'recipient', username: 'nunomaduro' },
                    log: {},
                },
                errors: {},
            },
            url: '/notifications',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => page,
        render: renderToString,
        setup: ({ App, props }) => (
            <TooltipProvider>
                <App {...props} />
            </TooltipProvider>
        ),
    });

    assert.ok(result);

    return result.body;
}

async function render(notifications: NotificationGroup[]): Promise<string> {
    const body = await renderPage(() => (
        <Index
            notifications={{
                data: notifications,
                next_cursor: null,
                prev_cursor: null,
            }}
            filters={{ type: 'all' }}
            readAt="2026-10-16T12:00:00.000000Z"
        />
    ));

    return body.slice(body.indexOf('<article'));
}

const renderItem = async (item: Notification): Promise<string> =>
    (
        await renderPage(() => <NotificationContent notification={item} />)
    ).replaceAll('<!-- -->', '');

const previewedAvatars = (body: string): number =>
    body.split('<span class="flex shrink-0 self-start"><span class="flex">')
        .length - 1;

const previewedNames = (body: string): number =>
    body.match(
        /<span class="relative z-10[^"]*">(?:<a[^>]*>)?(?:<a|<span)[^>]*title="Matthieu Napoli"/g,
    )?.length ?? 0;

const other = { ...actor, id: 'other', name: 'Other', username: 'other' };

void test('renders a code block highlighted in a notification', async () => {
    const body = await render([notification]);

    assert.match(body, /<pre[^>]*><span class="hl-keyword">return<\/span>/);
    assert.doesNotMatch(body, /```/);
    assert.equal(body.match(/<a\b/g)?.length, 4);
    assert.doesNotMatch(body, /<button\b/);
    assert.match(body, /<pre[^>]*tabindex="-1"/i);
});

for (const isFollowing of [false, true]) {
    void test(`keeps ${isFollowing ? 'unfollow' : 'follow-back'} separate from highlighted notification links`, async () => {
        const followed = {
            ...notification,
            id: 'follow-notification',
            group_id: 'follow-notification',
            type: 'followed',
            actor: { ...actor, is_followed: isFollowing },
            commit: null,
            commit_id: null,
            subject_id: null,
        } as NotificationGroup;
        const body = await render([notification, followed]);

        assert.match(body, /<pre[^>]*><span class="hl-keyword">return<\/span>/);
        assert.equal(body.match(/<a\b/g)?.length, 7);
        assert.equal(body.match(/<button\b/g)?.length, 1);
        assert.doesNotMatch(body, /<a\b[^>]*>(?:(?!<\/a>).)*<button\b/s);
        assert.match(
            body,
            new RegExp(
                `aria-label="${isFollowing ? 'Unfollow' : 'Follow'} @mnapoli"`,
            ),
        );
        assert.match(body, /followed you/);
        assert.doesNotMatch(body, /```/);
    });
}

void test('shows the image of a starred image-only commit beside who starred it', async () => {
    const starred = {
        ...notification,
        type: 'starred',
        actors_count: 3,
        commit: {
            id: 'image-only',
            body: '',
            body_segments: [],
            images: [
                {
                    id: 'image',
                    url: '/commit-images/image',
                    width: 1200,
                    height: 800,
                    description: null,
                },
            ],
        } as unknown as Omit<Commit, 'user'>,
    } as NotificationGroup;
    const body = (await render([starred])).replaceAll('<!-- -->', '');

    assert.match(
        body,
        /and 2 others <a\b[^>]*data-notification-link[^>]*>starred your image<\/a>/,
    );
    assert.match(body, /<img[^>]*src="\/commit-images\/image"/);
    assert.doesNotMatch(body, /<div[^>]*>(?:<div[^>]*>)*(?:<\/div>)+<\/a>/);
});

void test('links the actor to their profile apart from the commit link', async () => {
    const starred = { ...notification, type: 'starred' } as NotificationGroup;
    const body = await render([starred]);
    const links = [...body.matchAll(/<a\b[^>]*>/g)].map((match) => match[0]);

    assert.deepEqual(
        links.map((link) => link.match(/href="([^"]*)"/)?.[1]),
        ['/@mnapoli', '/@mnapoli', '/@mnapoli', '/@nunomaduro/commits/reply'],
    );
    assert.match(links[0], /tabindex="-1"/);
    assert.match(links[1], /hover:underline/);
    assert.doesNotMatch(links[2], /hover:underline/);
    assert.doesNotMatch(body, /<a\b(?:(?!<\/a>).)*<a\b/s);
    assert.match(body, /starred your commit<\/a>/);
});

void test('previews the person of a single reply from the avatar and the name', async () => {
    const body = await render([notification]);

    assert.equal(previewedAvatars(body), 1);
    assert.equal(previewedNames(body), 1);
});

void test('previews the person of a single star from the avatar and the name', async () => {
    const body = await render([
        { ...notification, type: 'starred' } as NotificationGroup,
    ]);

    assert.equal(previewedAvatars(body), 1);
    assert.equal(previewedNames(body), 1);
});

for (const type of ['replied', 'starred']) {
    void test(`leaves the header of a group of ${type} notifications without previews`, async () => {
        const body = await render([
            {
                ...notification,
                type,
                event_count: 2,
                actors: [actor, other],
                actors_count: 2,
            } as NotificationGroup,
        ]);

        assert.equal(previewedAvatars(body), 0);
        assert.equal(previewedNames(body), 0);
    });
}

for (const type of ['replied', 'starred']) {
    void test(`previews the person of an expanded ${type} notification`, async () => {
        const body = await renderItem({
            ...notification,
            type,
        } as Notification);

        assert.equal(previewedAvatars(body), 1);
        assert.equal(previewedNames(body), 1);
    });
}

const avatarLink =
    /<span class="flex shrink-0 self-start"><span class="flex"><a tabindex="-1"[^>]*href="\/@mnapoli"/;

const nestedLink = /<a\b(?:(?!<\/a>).)*<a\b/s;

for (const type of ['replied', 'starred']) {
    void test(`links the avatar of a single ${type} notification to the profile`, async () => {
        const body = await render([
            { ...notification, type } as NotificationGroup,
        ]);

        assert.match(body, avatarLink);
        assert.doesNotMatch(body, nestedLink);
    });
}

void test('leaves the avatar of a single follow unlinked, as the row already opens the profile', async () => {
    const body = await render([
        {
            ...notification,
            type: 'followed',
            actor: { ...actor, is_followed: false },
            commit: null,
            commit_id: null,
            subject_id: null,
        } as NotificationGroup,
    ]);

    assert.doesNotMatch(body, avatarLink);
});

void test('links an expanded reply to its author and to the reply', async () => {
    const body = await renderItem(notification);

    assert.match(body, avatarLink);
    assert.match(
        body,
        /<a [^>]*href="\/@mnapoli"[^>]*><span class="[^"]*font-semibold[^"]*" title="Matthieu Napoli">/,
    );
    assert.match(
        body,
        /<a [^>]*href="\/@mnapoli\/commits\/reply"[^>]*>(?:(?!<\/a>).)*<time\b/s,
    );
    assert.match(
        body,
        /<a tabindex="-1"[^>]*href="\/@mnapoli\/commits\/reply"[^>]*><div class="[^"]*max-h-40/,
    );
    assert.doesNotMatch(body, nestedLink);
});

void test('keeps an expanded star as one link to the profile', async () => {
    const body = await renderItem({
        ...notification,
        type: 'starred',
    } as Notification);

    assert.equal(body.match(/<a\b/g)?.length, 1);
    assert.match(body, /<a [^>]*href="\/@mnapoli"/);
});
