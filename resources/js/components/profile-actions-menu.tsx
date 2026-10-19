import { Form, router } from '@inertiajs/react';
import { Ellipsis, Radio, Square } from 'lucide-react';
import { useState } from 'react';
import UserLiveController from '@/actions/App/Http/Controllers/UserLiveController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import type { User } from '@/types';

const hours = Array.from({ length: 12 }, (_, index) => index + 1);

/**
 * The quick actions on your own profile, where other people's profiles have the mute and block menu.
 */
export function ProfileActionsMenu({ user }: { user: Pick<User, 'live'> }) {
    const [isGoingLive, setIsGoingLive] = useState(false);
    const [isEnding, setIsEnding] = useState(false);

    const endStream = () =>
        router.visit(UserLiveController.destroy(), {
            preserveScroll: true,
            onStart: () => setIsEnding(true),
            onFinish: () => setIsEnding(false),
            onSuccess: () => router.flushAll(),
        });

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
                            onSelect={endStream}
                            data-test="end-stream"
                        >
                            <Square />
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

            <Dialog open={isGoingLive} onOpenChange={setIsGoingLive}>
                <DialogContent>
                    <DialogTitle>Go live</DialogTitle>
                    <DialogDescription>
                        Share your YouTube stream and your avatar gets a live
                        marker that sends people to it, for as long as you
                        choose.
                    </DialogDescription>

                    <Form
                        {...UserLiveController.update.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => {
                            setIsGoingLive(false);
                            router.flushAll();
                        }}
                        resetOnSuccess
                    >
                        {({ processing, errors }) => (
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="live-url">
                                        Stream link
                                    </Label>
                                    <input
                                        id="live-url"
                                        type="url"
                                        name="url"
                                        required
                                        autoFocus
                                        placeholder="https://www.youtube.com/live/…"
                                        className="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                                        data-test="live-url-input"
                                    />
                                    <InputError message={errors.url} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="live-hours">For</Label>
                                    <select
                                        id="live-hours"
                                        name="hours"
                                        defaultValue={2}
                                        className="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                                        data-test="live-hours-select"
                                    >
                                        {hours.map((count) => (
                                            <option key={count} value={count}>
                                                {count === 1
                                                    ? '1 hour'
                                                    : `${count} hours`}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.hours} />
                                </div>

                                <DialogFooter className="mt-2 gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="go-live-button"
                                    >
                                        <Radio />
                                        Go live
                                    </Button>
                                </DialogFooter>
                            </div>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}
