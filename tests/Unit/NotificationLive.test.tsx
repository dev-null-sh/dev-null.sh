/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { find, hookHarness } from './HookHarness';
import { fakeConnection, joinAll } from './FakeRealtime';

const harness = hookHarness();
type Visit = { url: unknown; options: { onFinish?: () => void } };
type Reload = { only?: string[]; onFinish?: () => void };
const visits: Visit[] = [];
const reloads: Reload[] = [];

vi.doMock('react', () => harness.react);
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    router: {
        visit: (url: unknown, options: Visit['options']) => visits.push({ url, options }),
        reload: (options: Reload) => reloads.push(options),
        flushAll: () => {},
        on: () => () => {},
    },
    useHttp: () => ({ transform: () => {}, put: async () => {} }),
    usePage: () => ({ props: { auth: { user: { id: 'me' } } } }),
    usePoll: () => ({ start: () => {}, stop: () => {} }),
}));

const { connectRealtime } = await import('../../resources/js/lib/realtime');
const { default: Index } = await import('../../resources/js/pages/notification/index');

type Props = Parameters<typeof Index>[0];
type Group = Props['notifications']['data'][number];

const group = (event_count: number) =>
    ({
        id: 'group',
        group_id: 'group',
        event_count,
        type: 'starred',
        actor: { type: 'user', id: 'a', name: 'A', handle: 'a', lane: 1, is_followed: false },
        actors: [],
        actors_count: event_count,
        unread_count: 0,
        commit_id: 'c',
        subject_id: 'c',
        reason: null,
        created_at: '2026-11-03T10:00:00.000000Z',
        commit: { id: 'c', body: 'Body', body_segments: [] },
    }) as unknown as Group;

const page = (data: Group[] = []): Props => ({
    notifications: { data, next_cursor: null, prev_cursor: null },
    filters: { type: 'all' },
    readAt: '2026-11-03T09:00:00.000000Z',
});

let fake: ReturnType<typeof fakeConnection>;

beforeEach(() => {
    vi.useFakeTimers();
    visits.length = 0;
    reloads.length = 0;
    const events = new EventTarget();
    vi.stubGlobal('document', {
        visibilityState: 'visible',
        hidden: false,
        hasFocus: () => true,
        addEventListener: events.addEventListener.bind(events),
        removeEventListener: events.removeEventListener.bind(events),
    });
    fake = fakeConnection();
    connectRealtime(fake.connection);
});

