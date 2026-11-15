import assert from 'node:assert/strict';
import { afterEach, test, vi } from 'vite-plus/test';
import { registerServiceWorker } from '../../resources/js/lib/service-worker';

afterEach(() => { vi.unstubAllGlobals(); vi.unstubAllEnvs(); });

test.each([true, false])('preview opt-out removes existing workers even with PROD=%s', async (production) => {
    vi.stubEnv('PROD', production);
    const unregister = vi.fn(async () => true);
    const register = vi.fn();
    vi.stubGlobal('document', { querySelector: () => ({}) });
    vi.stubGlobal('navigator', { serviceWorker: { register, getRegistrations: async () => [{ unregister }, { unregister }] } });

    registerServiceWorker('build');
    await vi.waitFor(() => assert.equal(unregister.mock.calls.length, 2));
    assert.equal(register.mock.calls.length, 0);
});

test.each(['loading', 'complete'])('enabled production retains versioned registration when the document is %s', (readyState) => {
    vi.stubEnv('PROD', true);
    const register = vi.fn(async (_url: URL, _options: RegistrationOptions) => ({}));
    let loaded: (() => void) | undefined;
    vi.stubGlobal('document', { querySelector: () => null, readyState });
    vi.stubGlobal('window', { location: { origin: 'https://example.com' }, addEventListener: (_event: string, callback: () => void) => { loaded = callback; } });
    vi.stubGlobal('navigator', { serviceWorker: { register } });

    registerServiceWorker('build');
    if (readyState === 'loading') {
        assert.equal(register.mock.calls.length, 0);
        loaded!();
    }
    assert.equal(register.mock.calls.length, 1);
    assert.equal(String(register.mock.calls[0][0]), 'https://example.com/sw.js?v=build');
    assert.deepEqual(register.mock.calls[0][1], { scope: '/', updateViaCache: 'none' });
});

test('development assets do not register a service worker', () => {
    vi.stubEnv('PROD', false);
    const register = vi.fn();
    vi.stubGlobal('document', { querySelector: () => null });
    vi.stubGlobal('navigator', { serviceWorker: { register } });

    registerServiceWorker(null);
    assert.equal(register.mock.calls.length, 0);
});
