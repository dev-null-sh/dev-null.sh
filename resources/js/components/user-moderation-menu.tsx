import { useForm } from '@inertiajs/react';
import { Ban, Gavel, Hourglass, Undo2 } from 'lucide-react';
import { useId, useState } from 'react';
import UserBanController from '@/actions/App/Http/Controllers/UserBanController';
import UserTimeoutController from '@/actions/App/Http/Controllers/UserTimeoutController';
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
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatFullDateTime } from '@/lib/utils';
import type { User } from '@/types';

export type UserModeration = {
    timeoutsCount: number;
    timeout: { reason: string; endsAt: string } | null;
    ban: { reason: string; bannedAt: string } | null;
    reasons: Record<string, string>;
    durations: Record<string, string>;
};

/**
 * What admins see of someone's timeouts and ban on their profile, next to the safety menu.
 */
export function UserModerationStatus({
    moderation,
}: {
    moderation: UserModeration;
}) {
    const { timeoutsCount, timeout, ban } = moderation;

    return (
        <p
            className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 font-mono text-xs text-muted-foreground"
            data-test="user-moderation-status"
        >
            <span>
                {timeoutsCount} {timeoutsCount === 1 ? 'timeout' : 'timeouts'}
            </span>
            {timeout && (
                <span className="rounded bg-amber-500/10 px-1.5 py-0.5 text-amber-700 dark:text-amber-400">
                    In timeout until {formatFullDateTime(timeout.endsAt)} ·{' '}
                    {timeout.reason}
                </span>
            )}
            {ban && (
                <span className="rounded bg-destructive/10 px-1.5 py-0.5 text-destructive">
                    Banned · {ban.reason}
                </span>
            )}
        </p>
    );
}

export function UserModerationMenu({
    user,
    moderation,
}: {
    user: Pick<User, 'username'>;
    moderation: UserModeration;
}) {
    const [isOpen, setOpen] = useState(false);
    // Kept apart from whether it's open, so the dialog doesn't change while it fades out...
    const [kind, setKind] = useState<'timeout' | 'ban'>('timeout');
    const open = (next: 'timeout' | 'ban') => {
        setKind(next);
        setOpen(true);
    };
    const lift = useForm({});

    return (
        <>
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="icon"
                        className="shrink-0"
                        aria-label={`Moderate @${user.username}`}
                        data-test="user-moderation-menu"
                    >
                        <Gavel />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56">
                    <DropdownMenuLabel className="font-mono text-xs font-normal text-muted-foreground">
                        {moderation.timeoutsCount}{' '}
                        {moderation.timeoutsCount === 1
                            ? 'timeout'
                            : 'timeouts'}{' '}
                        so far
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {moderation.timeout ? (
                        <DropdownMenuItem
                            disabled={lift.processing}
                            onSelect={() =>
                                lift.submit(
                                    UserTimeoutController.destroy(user),
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Undo2 />
                            End timeout
                        </DropdownMenuItem>
                    ) : (
                        <DropdownMenuItem
                            onSelect={() => open('timeout')}
                            data-test="user-timeout-item"
                        >
                            <Hourglass />
                            Put in timeout…
                        </DropdownMenuItem>
                    )}
                    {moderation.ban ? (
                        <DropdownMenuItem
                            disabled={lift.processing}
                            onSelect={() =>
                                lift.submit(UserBanController.destroy(user), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <Undo2 />
                            Unban
                        </DropdownMenuItem>
                    ) : (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => open('ban')}
                            data-test="user-ban-item"
                        >
                            <Ban />
                            Ban…
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <ModerationDialog
                open={isOpen}
                kind={kind}
                user={user}
                moderation={moderation}
                onClose={() => setOpen(false)}
            />
        </>
    );
}

function ModerationDialog({
    open,
    kind,
    user,
    moderation,
    onClose,
}: {
    open: boolean;
    kind: 'timeout' | 'ban';
    user: Pick<User, 'username'>;
    moderation: UserModeration;
    onClose: () => void;
}) {
    const id = useId();
    const durations = Object.entries(moderation.durations);
    const form = useForm({
        reason: '',
        duration: durations[1]?.[0] ?? '',
    });
    const isBan = kind === 'ban';

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next && !form.processing) {
                    onClose();
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogContent className="max-h-[calc(var(--visual-viewport-height,100dvh)-2rem)] overflow-y-auto">
                <DialogTitle>
                    {isBan
                        ? `Ban @${user.username}?`
                        : `Put @${user.username} in timeout`}
                </DialogTitle>
                <DialogDescription>
                    {isBan
                        ? "They'll be signed out of everything but a page that tells them why, where all they can do is delete their account. Their GitHub account can't sign up again."
                        : "They can still read, but they can't commit, reply, or send remotes until it's over. They're told why when they try."}
                </DialogDescription>

                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(
                            isBan
                                ? UserBanController.store(user)
                                : UserTimeoutController.store(user),
                            {
                                preserveScroll: true,
                                onSuccess: () => {
                                    onClose();
                                    form.reset();
                                },
                            },
                        );
                    }}
                >
                    {!isBan && (
                        <fieldset
                            className="grid gap-2"
                            disabled={form.processing}
                        >
                            <legend className="mb-2 text-sm font-medium">
                                For how long?
                            </legend>
                            <div className="flex flex-wrap gap-2">
                                {durations.map(([value, label]) => (
                                    <label
                                        key={value}
                                        className="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm has-checked:border-primary has-checked:bg-primary-soft"
                                    >
                                        <input
                                            type="radio"
                                            name={`${id}-duration`}
                                            value={value}
                                            checked={
                                                form.data.duration === value
                                            }
                                            onChange={() =>
                                                form.setData('duration', value)
                                            }
                                            className="size-4 accent-primary"
                                        />
                                        {label}
                                    </label>
                                ))}
                            </div>
                            <InputError message={form.errors.duration} />
                        </fieldset>
                    )}

                    <fieldset className="grid gap-2" disabled={form.processing}>
                        <legend className="mb-2 text-sm font-medium">
                            Why? They'll see this.
                        </legend>
                        {Object.entries(moderation.reasons).map(
                            ([value, label]) => (
                                <label
                                    key={value}
                                    className="flex min-h-9 cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm has-checked:border-primary has-checked:bg-primary-soft"
                                >
                                    <input
                                        type="radio"
                                        name={`${id}-reason`}
                                        value={value}
                                        checked={form.data.reason === value}
                                        onChange={() =>
                                            form.setData('reason', value)
                                        }
                                        required
                                        className="size-4 accent-primary"
                                    />
                                    {label}
                                </label>
                            ),
                        )}
                        <InputError message={form.errors.reason} />
                    </fieldset>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant={isBan ? 'destructive' : 'default'}
                            disabled={form.processing || !form.data.reason}
                            data-test="confirm-moderation-button"
                        >
                            {isBan ? 'Ban' : 'Put in timeout'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
