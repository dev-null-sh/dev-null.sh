import { Bell, BellOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePushNotifications } from '@/hooks/use-push-notifications';

export function PushToggle() {
    const { state, enable, disable } = usePushNotifications();

    if (state === 'unsupported') {
        return (
            <span className="text-sm text-muted-foreground">
                This browser doesn't support push notifications.
            </span>
        );
    }

    if (state === 'denied') {
        return (
            <span className="text-xs text-muted-foreground">
                Push blocked in browser settings
            </span>
        );
    }

    const isEnabled = state === 'enabled';

    return (
        <Button
            variant="outline"
            size="sm"
            disabled={state === 'busy'}
            onClick={() => void (isEnabled ? disable() : enable())}
        >
            {isEnabled ? <BellOff /> : <Bell />}
            {isEnabled ? 'Disable push' : 'Enable push'}
        </Button>
    );
}
