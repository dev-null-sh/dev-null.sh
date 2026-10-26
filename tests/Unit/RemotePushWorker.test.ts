import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

function worker(windows: object[] = []) {
    const handlers = new Map<string, (event: object) => void>();
    const notifications: { title: string; options: { body: string; data: { url: string }; silent: boolean } }[] = [];
    const opened: string[] = [];
    const self = {
        location: { href: 'https://dev-null.sh/sw.js?v=test', origin: 'https://dev-null.sh' },
        addEventListener: (name: string, handler: (event: object) => void) => handlers.set(name, handler),
        registration: { showNotification: async (title: string, options: typeof notifications[number]['options']) => { notifications.push({ title, options }); } },
        clients: {
            matchAll: async () => windows,
            openWindow: async (url: string) => { opened.push(url); },
        },
    };
    runInNewContext(readFileSync(new URL('../../public/sw.js', import.meta.url), 'utf8'), { self, URL });
    return { handlers, notifications, opened };
}

void test('a push with no page open shows a silent generic notification without needing a payload or network', async () => {
    const { handlers, notifications } = worker();
    let finished = Promise.resolve();
    handlers.get('push')!({ waitUntil: (promise: Promise<void>) => { finished = promise; } });
    await finished;
    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].title, '/dev/null · Remotes');
    assert.equal(notifications[0].options.body, 'You have a new message in Remotes.');
    assert.equal(notifications[0].options.silent, true);
    assert.equal(notifications[0].options.data.url, '/remotes');
});

void test('clicking a notification opens the inbox when the site is closed', async () => {
    const { handlers, opened } = worker();
    let finished = Promise.resolve();
    let closed = false;
    handlers.get('notificationclick')!({ notification: { close: () => { closed = true; }, data: { url: '/remotes' } }, waitUntil: (promise: Promise<void>) => { finished = promise; } });
    await finished;
    assert.equal(closed, true);
    assert.deepEqual(opened, ['https://dev-null.sh/remotes']);
});

void test('clicking reuses and focuses an existing remote window', async () => {
    const navigated: string[] = [];
    let focused = false;
    const { handlers, opened } = worker([{ url: 'https://dev-null.sh/log/latest' }, { url: 'https://dev-null.sh/remotes/@alice', navigate: async (url: string) => { navigated.push(url); }, focus: async () => { focused = true; } }]);
    let finished = Promise.resolve();
    handlers.get('notificationclick')!({ notification: { close() {}, data: { url: '/remotes' } }, waitUntil: (promise: Promise<void>) => { finished = promise; } });
    await finished;
    assert.equal(focused, true);
    assert.deepEqual(navigated, ['https://dev-null.sh/remotes']);
    assert.deepEqual(opened, []);
});

void test('notification clicks cannot navigate to another origin', () => {
    const { handlers } = worker();
    let waited = false;
    handlers.get('notificationclick')!({ notification: { close() {}, data: { url: 'https://attacker.test/' } }, waitUntil: () => { waited = true; } });
    assert.equal(waited, false);
});
