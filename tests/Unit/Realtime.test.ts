import assert from 'node:assert/strict';
import { afterEach, mock, test } from 'node:test';
import {
    catchUpAfterRestore,
    coalesce,
    commitChannel,
    connectRealtime,
    isListening,
    listen,
    liveTransport,
    pageReadAt,
    userChannel,
} from '../../resources/js/lib/realtime.ts';
import { fakeConnection } from './FakeRealtime.ts';

afterEach(() => {
    mock.timers.reset();
    connectRealtime(null);
});

void test('shares one subscription between listeners and leaves it once the last one stops', () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    const heard: string[] = [];

    const stopA = listen(userChannel('a'), {
        notifications: () => heard.push('a'),
    });
    const stopB = listen(userChannel('a'), {
        notifications: () => heard.push('b'),
    });
    fake.joined.get('private-users.a')?.emit('notifications');
    assert.deepEqual(heard, ['a', 'b']);

    stopA();
    fake.joined.get('private-users.a')?.emit('notifications');
    assert.deepEqual(heard, ['a', 'b', 'b']);

    stopB();
    mock.timers.tick(999);
    assert.deepEqual(fake.left, []);
    mock.timers.tick(1);
    assert.deepEqual(fake.left, ['private-users.a']);
});

void test('a listener arriving before the channel is left keeps the same subscription', () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const fake = fakeConnection();
    connectRealtime(fake.connection);

    listen(userChannel('a'), { notifications: () => {} })();
    const heard: unknown[] = [];
    listen(userChannel('a'), {
        notifications: (payload) => heard.push(payload),
    });
    mock.timers.tick(5_000);

    assert.deepEqual(fake.left, []);
    fake.joined.get('private-users.a')?.emit('notifications', { id: 1 });
    assert.deepEqual(heard, [{ id: 1 }]);
});

void test('catches up whenever the channel is joined, at first and after a dropped connection', () => {
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    let syncs = 0;
    listen(userChannel('a'), { notifications: () => {} }, () => syncs++);
    const channel = fake.joined.get('private-users.a');

    assert.equal(isListening(userChannel('a')), false);
    fake.setStatus('connected');
    channel?.join();
    assert.equal(syncs, 1);
    assert.equal(isListening(userChannel('a')), true);

    fake.setStatus('unavailable');
    assert.equal(isListening(userChannel('a')), false);
    fake.setStatus('connected');
    assert.equal(isListening(userChannel('a')), false);
    channel?.join();
    assert.equal(syncs, 2);
    assert.equal(isListening(userChannel('a')), true);
});

void test('a refused subscription is not listening, so pages keep polling', () => {
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    listen(userChannel('a'), { notifications: () => {} });
    fake.setStatus('connected');
    fake.joined.get('private-users.a')?.join();

    fake.joined.get('private-users.a')?.reject();

    assert.equal(isListening(userChannel('a')), false);
});

void test('without a connection nothing is heard and nothing is listening', () => {
    connectRealtime(null);
    const stop = listen(userChannel('a'), { notifications: () => {} });

    assert.equal(isListening(userChannel('a')), false);
    stop();
});

void test('live props treat joining their channel as an update, so they catch up with what was missed', () => {
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    const heard: unknown[] = [];

    liveTransport.subscribe(
        { name: 'users.a', type: 'private' },
        'notifications',
        (payload) => heard.push(payload),
    );
    fake.setStatus('connected');
    fake.joined.get('private-users.a')?.join();
    fake.joined.get('private-users.a')?.emit('notifications', { n: 1 });

    assert.deepEqual(heard, [{}, { n: 1 }]);
    assert.equal(liveTransport.socketId?.(), '1.2');
});

void test('coalesces a burst into one refresh and refreshes once more for requests made while running', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    let runs = 0;
    let finish = () => {};
    const refresh = coalesce(
        () =>
            new Promise<void>((resolve) => {
                runs++;
                finish = resolve;
            }),
        100,
    );

    refresh.request();
    refresh.request();
    refresh.request();
    mock.timers.tick(100);
    assert.equal(runs, 1);

    refresh.request();
    refresh.request();
    mock.timers.tick(100);
    assert.equal(runs, 1);

    finish();
    await Promise.resolve();
    await Promise.resolve();
    mock.timers.tick(100);
    assert.equal(runs, 2);

    finish();
    await Promise.resolve();
    await Promise.resolve();
    mock.timers.tick(1_000);
    assert.equal(runs, 2);
});

