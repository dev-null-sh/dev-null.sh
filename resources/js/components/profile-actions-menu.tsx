import { Form, router } from '@inertiajs/react';
import { Ellipsis, Radio, RadioOff } from 'lucide-react';
import { useState } from 'react';
import { endStream, GoLiveDialog } from '@/components/go-live-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { User } from '@/types';

/**
 * The quick actions on your own profile, where other people's profiles have the mute and block menu.
 */
export function ProfileActionsMenu({ user }: { user: Pick<User, 'live'> }) {
    const [isGoingLive, setIsGoingLive] = useState(false);
    const [isEnding, setIsEnding] = useState(false);

    return (
        <>
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="icon"
                        className="shrink-0"
                        aria-label="More options"
                        data-test="profile-actions-menu"
                    >
                        <Ellipsis />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                    {user.live ? (
                        <DropdownMenuItem
                            disabled={isEnding}
                            onSelect={() =>
                                endStream({
                                    onStart: () => setIsEnding(true),
                                    onFinish: () => setIsEnding(false),
                                })
                            }
                            data-test="end-stream"
                        >
                            <RadioOff />
                            End stream
                        </DropdownMenuItem>
                    ) : (
                        <DropdownMenuItem
                            onSelect={() => setIsGoingLive(true)}
                            data-test="go-live"
                        >
                            <Radio />
                            Go live
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <GoLiveDialog open={isGoingLive} onOpenChange={setIsGoingLive} />
        </>
    );
}
