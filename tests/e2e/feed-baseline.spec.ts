import { test } from '@playwright/test';

test('feed baseline DOM size', async ({ page }) => {
    await page.goto('/');
    await page.getByTestId('local-sign-in-username').fill('local-dev');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForURL(/\/log\//);
    await page.waitForSelector('[data-commit]');

    let previous = 0;
    for (let i = 0; i < 20; i++) {
        const count = await page.locator('[data-commit]').count();
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(1200);
        const next = await page.locator('[data-commit]').count();
        if (next === count && next === previous) {
            break;
        }
        previous = count;
    }

    const result = await page.evaluate(() => ({
        commits: document.querySelectorAll('[data-commit]').length,
        totalNodes: document.querySelectorAll('*').length,
        scrollHeight: document.body.scrollHeight,
        viewportHeight: window.innerHeight,
    }));

    console.log('FEED_BASELINE', JSON.stringify(result));
});