afterEach(() => {
    harness.unmount();
    connectRealtime(null);
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

const notificationReloads = () =>
    reloads.filter((reload) => reload.only?.includes('notifications'));

test('a notification heard while a filter loads is read once the filter has, without another update', async () => {
    const props = page();
    const tree = harness.render(Index, props);
    joinAll(fake);
    const filters = find(tree, (props) => typeof props.onChange === 'function' && 'enabled' in props);
    assert.ok(filters);

    (filters.props.onChange as (types: string[]) => void)(['replied']);
    void harness.render(Index, props);
    fake.joined.get('private-users.me')?.emit('notifications');
    await vi.advanceTimersByTimeAsync(600);
    assert.equal(notificationReloads().length, 0);

    visits.at(-1)?.options.onFinish?.();
    void harness.render(Index, props);
    await vi.advanceTimersByTimeAsync(600);

    assert.equal(notificationReloads().length, 1);
});

test('a group changed while its first members load reads them again once they have, even at the same size', async () => {
    const members = (names: string[]) =>
        Response.json({
            data: names.map((id) => ({ ...group(1), id, group_id: id })),
            next_cursor: null,
            prev_cursor: null,
        });
    const responses: ((response: Response) => void)[] = [];
    vi.stubGlobal('fetch', () => new Promise<Response>((resolve) => responses.push(resolve)));
    vi.stubGlobal('window', { ...globalThis, setTimeout, clearTimeout });
    const item = find(harness.render(Index, page([group(2)])), (props) => 'withoutBottomBorder' in props);
    assert.ok(item);
    const Item = item.type as (props: Record<string, unknown>) => ReturnType<typeof Index>;
    harness.unmount();
    const items = harness;
    const props = { ...item.props, onOpenChange: undefined };
    const expand = find(items.render(Item, props), (props) => 'aria-expanded' in props);
    assert.ok(expand);

    (expand.props.onClick as () => void)();
    void items.render(Item, props);
    const changed = { ...props, notification: { ...group(2), actors_count: 2 } };
    void items.render(Item, changed);
    responses.shift()?.(members(['old-1', 'old-2']));
    await vi.advanceTimersByTimeAsync(0);
    void items.render(Item, changed);
    await vi.advanceTimersByTimeAsync(0);

    assert.equal(responses.length, 1);
    responses.shift()?.(members(['new-1', 'new-2']));
    await vi.advanceTimersByTimeAsync(0);
    const shown = items.render(Item, changed);

    assert.ok(find(shown, (props) => (props.notification as { id?: string } | undefined)?.id === 'new-1'));
});

test('a group gaining a member while open stays the same item, so it stays open where the reader is', () => {
    const item = (shown: Group) =>
        find(
            harness.render(Index, page([shown])),
            (props) => 'withoutBottomBorder' in props,
        );
    const before = item(group(2));
    const after = item({ ...group(3), id: 'newest' });

    assert.ok(before && after);
    assert.equal(after.key, before.key);
});

test('a closed group whose members changed reads them afresh when opened again', async () => {
    const members = (names: string[]) =>
        Response.json({
            data: names.map((id) => ({ ...group(1), id, group_id: id })),
            next_cursor: null,
            prev_cursor: null,
        });
    const responses: ((response: Response) => void)[] = [];
    vi.stubGlobal(
        'fetch',
        () => new Promise<Response>((resolve) => responses.push(resolve)),
    );
    vi.stubGlobal('window', { ...globalThis, setTimeout, clearTimeout });
    const item = find(
        harness.render(Index, page([group(2)])),
        (props) => 'withoutBottomBorder' in props,
    );
    assert.ok(item);
    const Item = item.type as (
        props: Record<string, unknown>,
    ) => ReturnType<typeof Index>;
    harness.unmount();
    const props = { ...item.props, onOpenChange: undefined };
    const toggle = (shown: Record<string, unknown>) => {
        const expand = find(
            harness.render(Item, shown),
            (props) => 'aria-expanded' in props,
        );
        (expand?.props.onClick as () => void)();
    };

    toggle(props);
    responses.shift()?.(members(['old-1', 'old-2']));
    await vi.advanceTimersByTimeAsync(0);
    toggle(props);
    const changed = { ...props, notification: { ...group(3), id: 'newest' } };
    void harness.render(Item, changed);
    await vi.advanceTimersByTimeAsync(0);
    toggle(changed);
    await vi.advanceTimersByTimeAsync(0);

    assert.equal(responses.length, 1, 'opening again reads the members');
    responses.shift()?.(members(['newest', 'old-1', 'old-2']));
    await vi.advanceTimersByTimeAsync(0);
    const shown = harness.render(Item, changed);
    assert.ok(
        find(
            shown,
            (props) =>
                (props.notification as { id?: string } | undefined)?.id ===
                'newest',
        ),
    );
});

const person = (handle: string) => ({ ...group(3).actor, id: handle, name: handle.toUpperCase(), handle });
const named = (id: string, minute: number, actor = 'a', earlier: string[] = []) =>
    ({
        ...group(3),
        id,
        group_id: id,
        actor: person(actor),
        actors: [actor, ...earlier].map(person),
        created_at: `2026-11-03T10:${String(minute).padStart(2, '0')}:00.000000Z`,
    }) as Group;

const shownOrder = (tree: ReturnType<typeof Index>) => {
    const order: string[] = [];
    find(tree, (props) => {
        if ('withoutBottomBorder' in props) {
            order.push((props.notification as Group).group_id);
        }

        return false;
    });

    return order;
};

test('an open group stays where the reader has it when someone joins it, and closed ones take their new place', () => {
    const before = page([named('mention', 30), named('open', 20), named('closed', 10)]);
    const tree = harness.render(Index, before);
    const open = find(tree, (props) => (props.notification as Group | undefined)?.group_id === 'open' && 'withoutBottomBorder' in props);
    (open?.props.onOpenChange as (isOpen: boolean) => void)(true);
    void harness.render(Index, before);

    const joined = page([named('open', 40, 'alice'), named('closed', 35, 'bob'), named('mention', 30)]);
    void harness.render(Index, joined);
    const shown = harness.render(Index, joined);

    assert.deepEqual(shownOrder(shown), ['closed', 'mention', 'open']);
    const kept = find(shown, (props) => (props.notification as Group | undefined)?.group_id === 'open' && 'withoutBottomBorder' in props);
    assert.equal((kept?.props.notification as Group).actor.id, 'alice', 'the group shown is the one read now');

    const closed = find(shown, (props) => (props.notification as Group | undefined)?.group_id === 'open' && 'withoutBottomBorder' in props);
    (closed?.props.onOpenChange as (isOpen: boolean) => void)(false);
    void harness.render(Index, joined);
    const again = page([named('open', 40, 'alice'), named('closed', 35, 'bob'), named('mention', 30)]);
    void harness.render(Index, again);

    assert.deepEqual(shownOrder(harness.render(Index, again)), ['open', 'closed', 'mention']);
});

test('an open group keeps naming who it named when opened, while its counts and members follow who joins', () => {
    vi.stubGlobal('fetch', () => new Promise<Response>(() => {}));
    vi.stubGlobal('window', { ...globalThis, setTimeout, clearTimeout });
    const item = find(harness.render(Index, page([named('open', 20)])), (props) => 'withoutBottomBorder' in props);
    assert.ok(item);
    const Item = item.type as (props: Record<string, unknown>) => ReturnType<typeof Index>;
    harness.unmount();
    const props = { ...item.props, onOpenChange: undefined };
    const expand = find(harness.render(Item, props), (props) => 'aria-expanded' in props);
    (expand?.props.onClick as () => void)();
    void harness.render(Item, props);

    const joined = { ...props, notification: { ...named('open', 40, 'alice', ['a']), actors_count: 4, event_count: 4 } };
    void harness.render(Item, joined);
    const summary = find(harness.render(Item, joined), (props) => props.summary === true);
    const shown = summary?.props.notification as Group;

    assert.equal(shown.actor.id, 'a');
    assert.equal(shown.actors_count, 4);

    const collapse = find(harness.render(Item, joined), (props) => 'aria-expanded' in props);
    (collapse?.props.onClick as () => void)();
    void harness.render(Item, joined);
    const closed = find(harness.render(Item, joined), (props) => props.summary === true);
    assert.equal((closed?.props.notification as Group).actor.id, 'alice');
});
