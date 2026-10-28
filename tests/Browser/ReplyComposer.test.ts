import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { chromium } from 'playwright';
import { createServer } from 'vite';

await test(
    'submits only checked recipients and displays indexed recipient errors',
    { timeout: 60000 },
    async (context) => {
        execFileSync('php', ['artisan', 'wayfinder:generate', '--with-form'], {
            cwd: fileURLToPath(new URL('../../', import.meta.url)),
            stdio: 'pipe',
        });
        const server = await createServer({
            configFile: false,
            cacheDir: 'node_modules/.vite-reply-composer-tests',
            resolve: {
                alias: {
                    '@': fileURLToPath(
                        new URL('../../resources/js', import.meta.url),
                    ),
                },
            },
            plugins: [
                react(),
                {
                    name: 'reply-composer-fixture',
                    configureServer(server) {
                        server.middlewares.use(
                            '/reply-composer',
                            async (_request, response) => {
                                const html = await server.transformIndexHtml(
                                    '/reply-composer',
                                    '<div id="app"></div><script type="module" src="/tests/Browser/fixtures/reply-composer.tsx"></script>',
                                );
                                response.setHeader('Content-Type', 'text/html');
                                response.end(html);
                            },
                        );
                    },
                },
            ],
            server: { host: '127.0.0.1', port: 0 },
        });
        context.after(() => server.close());
        await server.listen();
        const browser = await chromium.launch();
        try {
            const page = await browser.newPage();
            await page.route('**/commits/own-commit/replies', (route) =>
                route.fulfill({
                    status: 422,
                    json: {
                        errors: {
                            'reply_recipients.0':
                                'Recipient is no longer available.',
                            link_preview_url:
                                'The link preview is unavailable.',
                        },
                    },
                }),
            );
            const url = server.resolvedUrls?.local[0];
            assert.ok(url);
            await page.goto(`${url}reply-composer`);
            const trigger = page.getByRole('button', { name: /Replying to/ });
            await trigger.click();
            await page
                .getByRole('checkbox', {
                    name: 'Include @excluded',
                    exact: true,
                })
                .uncheck();
            await page
                .getByRole('button', { name: 'Done', exact: true })
                .click();
            await page.waitForFunction(
                () =>
                    document.activeElement?.getAttribute('data-slot') ===
                    'dialog-trigger',
            );
            await page.getByRole('textbox').fill('Hello everyone');
            const requestPromise = page.waitForRequest(
                (request) =>
                    request.url().endsWith('/commits/own-commit/replies') &&
                    request.method() === 'POST',
            );
            await page
                .getByRole('button', { name: 'Reply', exact: true })
                .click();
            const request = await requestPromise;
            assert.deepEqual(request.postDataJSON().reply_recipients, [
                'retained-user',
            ]);
            await page
                .getByText('Recipient is no longer available.', { exact: true })
                .waitFor();
        } finally {
            await browser.close();
        }
    },
);
