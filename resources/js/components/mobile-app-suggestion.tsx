import { X } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { ANDROID_APP_URL, IOS_APP_URL } from '@/lib/mobile-apps';
import { readStorage, writeStorage } from '@/lib/storage';

const DISMISSED = 'devnull:mobile-app-suggestion-dismissed';

const APPS = {
    ios: { name: 'iOS', store: 'TestFlight', url: IOS_APP_URL },
    android: { name: 'Android', store: 'Google Play', url: ANDROID_APP_URL },
};

const listeners = new Set<() => void>();

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

function dismiss(): void {
    writeStorage(DISMISSED, '1');
    listeners.forEach((listener) => listener());
}

function suggestedPlatform(): keyof typeof APPS | null {
    if (
        readStorage(DISMISSED) !== '' ||
        window.matchMedia('(display-mode: standalone)').matches
    ) {
        return null;
    }

    if (/iPhone/.test(navigator.userAgent)) {
        return 'ios';
    }

    return /Android.*Mobile/.test(navigator.userAgent) ? 'android' : null;
}

/**
 * Sits on the mobile nav beside the commit button, at the same height, and leaves with the nav when the keyboard opens.
 * It stays out of the server render, which cannot read the phone, and a dismissal lasts in this browser.
 */
export function MobileAppSuggestion() {
    const platform = useSyncExternalStore(
        subscribe,
        suggestedPlatform,
        () => null,
    );

    if (platform === null) {
        return null;
    }

    const app = APPS[platform];

    return (
        <div
            className="absolute right-22 bottom-full left-4 mb-4 flex h-14 items-center rounded-lg border bg-card shadow-lg"
            data-test="mobile-app-suggestion"
        >
            <a
                href={app.url}
                target="_blank"
                rel="noopener noreferrer"
                className="flex h-full min-w-0 flex-1 items-center gap-2.5 rounded-l-lg pl-2.5"
            >
                <img
                    src="/images/apps/mobile-icon.webp"
                    alt=""
                    width={256}
                    height={256}
                    className="size-9 shrink-0 rounded-[22%] border"
                />
                <span className="min-w-0">
                    <span className="block truncate text-sm font-semibold">
                        Try the {app.name} app
                    </span>
                    <span className="block truncate text-xs text-muted-foreground">
                        Public beta on {app.store}
                    </span>
                </span>
            </a>
            <button
                type="button"
                onClick={dismiss}
                aria-label={`Dismiss the ${app.name} app suggestion`}
                className="flex h-full w-11 shrink-0 items-center justify-center rounded-r-lg text-muted-foreground"
            >
                <X className="size-4" />
            </button>
        </div>
    );
}
