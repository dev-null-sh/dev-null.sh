import { router } from '@inertiajs/react';
import { Radio, RadioOff } from 'lucide-react';
import { useState } from 'react';
import GuildLiveController from '@/actions/App/Http/Controllers/GuildLiveController';
import { GoLiveDialog } from '@/components/go-live-dialog';
import { Button } from '@/components/ui/button';
import type { LiveStream } from '@/types';

/**
 * Going live, or ending the stream, from a guild the signed-in developer moderates.
 */
export function GuildLiveButton({
    handle,
    live,
}: {
    handle: string;
    live: LiveStream | null;
}) {
    const [isGoingLive, setIsGoingLive] = useState(false);
    const [isEnding, setIsEnding] = useState(false);

    return (
        <>
            {live ? (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="shrink-0"
                    disabled={isEnding}
                    onClick={() => {
                        setIsEnding(true);
                        router.visit(
                            GuildLiveController.destroy({ guild: handle }),
                            {
                                preserveScroll: true,
                                onFinish: () => setIsEnding(false),
                                onSuccess: () => router.flushAll(),
                            },
                        );
                    }}
                    aria-label="End stream"
                    title="End stream"
                    data-test="end-guild-stream"
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
                    data-test="go-live-guild"
                >
                    <Radio />
                </Button>
            )}

            <GoLiveDialog
                open={isGoingLive}
                onOpenChange={setIsGoingLive}
                form={GuildLiveController.update.form({ guild: handle })}
                description="Share a YouTube or Twitch stream on this guild. People open it from the guild page, for as long as you choose."
            />
        </>
    );
}
