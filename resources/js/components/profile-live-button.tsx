import { Radio, RadioOff } from 'lucide-react';
import { useState } from 'react';
import { endStream, GoLiveDialog } from '@/components/go-live-dialog';
import { Button } from '@/components/ui/button';
import { useLiveStream } from '@/hooks/use-live-stream';
import type { User } from '@/types';

/**
 * Going live, or ending the stream, from your own profile, where other people's profiles have the mute and block menu.
 */
export function ProfileLiveButton({
    user,
}: {
    user: Pick<User, 'id' | 'live'>;
}) {
    const [isGoingLive, setIsGoingLive] = useState(false);
    const stream = useLiveStream(user);
    const [isEnding, setIsEnding] = useState(false);

    return (
        <>
            {stream ? (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="shrink-0"
                    disabled={isEnding}
                    onClick={() =>
                        endStream({
                            onStart: () => setIsEnding(true),
                            onFinish: () => setIsEnding(false),
                        })
                    }
                    aria-label="End stream"
                    title="End stream"
                    data-test="end-stream"
                >
                    <RadioOff />
                </Button>
            ) : (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="shrink-0"
                    onClick={() => setIsGoingLive(true)}
                    aria-label="Go live"
                    title="Go live"
                    data-test="go-live"
                >
                    <Radio />
                </Button>
            )}

            <GoLiveDialog open={isGoingLive} onOpenChange={setIsGoingLive} />
        </>
    );
}
