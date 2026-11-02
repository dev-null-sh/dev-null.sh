import assert from 'node:assert/strict';
import { test } from 'node:test';
import { inAppBrowser } from '../../resources/js/lib/in-app-browser.ts';

const SAFARI =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1';
const IOS_WEB_VIEW =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148';
const CHROME_ANDROID =
    'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36';
const ANDROID_WEB_VIEW =
    'Mozilla/5.0 (Linux; Android 15; Pixel 9; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/141.0.0.0 Mobile Safari/537.36';

await test('tells the app whose browser shows the page, when it names itself', () => {
    assert.deepEqual(inAppBrowser(`${IOS_WEB_VIEW} Twitter for iPhone`), { app: 'X', platform: 'ios' });
    assert.deepEqual(inAppBrowser(`${IOS_WEB_VIEW} Instagram 400.0.0.0`), { app: 'Instagram', platform: 'ios' });
    assert.deepEqual(inAppBrowser(`${ANDROID_WEB_VIEW} [FB_IAB/FB4A;FBAV/500.0.0.0;]`), { app: 'Facebook', platform: 'android' });
    assert.deepEqual(inAppBrowser(`${SAFARI} LinkedInApp/9.30`), { app: 'LinkedIn', platform: 'ios' });
});

await test('tells web views of apps that do not name themselves', () => {
    assert.deepEqual(inAppBrowser(IOS_WEB_VIEW), { app: null, platform: 'ios' });
    assert.deepEqual(inAppBrowser(ANDROID_WEB_VIEW), { app: null, platform: 'android' });
});

await test('leaves the phone browsers and computers alone', () => {
    for (const userAgent of [
        SAFARI,
        CHROME_ANDROID,
        'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/141.0.0.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/144.0 Mobile/15E148 Safari/605.1.15',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15',
    ]) {
        assert.equal(inAppBrowser(userAgent), null);
    }
});

