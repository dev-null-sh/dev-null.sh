import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { loadTurnstile } from '@/lib/turnstile';

export function HumanVerification({
    onToken,
}: {
    onToken: (token: string) => void;
}) {
    const { humanVerificationSiteKey } = usePage().props;
    const container = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const element = container.current;

        if (element === null) {
            return;
        }

        const lifetime = new AbortController();

        void loadTurnstile().then((turnstile) => {
            if (lifetime.signal.aborted) {
                return;
            }

            const widgetId = turnstile.render(element, {
                sitekey: humanVerificationSiteKey,
                size: 'flexible',
                theme: document.documentElement.classList.contains('dark')
                    ? 'dark'
                    : 'light',
                callback: onToken,
                'expired-callback': () => onToken(''),
                'error-callback': () => onToken(''),
            });

            lifetime.signal.addEventListener('abort', () =>
                turnstile.remove(widgetId),
            );
        });

        return () => lifetime.abort();
    }, [humanVerificationSiteKey, onToken]);

    return (
        <div
            ref={container}
            className="h-[65px]"
            data-test="human-verification"
        />
    );
}
