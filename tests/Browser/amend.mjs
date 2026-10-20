import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH });
let page = await browser.newPage({ viewport: { width: Number(process.env.WIDTH ?? 1440), height: 1000 } });
const original = `Amend browser test ${Date.now()}: shiped it`;
const amended = original.replace('shiped', 'shipped');
try {
    await page.goto(`${process.env.TEST_URL ?? 'http://127.0.0.1:8000'}/login`);
    await page.getByLabel('Username', { exact: true }).fill(process.env.TEST_USERNAME ?? 'antti');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await page.waitForURL('**/log/**');
    await page.locator('[data-test="commit-composer"] textarea').fill(original);
    await page.locator('[data-test="create-commit-button"]').click();
    const card = page.locator('article[data-commit]').filter({ hasText: original }).first();
    await card.waitFor();
    const id = await card.getAttribute('data-commit');
    const stableCard = page.locator(`article[data-commit="${id}"]`).first();
    if (process.env.SCENARIO === 'thread') {
        const moderatorPage = page;
        page = await browser.newPage();
        await page.goto(`${process.env.TEST_URL ?? 'http://127.0.0.1:8000'}/login`);
        await page.getByLabel('Username', { exact: true }).fill('amend-browser-reader');
        await page.getByRole('button', { name: 'Sign in', exact: true }).click();
        await page.waitForURL('**/log/**');
        const result = await page.evaluate(async ({ id }) => {
            const token = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='))?.split('=').slice(1).join('=');
            const response = await fetch(`/commits/${id}/replies`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(token ?? '') },
                body: JSON.stringify({ body: 'A reply with a typo to fix', source_version: id }),
            });
            return { status: response.status, data: response.ok ? await response.json() : null };
        }, { id, original });
        assert.equal(result.status, 201);
        const approved = await moderatorPage.evaluate(async (replyId) => {
            const token = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='))?.split('=').slice(1).join('=');
            const response = await fetch(`/moderation/${replyId}/approval`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(token ?? '') },
                body: JSON.stringify({ expected_version: replyId }),
            });
            return response.ok;
        }, result.data.committed.id);
        assert.equal(approved, true);
        await page.goto(`${process.env.TEST_URL ?? 'http://127.0.0.1:8000'}/log/latest`);
        const toggle = page.locator(`article[data-commit="${id}"]`).first().locator('[data-test="toggle-replies-button"]');
        if (await toggle.count() && await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
        const replyUrl = await page.locator('[data-test="reply"]').filter({ hasText: 'A reply with a typo to fix' }).first().getAttribute('data-permalink');
        const reply = page.locator(`[data-test="reply"][data-permalink="${replyUrl}"]`);
        await reply.getByRole('button', { name: 'Amend reply', exact: true }).click();
        await reply.getByLabel('Commit text', { exact: true }).fill('A reply with a typo');
        await reply.getByRole('button', { name: 'Amend', exact: true }).click();
        await reply.getByRole('button', { name: 'amended', exact: true }).click();
        await page.locator('[data-test="commit-versions"]').getByText('A reply with a typo', { exact: true }).waitFor();
        console.log('Thread amendment and history passed');
        process.exitCode = 0;
    } else {
    await stableCard.hover();
    await stableCard.getByRole('button', { name: 'Amend commit', exact: true }).click();
    const input = stableCard.getByLabel('Commit text', { exact: true });
    assert.equal(await input.evaluate(el => el.selectionStart === el.value.length && document.activeElement === el), true);
    await input.evaluate(el => el.setSelectionRange(0, 0));
    await input.press('X');
    assert.equal(await input.evaluate(el => el.selectionStart), 1);
    await input.fill(amended);
    if (process.env.SCENARIO === 'secret') {
        await input.fill('ghp_' + 'Ab3dEf6hIj9kLm2nOp5qRs8tUv1wXy4zABCD');
        await stableCard.getByRole('button', { name: 'Amend', exact: true }).click();
        await page.getByRole('dialog').getByText('Publish possible secrets?', { exact: true }).waitFor();
        assert.equal(await stableCard.locator('[data-test="commit-edited"]').count(), 0);
    } else if (process.env.SCENARIO === 'expired') {
        await page.clock.setFixedTime(new Date(Date.now() + 16 * 60 * 1000));
        await input.press('Control+Enter');
        await stableCard.getByRole('alert').filter({ hasText: 'The 15-minute amendment window has closed.' }).waitFor();
        assert.equal(await input.inputValue(), amended);
    } else {
        await stableCard.getByRole('button', { name: 'Amend', exact: true }).click();
        await stableCard.getByText(amended, { exact: true }).waitFor();
        await stableCard.getByRole('button', { name: 'amended', exact: true }).click();
        await page.locator('[data-test="commit-versions"]').getByText(original, { exact: true }).waitFor();
        await page.locator('[data-test="commit-versions"]').getByText(amended, { exact: true }).waitFor();
        await page.getByRole('button', { name: 'Close', exact: true }).click();
        await page.reload();
        await page.locator('article[data-commit]').filter({ hasText: amended }).first().getByRole('button', { name: 'amended', exact: true }).waitFor();
    }
    console.log(`Amend browser check passed (${process.env.SCENARIO ?? 'save'}, ${process.env.WIDTH ?? 1440}px)`);
    }
} catch (error) {
    console.error(await page.locator('body').innerText());
    throw error;
} finally {
    await browser.close();
}
