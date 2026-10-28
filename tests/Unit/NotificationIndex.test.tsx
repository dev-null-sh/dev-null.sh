/// <reference types="node" />

import assert from 'node:assert/strict';
import { createInertiaApp } from '@inertiajs/react';
import type { ReactElement } from 'react';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import { laneColor } from '../../resources/js/lib/utils';
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
    handle: 'mnapoli',
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
                    user: { id: 'recipient', handle: 'nunomaduro' },
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

const other = { ...actor, id: 'other', name: 'Other', handle: 'other' };

 test('separates last-visit activity without a border under the preceding notification', async () => {
    const body = await render([
        { ...notification, read_at: '2026-10-16T12:00:00.000000Z' },
        {
            ...notification,
            id: 'earlier',
            group_id: 'earlier',
            read_at: '2026-10-15T12:00:00.000000Z',
            created_at: '2026-10-15T11:51:00.000000Z',
        },
    ]);
    const [before, after] = body.split('role="separator"');

    assert.match(before, /class="min-w-0 flex-1 py-3 pr-4"/);
    assert.match(after, /class="my-3 flex bg-muted\/40"/);
    assert.match(after, /class="flex flex-1 items-center py-4 pr-4"/);
    assert.match(after, /class="min-w-0 flex-1 py-3 pr-4 border-b"/);
});

 test('colors each notification rail by actor and ends it in a dot', async () => {
    const nextActor = { ...other, lane: 5, is_followed: false };
    const body = await render([
        notification,
        {
            ...notification,
            id: 'next',
            group_id: 'next',
            actor: nextActor,
            created_at: '2026-10-16T11:50:00.000000Z',
        },
    ]);

    assert.ok(body.includes(`--lane:${laneColor(actor.lane)}`));
    assert.ok(body.includes(`--lane:${laneColor(nextActor.lane)}`));
    assert.equal(body.match(/group\/rail-node absolute bottom-5/g)?.length, 2);
    assert.doesNotMatch(body, /data-test="rail-previous"/);
    assert.match(body, /border-\(--lane\)/);
});

 test('renders a code block highlighted in a notification', async () => {
    const body = await render([notification]);

    assert.match(body, /<pre[^>]*><span class="hl-keyword">return<\/span>/);
    assert.doesNotMatch(body, /```/);
    assert.equal(body.match(/<a\b/g)?.length, 4);
    assert.doesNotMatch(body, /<button\b/);
    assert.match(body, /<pre[^>]*tabindex="-1"/i);
});

for (const isFollowing of [false, true]) {
     test(`keeps ${isFollowing ? 'unfollow' : 'follow-back'} separate from highlighted notification links`, async () => {
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
                `aria-label="${isFollowing ? 'Unfollow @mnapoli' : 'Follow @mnapoli back'}"`,
            ),
        );
        assert.match(body, /followed you/);
        assert.doesNotMatch(body, /```/);
    });
}

 test('shows the image of a starred image-only commit beside who starred it', async () => {
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
        /and 2 others <\/span><a\b[^>]*data-notification-link[^>]*>starred your image<\/a>/,
    );
    assert.match(body, /<img[^>]*src="\/commit-images\/image"/);
    assert.doesNotMatch(body, /<div[^>]*>(?:<div[^>]*>)*(?:<\/div>)+<\/a>/);
});

 test('links the actor to their profile apart from the commit link', async () => {
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

 test('previews the person of a single reply from the avatar and the name', async () => {
    const body = await render([notification]);

    assert.equal(previewedAvatars(body), 1);
    assert.equal(previewedNames(body), 1);
});

 test('previews the person of a single star from the avatar and the name', async () => {
    const body = await render([
        { ...notification, type: 'starred' } as NotificationGroup,
    ]);

    assert.equal(previewedAvatars(body), 1);
    assert.equal(previewedNames(body), 1);
});

for (const type of ['replied', 'starred']) {
     test(`leaves the header of a group of ${type} notifications without previews`, async () => {
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
     test(`previews the person of an expanded ${type} notification`, async () => {
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
     test(`links the avatar of a single ${type} notification to the profile`, async () => {
        const body = await render([
            { ...notification, type } as NotificationGroup,
        ]);

        assert.match(body, avatarLink);
        assert.doesNotMatch(body, nestedLink);
    });
}

 test('leaves the avatar of a single follow unlinked, as the row already opens the profile', async () => {
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

 test('links an expanded reply to its author and to the reply', async () => {
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

 test('keeps an expanded star as one link to the profile', async () => {
    const body = await renderItem({
        ...notification,
        type: 'starred',
    } as Notification);

    assert.equal(body.match(/<a\b/g)?.length, 1);
    assert.match(body, /<a [^>]*href="\/@mnapoli"/);
});

 test('says which level and rank a level-up reached, linking to the profile', async () => {
    const leveledUp = {
        ...notification,
        id: 'level',
        group_id: 'level',
        type: 'leveled_up',
        commit_id: null,
        subject_id: null,
        commit: null,
        level: 5,
        rank: 'wheel',
        rank_description: 'Member of the trusted wheel administrative group',
    } as NotificationGroup;
    const body = await render([leveledUp]);

    assert.match(
        body,
        /data-test="level-chip"[^>]*>Lv\. <!-- -->5<!-- --> · <!-- -->wheel</,
    );
    assert.match(body, /You reached level 5: wheel<\/a>\./);
    assert.match(body, /Member of the trusted wheel administrative group/);
    assert.match(body, /<a[^>]*href="\/@mnapoli"/);
});
