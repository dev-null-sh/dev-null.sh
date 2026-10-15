import { Bell, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { usePushNotifications } from '@/hooks/use-push-notifications';

const DISMISSED_KEY = 'push-prompt-dismissed';

// Storage can throw in private windows; then the banner just shows again next visit.
function wasDismissed(): boolean {
    try {
        return localStorage.getItem(DISMISSED_KEY) === '1';
    } catch {
        return false;
    }
}

export function PushPromptBanner() {
    const { state, enable } = usePushNotifications();
    const [isDismissed, setIsDismissed] = useState(false);

    // `state` starts as 'busy' on server and client, so reading storage here can't break hydration.
    if (state !== 'disabled' || isDismissed || wasDismissed()) {
        return null;
    }

    const dismiss = () => {
        try {
            localStorage.setItem(DISMISSED_KEY, '1');
        } catch {
            // Ignore, see wasDismissed().
        }

        setIsDismissed(true);
    };

    return (
        <div className="flex items-center gap-3 border-b bg-primary-soft px-4 py-3 text-sm sm:px-6">
            <Bell className="size-4 shrink-0 text-primary" />
            <p className="min-w-0 flex-1">
                Get notified when people you follow commit?
            </p>
            <Button size="sm" onClick={() => void enable()}>
                Enable
            </Button>
            <Button
                variant="ghost"
                size="icon"
                className="size-8"
                aria-label="Not now"
                onClick={dismiss}
            >
                <X />
            </Button>
        </div>
    );
}