void test('a cancelled refresh never runs', () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    let runs = 0;
    const refresh = coalesce(async () => {
        runs++;
    }, 100);

    refresh.request();
    refresh.cancel();
    refresh.request();
    mock.timers.tick(1_000);

    assert.equal(runs, 0);
});

void test('a listener joining a channel heard on since the page read catches up, however long ago that was', async () => {
    mock.timers.enable({ apis: ['setTimeout', 'Date'], now: 1_000_000 });
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    listen(userChannel('review'), { notifications: () => {} });
    fake.setStatus('connected');
    fake.joined.get('private-users.review')?.join();
    pageReadAt(Date.now());
    mock.timers.tick(60_000);
    fake.joined.get('private-users.review')?.emit('account');
    mock.timers.tick(16_000);
    let reconciliations = 0;

    listen(
        userChannel('review'),
        { account: () => {} },
        () => reconciliations++,
    );
    await Promise.resolve();

    assert.equal(reconciliations, 1);
});

void test('a listener mounted long after an event that came after the page was read catches up', async () => {
    mock.timers.enable({ apis: ['setTimeout', 'Date'], now: 1_000_000 });
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    listen(commitChannel('c'), { replies: () => {} });
    fake.setStatus('connected');
    fake.joined.get('commits.c')?.join();
    pageReadAt(Date.now());
    mock.timers.tick(1_000);
    fake.joined.get('commits.c')?.emit('visibility');
    mock.timers.tick(30_000);
    let syncs = 0;

    listen(commitChannel('c'), { visibility: () => {} }, () => syncs++);
    await Promise.resolve();

    assert.equal(syncs, 1);
});

void test('a listener joining a channel that rejoined since the page was read catches up', async () => {
    mock.timers.enable({ apis: ['setTimeout', 'Date'], now: 1_000_000 });
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    listen(userChannel('a'), { notifications: () => {} });
    fake.setStatus('connected');
    fake.joined.get('private-users.a')?.join();
    pageReadAt(Date.now());
    mock.timers.tick(10_000);
    fake.setStatus('connecting');
    fake.setStatus('connected');
    fake.joined.get('private-users.a')?.join();
    mock.timers.tick(30_000);
    let syncs = 0;

    listen(userChannel('a'), { account: () => {} }, () => syncs++);
    await Promise.resolve();

    assert.equal(syncs, 1);
});

void test('a listener joining a channel listened to since before the page was read, with nothing heard since, needs no catching up', async () => {
    mock.timers.enable({ apis: ['setTimeout', 'Date'], now: 1_000_000 });
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    listen(userChannel('a'), { notifications: () => {} });
    fake.setStatus('connected');
    fake.joined.get('private-users.a')?.join();
    fake.joined.get('private-users.a')?.emit('account');
    mock.timers.tick(1_000);
    pageReadAt(Date.now());
    mock.timers.tick(60_000);
    let quiet = 0;
    let busy = 0;

    listen(userChannel('a'), { account: () => {} }, () => quiet++);
    fake.joined.get('private-users.a')?.emit('notifications');
    listen(userChannel('a'), { notifications: () => {} }, () => busy++);
    await Promise.resolve();

    assert.equal(quiet, 0);
    assert.equal(busy, 1);
});

void test('a page coming back from history makes every listener joining it catch up, however late, and reads live props again', async () => {
    mock.timers.enable({ apis: ['setTimeout', 'Date'], now: 1_000_000 });
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    const live: unknown[] = [];
    liveTransport.subscribe(
        { name: 'users.a', type: 'private' },
        'notifications',
        (payload) => live.push(payload),
    );
    fake.setStatus('connected');
    fake.joined.get('private-users.a')?.join();
    pageReadAt(Date.now());
    mock.timers.tick(60_000);
    live.length = 0;
    let restored = 0;
    let fresh = 0;

    catchUpAfterRestore();
    mock.timers.tick(30_000);
    listen(userChannel('a'), { account: () => {} }, () => restored++);
    await Promise.resolve();
    pageReadAt(Date.now());
    listen(userChannel('a'), { account: () => {} }, () => fresh++);
    await Promise.resolve();

    assert.equal(restored, 1);
    assert.equal(fresh, 0);
    assert.deepEqual(live, [{}]);
});
